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

namespace local_adele\course_restriction\conditions;

use advanced_testcase;
use local_adele\course_restriction\conditions\disabled\parent_node_completed;

/**
 * PHPUnit test case for the 'parent_node_completed' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parent_node_completed_test extends advanced_testcase {

    /**
     * Set up function to reset all database changes after each test.
     */
    protected function setUp(): void {
        // Reset the database after each test.
        $this->resetAfterTest();
    }

    /**
     * Test the get_description function.
     * @covers \local_adele\course_restriction\conditions\disabled\parent_node_completed::get_description
     */
    public function test_get_description() {
        $parentnodecompleted = new parent_node_completed();
        $description = $parentnodecompleted->get_description();

        $this->assertIsArray($description);
        $this->assertArrayHasKey('id', $description);
        $this->assertArrayHasKey('name', $description);
        $this->assertArrayHasKey('description', $description);
        $this->assertEquals($parentnodecompleted->id, $description['id']);
        $this->assertEquals($parentnodecompleted->label, $description['label']);
    }

    /**
     * Test the get_restriction_status function.
     * @covers \local_adele\course_restriction\conditions\parent_node_completed::get_restriction_status
     */
    public function test_get_restriction_status() {
        $parentnodecompleted = new parent_node_completed();

        // Test with valid start and end date.
        $userpath = (object)[
            'json' => [
                'tree' => [
                    'nodes' => [
                        [
                            'id' => 1,
                            'data' => [
                                'fullname' => 'Parent Course 1',
                                'completion' => [
                                    'feedback' => ['status' => 'completed'],
                                ],
                            ],
                        ],
                        [
                            'id' => 2,
                            'data' => [
                                'fullname' => 'Parent Course 2',
                                'completion' => [
                                    'feedback' => ['status' => 'incomplete'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $node = [
            'parentCourse' => [1, 2],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 12,
                        'data' => [
                            'label' => 'parent_node_completed',
                        ],
                    ],
                ],
            ],
        ];
        $status = $parentnodecompleted->get_restriction_status($node, $userpath);
        $this->assertArrayHasKey(12, $status);
        $this->assertTrue($status[12]['completed'] !== false);
    }

}
