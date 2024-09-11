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
 * PHPUnit test case for the 'manual' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manual_test extends advanced_testcase {

    /**
     * Set up function to reset all database changes after each test.
     */
    protected function setUp(): void {
        // Reset the database after each test.
        $this->resetAfterTest();
    }

    /**
     * Test the get_description function.
     * @covers \local_adele\course_completion\conditions\manual::get_description
     */
    public function test_get_description() {
        $manualrestriction = new manual();

        $description = $manualrestriction->get_description();

        $this->assertIsArray($description);
        $this->assertArrayHasKey('id', $description);
        $this->assertArrayHasKey('name', $description);
        $this->assertArrayHasKey('description', $description);
        $this->assertEquals($manualrestriction->id, $description['id']);
        $this->assertEquals($manualrestriction->label, $description['label']);
    }

    /**
     * Test the get_completion_description_before function.
     * @covers \local_adele\course_completion\conditions\manual::get_restriction_status
     */
    public function test_get_restriction_status() {
        $manualrestriction = new manual();

        $nodeincomplete = [
            'data' => [
                'manualrestriction' => false,
                'manualrestrictionvalue' => false,
            ],
        ];
        $userpath = (object) ['userid' => 1];

        $statusincomplete = $manualrestriction->get_restriction_status($nodeincomplete, $userpath);
        $this->assertFalse($statusincomplete['completed']);
        $this->assertEquals('unchecked', $statusincomplete['inbetween_info']);

        $nodecomplete = [
            'data' => [
                'manualrestriction' => true,
                'manualrestrictionvalue' => false,
            ],
        ];
        $statuscomplete = $manualrestriction->get_restriction_status($nodecomplete, $userpath);
        $this->assertFalse($statuscomplete['completed']);
        $this->assertEquals('unchecked', $statuscomplete['inbetween_info']);
    }

}
