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

use local_adele\modquiz;
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
class modquiz_test extends advanced_testcase {

    /**
     * Test the get_editors method.
     */
    public function test_get_mod_quizzes() {
        global $DB;
        $DB = $this->createMock(\moodle_database::class);
        $expectedrecords = [
            (object)[
                'id' => 1,
                'course' => 101,
                'name' => 'Quiz 1',
                'coursename' => 'Course 1'
            ],
            (object)[
                'id' => 2,
                'course' => 102,
                'name' => 'Quiz 2',
                'coursename' => 'Course 2'
            ]
        ];
        $DB->expects($this->once())
            ->method('get_records_sql')
            ->with($this->stringContains('SELECT q.id, q.course, q.name, c.fullname as coursename'))
            ->willReturn($expectedrecords);

        $this->set_global_db($DB);
        $result = modquiz::get_mod_quizzes();
        // Define what we expect the result to look like.
        $expectedresult = [
            ['id' => 1, 'course' => 101, 'name' => 'Quiz 1', 'coursename' => 'Course 1'],
            ['id' => 2, 'course' => 102, 'name' => 'Quiz 2', 'coursename' => 'Course 2']
        ];
        $this->assertEquals($expectedresult, $result);
    }

    /**
     * Helper method to set global mock objects.
     *
     * @param string $globalname
     * @param mixed $mockobject
     */
    protected function set_global_db($mockdb) {
        global $DB;
        $DB = $mockdb;
    }
}
