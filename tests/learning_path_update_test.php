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
 * Tests strategy
 *
 * @package    local_catquiz
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use local_adele\learning_path_update;
use advanced_testcase;
use stdClass;


defined('MOODLE_INTERNAL') || die();

/**
 * Tests strategy
 *
 * @package    local_adele
 * @author Jacob Viertel
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_adele
 */
class learning_path_update_test extends advanced_testcase {

    /**
     * @runInSeparateProcess
     * Test the update_visibility method.
     */
    public function test_update_visibility() {
        global $DB;

        $DB = $this->createMock(\moodle_database::class);
        $DB->expects($this->once())
            ->method('update_record')
            ->with(
                $this->equalTo('local_adele_learning_paths'),
                $this->callback(function($data) {
                    // Ensure the data passed to update_record has the correct values.
                    return $data->id === 1 && $data->visibility === true;
                })
            )
            ->willReturn(true);

        $this->set_global_db($DB);

        $result = learning_path_update::update_visiblity(1, true);

        $this->assertArrayHasKey('success', $result);
    }
    /**
     * Helper method to replace the global $DB with a mock.
     *
     * @param \moodle_database $mockdb
     */
    protected function set_global_db($mockdb) {
        global $DB;
        $DB = $mockdb;
    }
}
