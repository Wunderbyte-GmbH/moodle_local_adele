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

namespace local_adele;

use advanced_testcase;
use context_course;
use context_module;
use local_adele\external\get_lp_user_path_relations;

/**
 * The teachers of a course are not the learners of its learning paths (#587).
 *
 * Every enrolment into a course that embeds a path used to subscribe to it,
 * whatever the role: teachers stood on the roster with 0 % and a rank, got a
 * learner path and were enrolled into the starting courses. A learner now is
 * who takes part in the course - local/adele:participate through a role of
 * their own.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\enrol_state::is_participant
 * @covers \local_adele\enrol_state::get_nonparticipant_userids
 * @covers \local_adele\enrollment::enrolled
 * @covers \local_adele\enrollment::role_assigned_in_course
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class issue587_participants_test extends advanced_testcase {
    /** @var \stdClass The course embedding the path. */
    private $course;
    /** @var int The learning path. */
    private $lpid;
    /** @var \stdClass The embedding activity. */
    private $activity;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Path', 'json' => '{"tree":{"nodes":[]}}', 'visibility' => 1, 'createdby' => 2,
        ]);
        $this->activity = $this->getDataGenerator()->create_module('adele', [
            'course' => $this->course->id,
            'learningpathid' => $this->lpid,
            'view' => 1,
            'userlist' => 1,
            'participantslist' => '1',
        ]);
    }

    /**
     * Whether a user is a learner of the path.
     *
     * @param int $userid The user.
     * @return bool
     */
    private function subscribed(int $userid): bool {
        global $DB;
        return $DB->record_exists('local_adele_path_user', [
            'user_id' => $userid,
            'learning_path_id' => $this->lpid,
            'status' => 'active',
        ]);
    }

    /**
     * The id of a role.
     *
     * @param string $shortname Its short name.
     * @return int
     */
    private function role(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * Enrolled as a participant: a learner of the path. Enrolled as a
     * teacher, with or without editing rights: not.
     */
    public function test_participants_are_subscribed_teachers_are_not(): void {
        $gen = $this->getDataGenerator();
        $student = $gen->create_and_enrol($this->course, 'student');
        $teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $tutor = $gen->create_and_enrol($this->course, 'teacher');

        $this->assertTrue($this->subscribed((int) $student->id));
        $this->assertFalse($this->subscribed((int) $teacher->id), 'A teacher of the course is not its learner.');
        $this->assertFalse($this->subscribed((int) $tutor->id), 'Nor is a teacher without editing rights.');
    }

    /**
     * A teacher who becomes a participant - a role change, no new enrolment -
     * becomes a learner of the path.
     */
    public function test_teacher_who_becomes_participant_is_subscribed(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->assertFalse($this->subscribed((int) $teacher->id));

        role_assign($this->role('student'), $teacher->id, context_course::instance($this->course->id)->id);

        $this->assertTrue($this->subscribed((int) $teacher->id), 'Teacher and participant: a participant.');
    }

    /**
     * A role somebody holds without being enrolled subscribes nobody.
     */
    public function test_role_without_enrolment_does_not_subscribe(): void {
        $user = $this->getDataGenerator()->create_user();
        role_assign($this->role('student'), $user->id, context_course::instance($this->course->id)->id);
        $this->assertFalse($this->subscribed((int) $user->id));
    }

    /**
     * What "takes part" means: the capability, through a role of one's own.
     */
    public function test_is_participant(): void {
        $gen = $this->getDataGenerator();
        $courseid = (int) $this->course->id;
        $context = context_course::instance($courseid);

        $student = $gen->create_and_enrol($this->course, 'student');
        $teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->assertTrue(enrol_state::is_participant($courseid, (int) $student->id));
        $this->assertFalse(enrol_state::is_participant($courseid, (int) $teacher->id));

        // The student role enrol_adele gives a learner in a node or host
        // course is ADELE's doing, not taking part by own right.
        $carried = $gen->create_user();
        role_assign($this->role('student'), $carried->id, $context->id, 'enrol_adele', 1);
        $this->assertFalse(enrol_state::is_participant($courseid, (int) $carried->id));

        // A role that prohibits the capability settles it.
        $prohibiting = $gen->create_role();
        assign_capability(enrol_state::PARTICIPATE, CAP_PROHIBIT, $prohibiting, $context->id);
        role_assign($prohibiting, $student->id, $context->id);
        $this->assertFalse(enrol_state::is_participant($courseid, (int) $student->id));
    }

    /**
     * The roster of the course leaves out its teachers - also a teacher who
     * still has a learner path from before.
     */
    public function test_roster_leaves_out_the_teachers(): void {
        global $DB, $USER;
        $gen = $this->getDataGenerator();
        $student = $gen->create_and_enrol($this->course, 'student');
        $teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        // The state of a site before the change: the teacher is a learner.
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $teacher->id,
            'course_id' => 0,
            'learning_path_id' => $this->lpid,
            'status' => 'active',
            'json' => '{"tree":{"nodes":[]}}',
            'createdby' => $teacher->id,
        ]);
        $this->assertSame(
            [(int) $teacher->id],
            enrol_state::get_nonparticipant_userids((int) $this->course->id, [(int) $student->id, (int) $teacher->id])
        );

        $this->setAdminUser();
        $ids = array_map('intval', array_column(
            get_lp_user_path_relations::execute(
                (int) $USER->id,
                $this->lpid,
                context_module::instance($this->activity->cmid)->id
            ),
            'id'
        ));
        $this->assertSame([(int) $student->id], $ids);
    }

    /**
     * Existing sites: besides the 'student' archetype, the site's gradebook
     * roles are given the capability - learners under a role without
     * archetype must not lose their subscriptions.
     */
    public function test_upgrade_gives_participate_to_the_gradebook_roles(): void {
        global $CFG;
        $custom = $this->getDataGenerator()->create_role(['shortname' => 'lernende', 'name' => 'Lernende']);
        $elsewhere = $this->getDataGenerator()->create_role(['shortname' => 'gast', 'name' => 'Gast']);
        $CFG->gradebookroles = $this->role('student') . ',' . $custom;

        $granted = \local_adele\helper\capability_migration::grant_participate_to_gradebook_roles();

        // The student role has the capability from its archetype already.
        $this->assertSame([$custom], $granted);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $custom);
        $this->assertTrue(enrol_state::is_participant((int) $this->course->id, (int) $user->id));
        $this->assertTrue($this->subscribed((int) $user->id));

        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $this->course->id, $elsewhere);
        $this->assertFalse($this->subscribed((int) $other->id), 'A role that is no gradebook role does not take part.');
        $this->assertSame([], \local_adele\helper\capability_migration::grant_participate_to_gradebook_roles(), 'Idempotent.');
    }
}
