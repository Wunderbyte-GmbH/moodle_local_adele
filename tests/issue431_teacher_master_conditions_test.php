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
 * Regression test for GitHub issue #431:
 *   Editing-teachers cannot read the restriction/completion condition palette
 *   (needed to operate "master conditions") unless granted the over-broad
 *   local/adele:canmanage. The dormant local/adele:teacheredit capability is now
 *   granted to the editingteacher archetype and honoured by these read gates.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use context_course;
use local_adele\external\get_restrictions;
use local_adele\external\get_completions;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\external\get_restrictions
 * @covers \local_adele\external\get_completions
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 */
#[RunTestsInSeparateProcesses]
final class issue431_teacher_master_conditions_test extends advanced_testcase {
    /**
     * Enrol a user as an editing-teacher in a fresh course and return
     * [the course context id, the user].
     *
     * @return array
     */
    private function editingteacher_in_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        return [context_course::instance($course->id)->id, $user];
    }

    /**
     * An editing-teacher (WITHOUT local/adele:canmanage) must be able to read the
     * restriction palette - it is what populates the master-condition editor (#431).
     *
     * @return void
     */
    public function test_editingteacher_can_read_restrictions(): void {
        $this->resetAfterTest(true);

        [$contextid] = $this->editingteacher_in_course();

        // Before the fix this threw required_capability_exception (canmanage only).
        $restrictions = get_restrictions::execute($contextid);
        $this->assertIsArray($restrictions);
    }

    /**
     * Same for the completion palette read gate (#431).
     *
     * @return void
     */
    public function test_editingteacher_can_read_completions(): void {
        $this->resetAfterTest(true);

        [$contextid] = $this->editingteacher_in_course();

        $completions = get_completions::execute($contextid);
        $this->assertIsArray($completions);
    }

    /**
     * Guard against over-granting: a plain student (no canmanage, no teacheredit)
     * must STILL be refused the condition palette (#431).
     *
     * @return void
     */
    public function test_student_is_still_blocked(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $contextid = context_course::instance($course->id)->id;

        $this->expectException(\core\exception\required_capability_exception::class);
        get_restrictions::execute($contextid);
    }
}
