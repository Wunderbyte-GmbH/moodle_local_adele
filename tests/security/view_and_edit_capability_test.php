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
use context_module;
use context_system;
use local_adele\external\get_lp_user_path_relation;
use local_adele\external\get_lp_user_path_relations;
use local_adele\external\save_lp_user_path_relation;
use local_adele\helper\capability_migration;

/**
 * Seeing a learner's path and changing it are two rights (mod_adele #39).
 *
 * The teacher without editing rights sees every learner of the own course and
 * each one's path (local/adele:viewparticipants) - and changes nothing
 * (local/adele:teacheredit). Measured before: that teacher's save was
 * accepted, through local/adele:teacheredit and through local/adele:canmanage
 * in the course, both of which the 'teacher' archetype held.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\external\get_lp_user_path_relation
 * @covers \local_adele\external\get_lp_user_path_relations
 * @covers \local_adele\external\save_lp_user_path_relation
 * @covers \local_adele\learning_paths::can_view_participants
 * @covers \local_adele\helper\capability_migration
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class view_and_edit_capability_test extends advanced_testcase {
    /** @var \stdClass The course embedding the path. */
    private $course;
    /** @var int The learning path. */
    private $lpid;
    /** @var int The context of the embedding activity. */
    private $contextid;
    /** @var \stdClass A learner. */
    private $learner;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Path', 'json' => '{"tree":{"nodes":[]}}', 'visibility' => 1, 'createdby' => 2,
        ]);
        $activity = $gen->create_module('adele', [
            'course' => $this->course->id,
            'learningpathid' => $this->lpid,
            'view' => 1,
            'userlist' => 2,
            'participantslist' => '1',
        ]);
        $this->contextid = context_module::instance($activity->cmid)->id;
        $this->learner = $gen->create_and_enrol($this->course, 'student');
        if (!$DB->record_exists('local_adele_path_user', ['user_id' => $this->learner->id, 'learning_path_id' => $this->lpid])) {
            $DB->insert_record('local_adele_path_user', (object) [
                'user_id' => $this->learner->id,
                'course_id' => 0,
                'learning_path_id' => $this->lpid,
                'status' => 'active',
                'json' => '{"tree":{"nodes":[]}}',
                'createdby' => $this->learner->id,
            ]);
        }
    }

    /**
     * Change the learner's path the way the path view does.
     *
     * @return array
     */
    private function save(): array {
        global $USER;
        return save_lp_user_path_relation::execute(
            $this->lpid,
            (int) $USER->id,
            json_encode([
                'route' => ['learningpathId' => $this->lpid, 'userId' => (int) $this->learner->id],
                'nodes' => [],
            ]),
            $this->contextid
        );
    }

    /**
     * The archetypes: the teacher without editing rights may see, the teacher
     * with editing rights may see and change.
     */
    public function test_archetype_defaults(): void {
        $nonediting = get_default_capabilities('teacher');
        $this->assertArrayHasKey('local/adele:viewparticipants', $nonediting);
        $this->assertArrayNotHasKey('local/adele:teacheredit', $nonediting);

        $editing = get_default_capabilities('editingteacher');
        $this->assertArrayHasKey('local/adele:viewparticipants', $editing);
        $this->assertArrayHasKey('local/adele:teacheredit', $editing);
    }

    /**
     * The teacher without editing rights reads the roster - all of it, also
     * under 'Only own results' - and a learner's path.
     */
    public function test_nonediting_teacher_sees_everything(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->setUser($teacher);

        $ids = array_map('intval', array_column(
            get_lp_user_path_relations::execute((int) $teacher->id, $this->lpid, $this->contextid),
            'id'
        ));
        $this->assertContains((int) $this->learner->id, $ids);
        $one = get_lp_user_path_relation::execute($this->lpid, (int) $this->learner->id, $this->contextid);
        $this->assertSame((int) $this->learner->id, (int) $one['user_id']);
    }

    /**
     * ... and changes nothing.
     */
    public function test_nonediting_teacher_cannot_change_a_learner_path(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        $this->save();
    }

    /**
     * No regression: the teacher with editing rights changes a learner's path.
     */
    public function test_editing_teacher_changes_a_learner_path(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        $this->assertTrue($this->save()['success']);
    }

    /**
     * Existing sites: the upgrade takes local/adele:teacheredit from the roles
     * of the 'teacher' archetype - the site-wide grant the old default put
     * there - and from nobody else.
     */
    public function test_upgrade_takes_teacheredit_from_nonediting_teacher_roles(): void {
        global $DB;
        $systemcontext = context_system::instance();
        $nonediting = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $editing = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        // The state an existing site is in.
        assign_capability('local/adele:teacheredit', CAP_ALLOW, $nonediting, $systemcontext->id, true);

        $changed = capability_migration::remove_teacheredit_from_nonediting_teachers();

        $this->assertSame([$nonediting], $changed);
        $granted = static function (int $roleid) use ($DB, $systemcontext): bool {
            return $DB->record_exists('role_capabilities', [
                'roleid' => $roleid,
                'contextid' => $systemcontext->id,
                'capability' => 'local/adele:teacheredit',
                'permission' => CAP_ALLOW,
            ]);
        };
        $this->assertFalse($granted($nonediting));
        $this->assertTrue($granted($editing), 'The teacher with editing rights keeps the right to change.');
        $this->assertSame([], capability_migration::remove_teacheredit_from_nonediting_teachers(), 'Idempotent.');
    }
}
