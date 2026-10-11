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

namespace local_adele\security;

use advanced_testcase;
use context_course;
use context_module;
use context_system;
use local_adele\external\get_lp_user_path_relation;
use local_adele\external\get_lp_user_path_relations;

/**
 * Learner data and the course the request claims to come from.
 *
 * Both getters take a context id from the client. Rights that come from a
 * course - teaching it, being enrolled in it - used to count in ANY course,
 * so the context of an unrelated course opened the learner data of every
 * path. Found by the E2E chain P (learner data); measured on a running site:
 *
 *  - a participant of a course without any learning path read the complete
 *    roster of a path;
 *  - a learner whose activity says 'Only own results' read the complete roster
 *    through the context of a node course ADELE had enrolled them in;
 *  - a teacher of an unrelated course read the roster and one learner's
 *    snapshot including the e-mail address.
 *
 * The rights now count only in a course that embeds the path.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\external\get_lp_user_path_relation
 * @covers \local_adele\external\get_lp_user_path_relations
 * @covers \local_adele\learning_paths::is_embedded_in_course
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class learner_data_embedding_test extends advanced_testcase {
    /** @var \stdClass The course that embeds the path, with 'Only own results'. */
    private $host;
    /** @var \stdClass A course that embeds nothing. */
    private $other;
    /** @var int The learning path. */
    private $lpid;
    /** @var \stdClass The activity embedding the path. */
    private $activity;
    /** @var \stdClass Learner A. */
    private $alpha;
    /** @var \stdClass Learner B. */
    private $beta;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->host = $gen->create_course();
        $this->other = $gen->create_course();
        $this->lpid = $this->make_path('Path');
        $this->activity = $this->embed($this->host, $this->lpid, 2);
        $this->alpha = $gen->create_and_enrol($this->host, 'student');
        $this->beta = $gen->create_and_enrol($this->host, 'student');
        $this->make_relation($this->alpha->id, $this->lpid);
        $this->make_relation($this->beta->id, $this->lpid);
    }

    /**
     * A learning path.
     *
     * @param string $name Its name.
     * @return int
     */
    private function make_path(string $name): int {
        global $DB;
        return (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => $name, 'json' => '{"tree":{"nodes":[]}}', 'visibility' => 1, 'createdby' => 2,
        ]);
    }

    /**
     * Embed a path into a course.
     *
     * @param \stdClass $course The course.
     * @param int $lpid The path.
     * @param int $userlist 2 = only own results, 1 = all results.
     * @return \stdClass The activity.
     */
    private function embed(\stdClass $course, int $lpid, int $userlist): \stdClass {
        return $this->getDataGenerator()->create_module('adele', [
            'course' => $course->id,
            'learningpathid' => $lpid,
            'view' => 1,
            'userlist' => $userlist,
            'participantslist' => '0',
        ]);
    }

    /**
     * An active snapshot of a learner on a path.
     *
     * @param int $userid The learner.
     * @param int $lpid The path.
     */
    private function make_relation(int $userid, int $lpid): void {
        global $DB;
        // Enrolling into a course that embeds the path subscribes already.
        if ($DB->record_exists('local_adele_path_user', ['user_id' => $userid, 'learning_path_id' => $lpid])) {
            return;
        }
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $userid,
            'course_id' => $this->host->id,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'json' => '{"tree":{"nodes":[]}}',
            'createdby' => $userid,
        ]);
    }

    /**
     * The ids a roster call returns.
     *
     * @param int $contextid The context the client passes.
     * @return int[]
     */
    private function roster(int $contextid): array {
        global $USER;
        $rows = get_lp_user_path_relations::execute((int) $USER->id, $this->lpid, $contextid);
        $ids = array_map('intval', array_column($rows, 'id'));
        sort($ids);
        return $ids;
    }

    /**
     * Somebody who is merely enrolled in a course without any learning path
     * must not read the roster by passing the context of that course.
     */
    public function test_participant_of_another_course_cannot_read_roster(): void {
        $outsider = $this->getDataGenerator()->create_and_enrol($this->other, 'student');
        $this->setUser($outsider);
        $this->expectException(\required_capability_exception::class);
        $this->roster(context_course::instance($this->other->id)->id);
    }

    /**
     * 'Only own results' holds whatever context the learner passes: the
     * context of another course they are enrolled in (a node course, say)
     * must not hand them the complete roster.
     */
    public function test_own_results_cannot_be_widened_through_another_course(): void {
        $this->getDataGenerator()->enrol_user($this->alpha->id, $this->other->id, 'student');
        $this->setUser($this->alpha);

        // The legitimate call: exactly the own row.
        $this->assertSame(
            [(int) $this->alpha->id],
            $this->roster(context_module::instance($this->activity->cmid)->id)
        );

        $this->expectException(\required_capability_exception::class);
        $this->roster(context_course::instance($this->other->id)->id);
    }

    /**
     * Another ADELE activity of the same course, showing a DIFFERENT path with
     * 'all results', does not speak for this path.
     */
    public function test_another_activity_does_not_lift_own_results(): void {
        $second = $this->embed($this->host, $this->make_path('Second path'), 1);
        $this->setUser($this->alpha);
        $this->assertSame(
            [(int) $this->alpha->id],
            $this->roster(context_module::instance($second->cmid)->id)
        );
    }

    /**
     * A teacher of an unrelated course must not read the roster of the path.
     */
    public function test_teacher_of_another_course_cannot_read_roster(): void {
        $foreign = $this->getDataGenerator()->create_and_enrol($this->other, 'teacher');
        $this->setUser($foreign);
        $this->expectException(\required_capability_exception::class);
        $this->roster(context_course::instance($this->other->id)->id);
    }

    /**
     * A teacher of an unrelated course must not read one learner's snapshot
     * (which carries the e-mail address) either.
     */
    public function test_teacher_of_another_course_cannot_read_a_learner(): void {
        $foreign = $this->getDataGenerator()->create_and_enrol($this->other, 'teacher');
        $this->setUser($foreign);
        $this->expectException(\required_capability_exception::class);
        get_lp_user_path_relation::execute(
            $this->lpid,
            (int) $this->beta->id,
            context_course::instance($this->other->id)->id
        );
    }

    /**
     * No regression: the teacher of the embedding course reads the roster and
     * a learner, whatever the result visibility says.
     */
    public function test_teacher_of_the_embedding_course_reads_roster_and_learner(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->host, 'teacher');
        $this->setUser($teacher);
        $modulecontextid = context_module::instance($this->activity->cmid)->id;

        // The teacher's enrolment subscribed the teacher as well; what matters
        // is that both learners are there.
        $roster = $this->roster($modulecontextid);
        $this->assertContains((int) $this->alpha->id, $roster);
        $this->assertContains((int) $this->beta->id, $roster);
        $one = get_lp_user_path_relation::execute($this->lpid, (int) $this->beta->id, $modulecontextid);
        $this->assertSame((int) $this->beta->id, (int) $one['user_id']);
    }

    /**
     * No regression: a learner reads their own snapshot from wherever they
     * open the path.
     */
    public function test_owner_reads_own_snapshot_in_any_context(): void {
        $this->getDataGenerator()->enrol_user($this->alpha->id, $this->other->id, 'student');
        $this->setUser($this->alpha);
        $one = get_lp_user_path_relation::execute(
            $this->lpid,
            (int) $this->alpha->id,
            context_course::instance($this->other->id)->id
        );
        $this->assertSame((int) $this->alpha->id, (int) $one['user_id']);
    }

    /**
     * A context outside any course carries no course rights - and is no
     * coding error: a manager, whose authority is about paths, gets the
     * roster; a learner is turned away.
     */
    public function test_system_context_serves_path_authority_only(): void {
        $systemcontextid = context_system::instance()->id;

        $this->setAdminUser();
        $this->assertSame([(int) $this->alpha->id, (int) $this->beta->id], $this->roster($systemcontextid));

        $this->setUser($this->alpha);
        $this->expectException(\required_capability_exception::class);
        $this->roster($systemcontextid);
    }
}
