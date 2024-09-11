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

/**
 * PHPUnit test case for the 'specific_course' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class specific_course_test extends advanced_testcase {

    /**
     * Set up function to reset all database changes after each test.
     */
    protected function setUp(): void {
        // Reset the database after each test.
        $this->resetAfterTest();
    }

    /**
     * Test the get_description function.
     * @covers \local_adele\course_restriction\conditions\specific_course::get_description
     */
    public function test_get_description() {
        $specificcourserestriction = new specific_course();
        $description = $specificcourserestriction->get_description();

        $this->assertIsArray($description);
        $this->assertArrayHasKey('id', $description);
        $this->assertArrayHasKey('name', $description);
        $this->assertArrayHasKey('description', $description);
        $this->assertEquals($specificcourserestriction->id, $description['id']);
        $this->assertEquals($specificcourserestriction->label, $description['label']);
    }

    /**
     * Test the get_restriction_status function.
     * @covers \local_adele\course_restriction\conditions\specific_course::get_restriction_status
     */
    public function test_get_restriction_status() {
        $specificcourserestriction = new specific_course();

        // Test with valid start and end date.
        $userpath = (object)[
            'json' => [
                'tree' => [
                    'nodes' => [
                        [
                            'id' => 1,
                            'data' => [
                                'fullname' => 'Course 1',
                                'completion' => [
                                    'feedback' => ['status' => 'completed'],
                                ],
                            ],
                        ],
                        [
                            'id' => 2,
                            'data' => [
                                'fullname' => 'Course 2',
                                'completion' => [
                                    'feedback' => ['status' => 'incomplete'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $nodecomplete = [
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 12,
                        'data' => [
                            'label' => 'specific_course',
                            'value' => [
                                'courseid' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $statuscomplete = $specificcourserestriction->get_restriction_status($nodecomplete, $userpath);
        $this->assertArrayHasKey(12, $statuscomplete);
        $this->assertEquals($statuscomplete[12]['completed']['id'], 1);
        $this->assertContains('Course 1', $statuscomplete[12]['placeholders']['course_list']);

        $nodeincomplete = [
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 13,
                        'data' => [
                            'label' => 'specific_course',
                            'value' => [
                                'courseid' => 2,
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $statusincomplete = $specificcourserestriction->get_restriction_status($nodeincomplete, $userpath);
        $this->assertArrayHasKey(13, $statusincomplete);
        $this->assertArrayNotHasKey('completed', $statusincomplete[13]);
        $this->assertContains('Course 2', $statusincomplete[13]['placeholders']['course_list']);

    }

}
