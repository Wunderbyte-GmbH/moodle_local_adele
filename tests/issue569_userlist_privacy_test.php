<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The mod_adele activity setting 'Participant results' = 'Only own results'
 * (userlist = 2) must be enforced SERVER-SIDE.
 *
 * GitHub #569: with 'Only own results' set, students still saw the complete
 * leaderboard. The only filter lived in UserList.vue and was dead code
 * (string/number strict comparison) - and even a working client filter would
 * not help, because get_lp_user_path_relations returned every participant's
 * name, progress and rank to any enrolled student regardless of the setting.
 *
 * The external classes require_once() the legacy lib/externallib.php, which
 * demands process isolation under PHPUnit.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The #[...] attributes between the class docblock and the class keyword hide
// the class-level @covers tag from this sniff (same as external_read_services_test).
// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing

namespace local_adele;

use advanced_testcase;
use context_course;
use context_module;
use local_adele\external\get_lp_user_path_relations;

/**
 * Server-side enforcement of the 'Only own results' activity setting (#569).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @covers \local_adele\external\get_lp_user_path_relations
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class issue569_userlist_privacy_test extends advanced_testcase {
    /** @var \stdClass Course the path is embedded in. */
    private $course;
    /** @var \stdClass First student (the caller in most scenarios). */
    private $student1;
    /** @var \stdClass Second student (whose results must stay private). */
    private $student2;
    /** @var int Learning path id. */
    private $lpid;
    /** @var \stdClass|null The adele activity instance (null when unembedded). */
    private $adele;

    /**
     * Course with two enrolled students, a learning path both are subscribed
     * to, and (optionally) a mod_adele embedding with the given setting.
     *
     * @param int|null $userlist 2 = only own results, 1 = all results, null = no activity at all.
     * @return void
     */
    private function build_world(?int $userlist): void {
        global $DB;
        $gen = self::getDataGenerator();
        $this->course = $gen->create_course();
        $this->student1 = $gen->create_user();
        $this->student2 = $gen->create_user();
        $gen->enrol_user($this->student1->id, $this->course->id, 'student');
        $gen->enrol_user($this->student2->id, $this->course->id, 'student');

        $json = json_encode(['tree' => ['nodes' => [], 'edges' => []], 'modules' => null]);
        $this->lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP 569',
            'description' => 'desc',
            'image' => '',
            'json' => $json,
            'visibility' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
        ]);

        $this->adele = null;
        if ($userlist !== null) {
            $this->adele = $gen->create_module('adele', [
                'course' => $this->course->id,
                'learningpathid' => $this->lpid,
                'view' => 1,
                'userlist' => $userlist,
                'participantslist' => 1,
            ]);
        }

        // Both students on the path. Adding the activity may already have
        // subscribed enrolled users; seed only what is missing (one snapshot
        // per user/path - unique index).
        foreach ([$this->student1, $this->student2] as $student) {
            if (
                !$DB->record_exists('local_adele_path_user', [
                'user_id' => $student->id,
                'learning_path_id' => $this->lpid,
                ])
            ) {
                $DB->insert_record('local_adele_path_user', [
                    'user_id' => $student->id,
                    'course_id' => $this->course->id,
                    'learning_path_id' => $this->lpid,
                    'status' => 'active',
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'createdby' => 2,
                    'json' => $json,
                ]);
            }
        }
    }

    /**
     * Call the web service the way the SPA does.
     *
     * @param \stdClass $caller Logged-in user.
     * @param int $contextid Context id the SPA passes (module or course).
     * @param int|null $useridparam The (untrusted) userid parameter; defaults to the caller.
     * @return array
     */
    private function fetch(\stdClass $caller, int $contextid, ?int $useridparam = null): array {
        $this->setUser($caller);
        return get_lp_user_path_relations::execute(
            $useridparam ?? (int) $caller->id,
            $this->lpid,
            $contextid
        );
    }

    /**
     * The ticket's scenario: 'Only own results' set, yet a student received the
     * complete roster. They must get exactly their own row - and passing
     * another user's id as the userid parameter must not leak that user's row
     * either (the parameter is untrusted client input).
     *
     * @return void
     */
    public function test_student_gets_only_their_own_row_with_own_results_setting(): void {
        $this->resetAfterTest(true);
        $this->build_world(2);
        $modulecontextid = context_module::instance($this->adele->cmid)->id;

        $relations = $this->fetch($this->student1, $modulecontextid);
        $this->assertCount(1, $relations, 'Only own results: the student must get exactly their own row (#569).');
        $this->assertSame((int) $this->student1->id, (int) $relations[0]['id']);
        $this->assertArrayHasKey('rank', $relations[0], 'The own row keeps its (true) rank.');

        // Spoofing the userid parameter must not return someone else's results.
        $spoofed = $this->fetch($this->student1, $modulecontextid, (int) $this->student2->id);
        $this->assertCount(1, $spoofed);
        $this->assertSame((int) $this->student1->id, (int) $spoofed[0]['id']);
    }

    /**
     * With 'Results of all other participants' (userlist = 1) the full
     * leaderboard is intended and stays available to students.
     *
     * @return void
     */
    public function test_all_results_setting_returns_the_full_roster(): void {
        $this->resetAfterTest(true);
        $this->build_world(1);
        $modulecontextid = context_module::instance($this->adele->cmid)->id;

        $relations = $this->fetch($this->student1, $modulecontextid);
        $this->assertCount(2, $relations);
    }

    /**
     * Teachers (local/adele:teacheredit, per #431 granted to editing teachers)
     * always see the full roster - the setting restricts participants only.
     *
     * @return void
     */
    public function test_teachers_keep_the_full_roster(): void {
        $this->resetAfterTest(true);
        $this->build_world(2);
        $modulecontextid = context_module::instance($this->adele->cmid)->id;

        $teacher = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');

        // Enrolling into a course with an adele activity auto-subscribes the new
        // user (observer), so the teacher may appear as a third row; what
        // matters is that BOTH students' results stay visible to the teacher.
        $ids = array_map('intval', array_column($this->fetch($teacher, $modulecontextid), 'id'));
        $this->assertContains((int) $this->student1->id, $ids);
        $this->assertContains((int) $this->student2->id, $ids);

        $this->setAdminUser();
        global $USER;
        $relations = get_lp_user_path_relations::execute((int) $USER->id, $this->lpid, $modulecontextid);
        $ids = array_map('intval', array_column($relations, 'id'));
        $this->assertContains((int) $this->student1->id, $ids);
        $this->assertContains((int) $this->student2->id, $ids);
    }

    /**
     * Called with a COURSE context (no specific activity), the strictest
     * embedding of this path in the course wins: any instance set to 'Only own
     * results' filters the roster for students.
     *
     * @return void
     */
    public function test_course_context_enforces_the_strictest_embedding(): void {
        $this->resetAfterTest(true);
        $this->build_world(2);
        $coursecontextid = context_course::instance($this->course->id)->id;

        $relations = $this->fetch($this->student1, $coursecontextid);
        $this->assertCount(1, $relations);
        $this->assertSame((int) $this->student1->id, (int) $relations[0]['id']);
    }

    /**
     * A path that the course does not embed is none of the course's business:
     * being enrolled there gives no right to its roster. (This used to return
     * the complete roster - "no setting to enforce" - and thereby let every
     * learner step around 'Only own results' through the context of any other
     * course they were enrolled in.)
     *
     * @return void
     */
    public function test_unembedded_path_has_no_roster_for_participants(): void {
        $this->resetAfterTest(true);
        $this->build_world(null);
        $coursecontextid = context_course::instance($this->course->id)->id;

        $this->expectException(\required_capability_exception::class);
        $this->fetch($this->student1, $coursecontextid);
    }
}
