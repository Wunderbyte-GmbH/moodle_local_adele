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

namespace local_adele\course_completion\conditions;

use advanced_testcase;

/**
 * PHPUnit test case for the 'modquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class modquiz_test extends advanced_testcase {
    /**
     * Set up function to reset all database changes after each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the get_completion_priority function.
     * @covers \local_adele\course_completion\conditions\modquiz::get_completion_priority
     */
    public function test_get_completion_priority(): void {
        $modquiz = new modquiz();
        $priority = $modquiz->get_completion_priority();
        $this->assertEquals($modquiz->priority, $priority);
    }

    /**
     * Test the get_description function.
     * @covers \local_adele\course_completion\conditions\modquiz::get_description
     */
    public function test_get_description(): void {
        $modquiz = new modquiz();

        $description = $modquiz->get_description();

        $this->assertIsArray($description);
        $this->assertArrayHasKey('id', $description);
        $this->assertArrayHasKey('name', $description);
        $this->assertArrayHasKey('description', $description);
        $this->assertEquals($modquiz->id, $description['id']);
        $this->assertEquals($modquiz->label, $description['label']);
    }

    /**
     * Test the get_completion_status function.
     * @covers \local_adele\course_completion\conditions\modquiz::get_completion_status
     */
    public function test_get_completion_status(): void {
        global $DB;

        // Mock the global $DB object.
        $DB = $this->createMock(\moodle_database::class);
        $DB->expects($this->any())
            ->method('get_record_sql')
            ->willReturn((object)[
                'name' => 'Sample Quiz Name',
                'cmid' => 65,
            ]);
        $modquiz = $this->getMockBuilder(modquiz::class)
            ->onlyMethods(['get_modquiz_records'])
            ->getMock();

        $modquiz->method('get_modquiz_records')
            ->will($this->returnValue([65 => (object)['grade' => 65]]));

        // Test incomplete node data (expecting no completion).
        $nodeincomplete = [
            'completion' => [
                'nodes' => [
                    [
                        'id' => 10,
                        'data' => [
                            'label' => 'modquiz',
                            'value' => [
                              'grade' => 70,
                              'quizid' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Simulate calling the get_completion_status method and passing the node.
        $statusincomplete = $modquiz->get_completion_status($nodeincomplete, 3);
        $this->assertFalse($statusincomplete['completed'][10]);
        $this->assertStringContainsString('65/70', $statusincomplete['inbetween_info']);
        $this->assertStringContainsString('/mod/quiz/view', $statusincomplete[10]['placeholders']['quiz_name_link']);

        // Test complete node data (expecting completion).
        $nodecomplete = [
            'completion' => [
                'nodes' => [
                    [
                        'id' => 10,
                        'data' => [
                            'label' => 'modquiz',
                            'value' => [
                              'grade' => 55,
                              'quizid' => 1,
                            ],
                          ],
                    ],
                ],
            ],
        ];

        // Simulate calling the get_completion_status method with complete node data.
        $statuscomplete = $modquiz->get_completion_status($nodecomplete, 1);
        $this->assertTrue($statuscomplete['completed'][10]);
        $this->assertStringContainsString('65/55', $statuscomplete['inbetween_info']);
    }
}
