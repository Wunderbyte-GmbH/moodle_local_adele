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
use local_adele\external\get_lp_user_path_relation;
use local_adele\external\get_lp_user_path_relations;

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Security test for #464 H2: the per-user path getters must not be an IDOR that
 * lets any authenticated user read another user's progress + email.
 *
 * The external classes require_once() the legacy lib/externallib.php, which
 * demands process isolation under PHPUnit.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @covers \local_adele\external\get_lp_user_path_relation
 * @covers \local_adele\external\get_lp_user_path_relations
 */
final class user_path_relation_access_test extends advanced_testcase {

    /** @var \stdClass The course the path is delivered in. */
    private $course;
    /** @var \context_course */
    private $context;
    /** @var int The learning-path id. */
    private $lpid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        global $DB;

        $this->course = $this->getDataGenerator()->create_course();
        $this->context = \context_course::instance($this->course->id);

        $this->lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Path', 'json' => '{"tree":{"nodes":[]}}', 'visibility' => 1, 'createdby' => 2,
        ]);
    }

    /**
     * Insert an active path_user snapshot for the given user.
     */
    private function make_relation(int $userid): void {
        global $DB;
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $userid,
            'course_id' => $this->course->id,
            'learning_path_id' => $this->lpid,
            'status' => 'active',
            'json' => '{"tree":{"nodes":[]}}',
            'createdby' => $userid,
        ]);
    }

    // ---------------------------------------------------------------- singular.

    /**
     * IDOR core: a student must not be able to read ANOTHER student's path
     * (which exposes their email + full progress tree).
     */
    public function test_other_student_cannot_read_user_path(): void {
        $victim = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $attacker = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->make_relation($victim->id);

        $this->setUser($attacker);
        $this->expectException(\required_capability_exception::class);
        get_lp_user_path_relation::execute($this->lpid, $victim->id, $this->context->id);
    }

    /**
     * A user may always read their OWN path (StudentView.vue, userId == own).
     */
    public function test_owner_can_read_own_path(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->make_relation($student->id);

        $this->setUser($student);
        $result = get_lp_user_path_relation::execute($this->lpid, $student->id, $this->context->id);

        $this->assertEquals($student->id, $result['user_id']);
        $this->assertEquals($student->email, $result['email']);
    }

    /**
     * A course teacher may read a student's path (UserPath.vue) — no regression.
     */
    public function test_teacher_can_read_student_path(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->make_relation($student->id);

        $this->setUser($teacher);
        $result = get_lp_user_path_relation::execute($this->lpid, $student->id, $this->context->id);

        $this->assertEquals($student->id, $result['user_id']);
    }

    /**
     * A path editor (lp_editors member, not a course teacher) may read a student's
     * path — check_access() is the same gate the editor UI uses; no regression.
     */
    public function test_path_editor_can_read_user_path(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $editor = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_adele_lp_editors', (object) ['userid' => $editor->id, 'learningpathid' => $this->lpid]);
        $this->make_relation($student->id);

        $this->setUser($editor);
        $result = get_lp_user_path_relation::execute($this->lpid, $student->id, $this->context->id);

        $this->assertEquals($student->id, $result['user_id']);
    }

    // ------------------------------------------------------------------ plural.

    /**
     * A user with no relationship to the course must not read its leaderboard.
     */
    public function test_unrelated_user_cannot_read_leaderboard(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->make_relation($student->id);
        $outsider = $this->getDataGenerator()->create_user();

        $this->setUser($outsider);
        $this->expectException(\required_capability_exception::class);
        get_lp_user_path_relations::execute($outsider->id, $this->lpid, $this->context->id);
    }

    /**
     * An enrolled participant can still read the leaderboard (StudentView.vue).
     */
    public function test_enrolled_participant_can_read_leaderboard(): void {
        $a = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $b = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->make_relation($a->id);
        $this->make_relation($b->id);

        $this->setUser($a);
        $result = get_lp_user_path_relations::execute($a->id, $this->lpid, $this->context->id);

        $this->assertCount(2, $result);
    }

    /**
     * A teacher can still read the leaderboard (TeacherView.vue) — no regression.
     */
    public function test_teacher_can_read_leaderboard(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->make_relation($student->id);

        $this->setUser($teacher);
        $result = get_lp_user_path_relations::execute($teacher->id, $this->lpid, $this->context->id);

        $this->assertCount(1, $result);
    }
}
