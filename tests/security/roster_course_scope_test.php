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
use local_adele\external\save_lp_user_path_relation;

/**
 * The roster of an embedding belongs to its course (#588).
 *
 * One learning path, embedded in two courses, a learner in each. The roster
 * used to be the path's: the teacher of one course - and, with 'results of all
 * other participants', its learners - saw the learners of the other course as
 * well. It is now the intersection of course enrolment and path subscription,
 * ranked within that set; the same boundary holds for reading and changing a
 * single learner's path through a course right.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\external\get_lp_user_path_relation
 * @covers \local_adele\external\get_lp_user_path_relations
 * @covers \local_adele\external\save_lp_user_path_relation
 * @covers \local_adele\learning_paths::get_learning_user_relations
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class roster_course_scope_test extends advanced_testcase {
    /** @var \stdClass Course X. */
    private $coursex;
    /** @var \stdClass Course Y. */
    private $coursey;
    /** @var \stdClass The embedding in course X ('all results'). */
    private $activityx;
    /** @var \stdClass The embedding in course Y ('all results'). */
    private $activityy;
    /** @var int The learning path. */
    private $lpid;
    /** @var \stdClass Learner of course X. */
    private $xavier;
    /** @var \stdClass Learner of course Y - further ahead than Xavier. */
    private $yara;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->coursex = $gen->create_course();
        $this->coursey = $gen->create_course();
        $this->lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Shared path', 'json' => '{"tree":{"nodes":[]}}', 'visibility' => 1, 'createdby' => 2,
        ]);
        $this->activityx = $this->embed($this->coursex);
        $this->activityy = $this->embed($this->coursey);
        $this->xavier = $gen->create_and_enrol($this->coursex, 'student');
        $this->yara = $gen->create_and_enrol($this->coursey, 'student');
        $this->snapshot((int) $this->xavier->id, false);
        $this->snapshot((int) $this->yara->id, true);
    }

    /**
     * Embed the path into a course, results visible to all participants.
     *
     * @param \stdClass $course The course.
     * @return \stdClass The activity.
     */
    private function embed(\stdClass $course): \stdClass {
        return $this->getDataGenerator()->create_module('adele', [
            'course' => $course->id,
            'learningpathid' => $this->lpid,
            'view' => 1,
            'userlist' => 1,
            'participantslist' => '1',
        ]);
    }

    /**
     * Give a learner a snapshot on the path: one node, completed or not.
     *
     * @param int $userid The learner.
     * @param bool $completed Whether the node is completed.
     */
    private function snapshot(int $userid, bool $completed): void {
        global $DB;
        $json = json_encode([
            // No tree: nothing for a recomputation to touch; the progress
            // then is completed nodes of all nodes.
            'tree' => ['nodes' => []],
            'user_path_relation' => ['dndnode_1' => ['completionnode' => ['valid' => $completed]]],
        ]);
        $existing = $DB->get_record('local_adele_path_user', ['user_id' => $userid, 'learning_path_id' => $this->lpid]);
        if ($existing) {
            $DB->set_field('local_adele_path_user', 'json', $json, ['id' => $existing->id]);
            return;
        }
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $userid,
            'course_id' => 0,
            'learning_path_id' => $this->lpid,
            'status' => 'active',
            'json' => $json,
            'createdby' => $userid,
        ]);
    }

    /**
     * The roster as "id => rank".
     *
     * @param int $contextid The context the client passes.
     * @return array
     */
    private function roster(int $contextid): array {
        global $USER;
        $rows = get_lp_user_path_relations::execute((int) $USER->id, $this->lpid, $contextid);
        $ranks = [];
        foreach ($rows as $row) {
            $ranks[(int) $row['id']] = (int) $row['rank'];
        }
        ksort($ranks);
        return $ranks;
    }

    /**
     * The teacher of course X gets the learner of X - ranked first, although
     * the learner of Y is further ahead on the same path.
     */
    public function test_teacher_gets_the_learners_of_the_own_course_only(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->coursex, 'editingteacher');
        $this->setUser($teacher);
        $roster = $this->roster(context_module::instance($this->activityx->cmid)->id);

        $this->assertArrayNotHasKey((int) $this->yara->id, $roster, 'The learner of course Y is none of course X.');
        $this->assertSame(1, $roster[(int) $this->xavier->id], 'Ranks are given within the course.');
    }

    /**
     * With 'results of all other participants' a learner sees the other
     * participants of THIS course.
     */
    public function test_learner_gets_the_participants_of_the_own_course_only(): void {
        $this->setUser($this->xavier);
        $this->assertSame(
            [(int) $this->xavier->id => 1],
            $this->roster(context_module::instance($this->activityx->cmid)->id)
        );
        $this->setUser($this->yara);
        $this->assertSame(
            [(int) $this->yara->id => 1],
            $this->roster(context_course::instance($this->coursey->id)->id)
        );
    }

    /**
     * Somebody enrolled in both courses belongs to both rosters.
     */
    public function test_learner_of_both_courses_is_in_both_rosters(): void {
        $this->getDataGenerator()->enrol_user($this->yara->id, $this->coursex->id, 'student');
        $this->setAdminUser();

        $inx = $this->roster(context_module::instance($this->activityx->cmid)->id);
        $this->assertSame([(int) $this->xavier->id => 2, (int) $this->yara->id => 1], $inx);
        $iny = $this->roster(context_module::instance($this->activityy->cmid)->id);
        $this->assertSame([(int) $this->yara->id => 1], $iny);
    }

    /**
     * A suspended enrolment still is an enrolment: host mode "hidden" keeps
     * its learners suspended in the host course, and the teacher must see them.
     */
    public function test_suspended_enrolment_stays_in_the_roster(): void {
        global $DB;
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['userid' => $this->xavier->id]);
        $this->setAdminUser();
        $this->assertArrayHasKey(
            (int) $this->xavier->id,
            $this->roster(context_module::instance($this->activityx->cmid)->id)
        );
    }

    /**
     * Authority over the path, asked outside any course: the path's roster,
     * ranked across the courses.
     */
    public function test_path_authority_outside_a_course_gets_the_whole_path(): void {
        $this->setAdminUser();
        $this->assertSame(
            [(int) $this->xavier->id => 2, (int) $this->yara->id => 1],
            $this->roster(context_system::instance()->id)
        );
    }

    /**
     * The teacher of course X does not read the path of the learner of Y.
     */
    public function test_teacher_cannot_read_a_learner_of_another_course(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->coursex, 'editingteacher');
        $this->setUser($teacher);
        $contextid = context_module::instance($this->activityx->cmid)->id;

        $own = get_lp_user_path_relation::execute($this->lpid, (int) $this->xavier->id, $contextid);
        $this->assertSame((int) $this->xavier->id, (int) $own['user_id']);

        $this->expectException(\required_capability_exception::class);
        get_lp_user_path_relation::execute($this->lpid, (int) $this->yara->id, $contextid);
    }

    /**
     * ... and does not change it.
     */
    public function test_teacher_cannot_change_a_learner_of_another_course(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->coursex, 'editingteacher');
        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        save_lp_user_path_relation::execute(
            $this->lpid,
            (int) $teacher->id,
            json_encode([
                'route' => ['learningpathId' => $this->lpid, 'userId' => (int) $this->yara->id],
                'nodes' => [],
            ]),
            context_module::instance($this->activityx->cmid)->id
        );
    }
}
