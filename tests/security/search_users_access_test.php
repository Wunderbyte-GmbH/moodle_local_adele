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
use local_adele\external\search_users;

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Security test for #464 H1: the user-search web service must not be a
 * world-readable directory dump.
 *
 * The external class require_once()s the legacy lib/externallib.php, which
 * demands process isolation under PHPUnit.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @covers \local_adele\external\search_users
 */
final class search_users_access_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A plain user with no learning-path editor access must be denied — previously
     * any authenticated user could enumerate the whole {user} table incl. emails.
     */
    public function test_non_editor_is_denied(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        search_users::execute('a');
    }

    /**
     * A manager/editor (here: admin) can still search users — no product regression
     * (the search powers the "manage learning-path editors" UI).
     */
    public function test_editor_can_search(): void {
        $this->getDataGenerator()->create_user(['firstname' => 'Findme', 'lastname' => 'Person']);
        $this->setAdminUser();

        $result = search_users::execute('Findme');

        $this->assertArrayHasKey('list', $result);
        $names = array_map(fn($u) => $u->firstname, $result['list']);
        $this->assertContains('Findme', $names);
    }

    /**
     * A normal editor (an lp_editors member, NOT a manager) can still search — this
     * is the real-world caller of the editor UI, so it must not regress.
     */
    public function test_path_editor_can_search(): void {
        global $DB;
        $editor = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_adele_lp_editors', (object) ['userid' => $editor->id, 'learningpathid' => 1]);
        $this->getDataGenerator()->create_user(['firstname' => 'Findme', 'lastname' => 'Person']);
        $this->setUser($editor);

        $result = search_users::execute('Findme');

        $this->assertArrayHasKey('list', $result);
        $this->assertContains('Findme', array_map(fn($u) => $u->firstname, $result['list']));
    }
}
