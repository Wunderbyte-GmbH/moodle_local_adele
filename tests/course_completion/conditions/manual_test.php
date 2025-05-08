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
final class manual_test extends advanced_testcase {
    /**
     * Set up function to reset all database changes after each test.
     */
    protected function setUp(): void {
        parent::setUp();
        // Reset the database after each test.
        $this->resetAfterTest();
    }

    /**
     * Test the get_description function.
     * @covers \local_adele\course_completion\conditions\manual::get_description
     */
    public function test_get_description(): void {
        $manualcompletion = new manual();

        $description = $manualcompletion->get_description();

        $this->assertIsArray($description);
        $this->assertArrayHasKey('id', $description);
        $this->assertArrayHasKey('name', $description);
        $this->assertArrayHasKey('description', $description);
        $this->assertEquals($manualcompletion->id, $description['id']);
        $this->assertEquals($manualcompletion->label, $description['label']);
    }

    /**
     * Test the get_completion_priority function.
     * @covers \local_adele\course_completion\conditions\manual::get_completion_priority
     */
    public function test_get_completion_priority(): void {
        $manualcompletion = new manual();
        $priority = $manualcompletion->get_completion_priority();
        $this->assertIsInt($priority);
        $this->assertEquals($manualcompletion->priority, $priority);
    }

    /**
     * Test the get_completion_description_before function.
     * @covers \local_adele\course_completion\conditions\manual::get_completion_description_before
     */
    public function test_get_completion_description_before(): void {
        $manualcompletion = new manual();
        $description = $manualcompletion->get_completion_description_before();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    /**
     * Test the get_completion_description_after function.
     * @covers \local_adele\course_completion\conditions\manual::get_completion_description_after
     */
    public function test_get_completion_description_after(): void {
        $manualcompletion = new manual();
        $description = $manualcompletion->get_completion_description_after();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    /**
     * Test the get_completion_description_inbetween function.
     * @covers \local_adele\course_completion\conditions\manual::get_completion_description_inbetween
     */
    public function test_get_completion_description_inbetween(): void {
        $manualcompletion = new manual();
        $description = $manualcompletion->get_completion_description_inbetween();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    /**
     * Test the get_completion_status function.
     * @covers \local_adele\course_completion\conditions\manual::get_completion_status
     */
    public function test_get_completion_status(): void {
        $manualcompletion = new manual();

        // Mock node data that is incomplete.
        $nodeincomplete = [
            'data' => [
                'manualcompletion' => false,
                'manualcompletionvalue' => false,
            ],
        ];

        $statusincomplete = $manualcompletion->get_completion_status($nodeincomplete, 1);
        $this->assertFalse($statusincomplete['completed']);
        $this->assertEquals('unchecked', $statusincomplete['inbetween_info']);

        // Mock node data that is complete.
        $nodecomplete = [
            'data' => [
                'manualcompletion' => true,
                'manualcompletionvalue' => true,
            ],
        ];

        $statuscomplete = $manualcompletion->get_completion_status($nodecomplete, 1);
        $this->assertTrue($statuscomplete['completed']);
        $this->assertEquals('checked', $statuscomplete['inbetween_info']);
    }
}
