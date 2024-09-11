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
use DateTime;

/**
 * PHPUnit test case for the 'timed_duration' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timed_duration_test extends advanced_testcase {

    /**
     * Set up function to reset all database changes after each test.
     */
    protected function setUp(): void {
        // Reset the database after each test.
        $this->resetAfterTest();
    }

    /**
     * Test the get_description function.
     * @covers \local_adele\course_restriction\conditions\timed_duration::get_description
     */
    public function test_get_description() {
        $timedduration = new timed_duration();

        $description = $timedduration->get_description();

        $this->assertIsArray($description);
        $this->assertArrayHasKey('id', $description);
        $this->assertArrayHasKey('name', $description);
        $this->assertArrayHasKey('description', $description);
        $this->assertEquals($timedduration->id, $description['id']);
        $this->assertEquals($timedduration->label, $description['label']);
    }

    /**
     * Test the get_restriction_status function.
     * @covers \local_adele\course_restriction\conditions\timed_duration::get_restriction_status
     */
    public function test_get_restriction_status() {
        $timedduration = new timed_duration();

        // Test with valid start and end date.
        $node = [
            'data' => ['first_enrolled' => time() - (5 * 86400)],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 1,
                        'data' => [
                            'label' => 'timed_duration',
                            'value' => [
                                'selectedOption' => '1',
                                'durationValue' => '0',
                                'selectedDuration' => '7',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $userpath = (object) [
            'userid' => 1,
            'timecreated' => time() - (30 * 86400),
        ];

        $status = $timedduration->get_restriction_status($node, $userpath);

        $this->assertArrayHasKey(1, $status);
        $this->assertTrue($status[1]['completed']); // Should be within 7-day window.
        $this->assertArrayHasKey('inbetween_info', $status[1]);
        $this->assertNotNull($status[1]['inbetween_info']['starttime']);
        $this->assertNotNull($status[1]['inbetween_info']['endtime']);

        $node['data']['first_enrolled'] = time() - (10 * 86400); // First enrolled 10 days ago.
        $status = $timedduration->get_restriction_status($node, $userpath);

        $this->assertFalse($status[1]['completed']);
    }

}
