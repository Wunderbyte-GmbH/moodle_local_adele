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
use local_adele\external\save_learningpath;

/**
 * Creating a learning path without any right is refused the way Moodle
 * refuses - with a required_capability_exception, not with a PHP error
 * (local_adele #594).
 *
 * The external class require_once()s the legacy lib/externallib.php, which
 * demands process isolation under PHPUnit.
 *
 * @package    local_adele
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @covers \local_adele\external\save_learningpath
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class save_learningpath_refusal_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Ask the service to create an empty path.
     *
     * @return array
     */
    private function create(): array {
        return save_learningpath::execute(
            0,
            0,
            'Unerlaubt',
            'x',
            json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            \context_system::instance()->id
        );
    }

    /**
     * Nobody in particular: refused, with the reason, and nothing is created.
     */
    public function test_person_without_rights_is_refused_with_the_reason(): void {
        global $DB;
        $this->setUser($this->getDataGenerator()->create_user());
        $before = $DB->count_records('local_adele_learning_paths');
        try {
            $this->create();
            $this->fail('creating a path must be refused');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertSame($before, $DB->count_records('local_adele_learning_paths'));
    }

    /**
     * The other side: an administrator creates the path.
     */
    public function test_administrator_creates_the_path(): void {
        global $DB;
        $this->setAdminUser();
        $before = $DB->count_records('local_adele_learning_paths');
        $this->create();
        $this->assertSame($before + 1, $DB->count_records('local_adele_learning_paths'));
    }
}
