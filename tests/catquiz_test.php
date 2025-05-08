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
use stdClass;

/**
 * PHPUnit test case for the 'catquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catquiz_test extends advanced_testcase {
    protected function setUp(): void {
        global $DB;
        parent::setUp();

        // Mock the global $DB object to ensure get_records_sql can be called.
        $DB = $this->getMockBuilder(stdClass::class)
            ->addMethods(['get_records_sql'])
            ->getMock();
    }

    /**
     * Test the get_catquiz_tests function.
     * @covers \local_adele\catquiz::get_catquiz_tests
     */
    public function test_get_catquiz_tests_class_does_not_exist(): void {
        $this->mock_class_exists('local_catquiz\testenvironment', false);

        $result = catquiz::get_catquiz_tests([]);
        $this->assertIsArray($result);
        $this->assertCount(0, $result, 'Expected an empty array when class does not exist');
    }

    /**
     * Test the get_catquiz_tests function.
     * @covers \local_adele\catquiz::get_catquiz_tests
     */
    public function test_get_catquiz_tests_class_exists_with_records(): void {
        global $DB;

        // Check if the class exists in the environment.
        if (!class_exists('local_catquiz\testenvironment')) {
            $this->markTestSkipped('Class local_catquiz\testenvironment does not exist in this environment.');
        }

        // Mock class_exists to return true.
        $this->mock_class_exists('local_catquiz\testenvironment', true);

        $DB->expects($this->once())
            ->method('get_records_sql')
            ->willReturn([
                (object)[
                    'id' => 1,
                    'json' => json_encode(['name' => 'Test Quiz']),
                    'fullname' => 'Course Full Name',
                    'courseid' => 123,
                ],
            ]);

        $testenvironmentmock = $this->getMockBuilder('local_catquiz\testenvironment')
            ->onlyMethods(['get_environments'])
            ->disableOriginalConstructor()
            ->getMock();

        $testenvironmentmock->expects($this->never())
            ->method('get_environments')
            ->with('mod_adaptivequiz', 0, 2, true)
            ->willReturn([
                (object)[
                    'id' => 1,
                    'json' => json_encode(['name' => 'Test Quiz']),
                    'fullname' => 'Course Full Name',
                    'courseid' => 123,
                ],
            ]);

        $result = catquiz::get_catquiz_tests([['course_node_id' => 123]]);

        // Assertions.
        $this->assertIsArray($result);
        $this->assertCount(1, $result, 'Expected one record when the class exists');
        $this->assertEquals('Test Quiz', $result[0]['name'], 'Expected name from decoded JSON');
        $this->assertEquals('Course Full Name', $result[0]['coursename']);
        $this->assertEquals(123, $result[0]['courseid']);
    }

    /**
     * Mock the class_exists function within the local_adele namespace.
     * @param string $classname
     * @param bool $exists
     * @return bool
     */
    protected function mock_class_exists($classname, $exists) {
        if (!function_exists('local_adele\class_exists')) {
            /**
             *  Mock the class_exists return value.
             * @param string $name
             * @return bool
             */
            function class_exists($name) {
                global $classexistsmocks;
                return isset($classexistsmocks[$name]) ? $classexistsmocks[$name] : \class_exists($name);
            }
        }
        global $classexistsmocks;
        $classexistsmocks[$classname] = $exists;
    }

    /**
     * Reset the class_exists mock.
     */
    protected function reset_class_exists_mock() {
        global $classexistsmocks;
        $classexistsmocks = [];
    }
}
