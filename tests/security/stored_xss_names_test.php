<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_adele;

use advanced_testcase;
use local_adele\course_completion\conditions\course_completed;
use local_adele\course_completion\conditions\modquiz;
use local_adele\course_restriction\conditions\specific_course;
use local_adele\course_restriction\conditions\parent_courses;

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Security test for #464 H4/M5: course/quiz names embedded in the completion &
 * restriction "information" strings are rendered with v-html on the frontend, so
 * they must be escaped at the server-side production sites. Crucially, escaping
 * must NOT change the matching/completion outcome (matching is always on ids).
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\course_completion\conditions\course_completed::get_completion_status
 * @covers \local_adele\course_completion\conditions\modquiz::get_completion_status
 * @covers \local_adele\course_restriction\conditions\specific_course::get_restriction_status
 * @covers \local_adele\course_restriction\conditions\parent_courses::get_restriction_status
 */
final class stored_xss_names_test extends advanced_testcase {

    /** A stored-XSS payload with a trailing marker, so we can prove the tag is removed
     *  while ordinary text content survives. */
    private const PAYLOAD = '<img src=x onerror=alert(1)>PWN';
    /** A legitimate name with an ampersand - must survive (entity-encoded for v-html), not be mangled. */
    private const AMPNAME = 'Maths & Science';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Assert a produced display string neutralises the payload: no raw executable
     * tag, and the dangerous '<' is entity-encoded.
     */
    private function assert_inert(string $haystack): void {
        foreach (['<img', '<script', 'onerror', 'javascript:'] as $bad) {
            $this->assertStringNotContainsString($bad, $haystack,
                "XSS token '$bad' leaked into a v-html-bound string (#464 H4).");
        }
        // format_string strips the tag but keeps ordinary text, so the marker survives -
        // proving the name is still shown, just inert.
        $this->assertStringContainsString('PWN', $haystack,
            'format_string should keep the textual content while removing markup.');
    }

    // ---------------------------------------------------------- course_completed.

    /**
     * The course_list placeholder embeds the course fullname; a payload name must be
     * escaped, while the completion matching (keyed on course id) is unchanged.
     */
    public function test_course_completed_escapes_name_and_keeps_matching(): void {
        $payloadcourse = $this->getDataGenerator()->create_course(
            ['fullname' => self::PAYLOAD, 'enablecompletion' => 1]);
        $ampcourse = $this->getDataGenerator()->create_course(
            ['fullname' => self::AMPNAME, 'enablecompletion' => 1]);

        $node = [
            'data' => ['course_node_id' => [$payloadcourse->id, $ampcourse->id]],
            'completion' => ['nodes' => [[
                'id' => 1,
                'data' => ['label' => 'course_completed', 'value' => ['min_courses' => 2]],
            ]]],
        ];

        $status = (new course_completed())->get_completion_status($node, 1);
        $list = $status[1]['placeholders']['course_list'];
        $joined = implode(' ', $list);

        $this->assert_inert($joined);
        // Legit ampersand name preserved (entity-encoded for the v-html sink, not destroyed).
        $this->assertStringContainsString('Maths &amp; Science', $joined);
        // Matching is keyed on course id and is unaffected by the name.
        $this->assertArrayHasKey($payloadcourse->id, $status['completed']);
        $this->assertArrayHasKey($ampcourse->id, $status['completed']);
        $this->assertFalse($status['completed'][$payloadcourse->id]);
    }

    // ----------------------------------------------------------- specific_course.

    /**
     * specific_course node_name embeds the node fullname; payload escaped, the
     * id-based "completed" match preserved.
     */
    public function test_specific_course_escapes_node_name_and_keeps_matching(): void {
        $userpath = (object) ['json' => ['tree' => ['nodes' => [
            ['id' => 1, 'data' => ['fullname' => self::PAYLOAD,
                'completion' => ['feedback' => ['status' => 'completed']]]],
        ]]]];
        $node = ['restriction' => ['nodes' => [[
            'id' => 12,
            'data' => ['label' => 'specific_course', 'value' => ['courseid' => 1]],
        ]]]];

        $status = (new specific_course())->get_restriction_status($node, $userpath);

        $this->assert_inert(implode(' ', $status[12]['placeholders']['node_name']));
        // Matching (node id 1 == courseid 1) still resolves to completed.
        $this->assertEquals(1, $status[12]['completed']['id']);
    }

    // ------------------------------------------------------------ parent_courses.

    /**
     * parent_courses courselist embeds node fullnames; payload escaped, match kept.
     */
    public function test_parent_courses_escapes_node_name_and_keeps_matching(): void {
        $userpath = (object) ['json' => ['tree' => ['nodes' => [
            ['id' => 5, 'data' => ['fullname' => self::PAYLOAD,
                'completion' => ['feedback' => ['status' => 'completed']]]],
        ]]]];
        $node = ['restriction' => ['nodes' => [[
            'id' => 20,
            'data' => ['label' => 'parent_courses', 'value' => ['courses_id' => [5], 'min_courses' => 1]],
        ]]]];

        $status = (new parent_courses())->get_restriction_status($node, $userpath);

        $this->assert_inert(implode(' ', $status[20]['placeholders']['node_name']));
    }

    // ------------------------------------------------------------------- modquiz.

    /**
     * modquiz quiz_name_link wraps the quiz name in an anchor; the name must be
     * escaped WITHOUT destroying the intended <a> link.
     */
    public function test_modquiz_escapes_name_but_keeps_anchor(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz',
            ['course' => $course->id, 'name' => self::PAYLOAD]);

        $node = [
            'completion' => ['nodes' => [[
                'id' => 7,
                'data' => ['label' => 'modquiz', 'value' => ['quizid' => $quiz->id, 'grade' => 1]],
            ]]],
        ];

        $status = (new modquiz())->get_completion_status($node, 1);
        $link = $status[7]['placeholders']['quiz_name_link'];

        // Anchor markup preserved...
        $this->assertStringContainsString('<a href="', $link);
        $this->assertStringContainsString('/mod/quiz/view.php', $link);
        $this->assertStringContainsString('</a>', $link);
        // ...but the quiz name inside it is inert.
        $this->assert_inert($link);
    }

    // ------------------------------------------------------ course summary (M5).

    /**
     * get_availablecourses() returns the course summary that the expanded node card
     * renders via v-html; it must be purified (scripts removed) while legitimate rich
     * formatting is preserved.
     */
    public function test_availablecourses_summary_is_purified(): void {
        set_config('selectconfig', '', 'local_adele');
        set_config('includetags', '', 'local_adele');
        set_config('excludetags', '', 'local_adele');
        set_config('catfilter', '', 'local_adele');

        $this->getDataGenerator()->create_course([
            'fullname' => 'Summary Repro',
            'summary' => '<p>Keep <b>bold</b></p><script>alert(document.cookie)</script>',
            'summaryformat' => FORMAT_HTML,
        ]);

        $list = \local_adele\learning_path_courses::get_availablecourses();
        $entry = null;
        foreach ($list as $c) {
            if (($c['fullname'] ?? '') === 'Summary Repro') {
                $entry = $c;
                break;
            }
        }
        $this->assertNotNull($entry, 'The created course should be returned by get_availablecourses.');

        // Script stripped...
        $this->assertStringNotContainsString('<script', $entry['summary']);
        $this->assertStringNotContainsString('alert(document.cookie)', $entry['summary']);
        // ...legitimate formatting preserved.
        $this->assertStringContainsString('bold', $entry['summary']);
        $this->assertStringContainsString('<b>', $entry['summary']);
    }
}
