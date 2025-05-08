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
 * @package    local_adele
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use local_adele\learning_path_editors;
use advanced_testcase;

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
final class learning_path_editors_test extends advanced_testcase {
    /**
     * Test the get_editors method.
     */
    public function test_get_editors(): void {
        global $DB;
        $DB = $this->createMock(\moodle_database::class);
        $lpid = 15;

        // Mock the get_records_sql method to return a predefined set of records.
        $DB->expects($this->once())
            ->method('get_records_sql')
            ->with($this->anything(), ['lpid' => $lpid])
            ->willReturn([
                (object) ['id' => 1, 'email' => 'editor1@example.com', 'firstname' => 'Editor1', 'lastname' => 'Lastname1'],
                (object) ['id' => 2, 'email' => 'editor2@example.com', 'firstname' => 'Editor2', 'lastname' => 'Lastname2'],
            ]);

        // Replace the global $DB with our mock.
        $this->set_global_db($DB);
        // Call the method under test.
        $result = learning_path_editors::get_editors($lpid);

        // Assert the results.
        $this->assertCount(2, $result);
        $this->assertEquals('editor1@example.com', $result[0]['email']);
        $this->assertEquals('Editor2', $result[1]['firstname']);
    }
    /**
     * Test the create_editors method.
     */
    public function test_create_editors() {
        global $DB;
        $userid = 10;
        $learningpathid = 20;
        $DB = $this->createMock(\moodle_database::class);

        $DB->expects($this->once())
            ->method('insert_record')
            ->with('local_adele_lp_editors', $this->callback(function ($data) use ($userid, $learningpathid) {
                return $data->userid === $userid && $data->learningpathid === $learningpathid;
            }))
            ->willReturn(true);  // Simulate a successful insert.

        $this->set_global_db($DB);
        $result = learning_path_editors::create_editors($learningpathid, $userid);
        $this->assertTrue($result['success']);
    }

    /**
     * Test the remove_editors method.
     */
    public function test_remove_editors(): void {
        global $DB;
        $DB = $this->createMock(\moodle_database::class);
        $lpid = 10;
        $userid = 15;

        $DB->expects($this->once())
            ->method('delete_records')
            ->with('local_adele_lp_editors', ['learningpathid' => $lpid, 'userid' => $userid]);

        $this->set_global_db($DB);

        $result = learning_path_editors::remove_editors($lpid, $userid);
        $this->assertTrue($result['success']);
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
