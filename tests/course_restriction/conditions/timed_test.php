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
 * PHPUnit test case for the 'timed' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timed_test extends advanced_testcase {

    /**
     * Set up function to reset all database changes after each test.
     */
    protected function setUp(): void {
        // Reset the database after each test.
        $this->resetAfterTest();
    }

    /**
     * Test the get_description function.
     * @covers \local_adele\course_restriction\conditions\timed::get_description
     */
    public function test_get_description() {
        $timedrestriction = new timed();

        $description = $timedrestriction->get_description();

        $this->assertIsArray($description);
        $this->assertArrayHasKey('id', $description);
        $this->assertArrayHasKey('name', $description);
        $this->assertArrayHasKey('description', $description);
        $this->assertEquals($timedrestriction->id, $description['id']);
        $this->assertEquals($timedrestriction->label, $description['label']);
    }

    /**
     * Test the isvaliddate function.
     * @covers \local_adele\course_restriction\conditions\timed::isvaliddate
     */
    public function test_isvaliddate() {
        $timed = new timed();

        // Test valid date.
        $validdate = $timed->isvaliddate('2024-01-01T00:00');
        $this->assertInstanceOf(DateTime::class, $validdate);
        $this->assertEquals("01.01.2024 00:00", $validdate->format('d.m.Y H:i'));

        // Test invalid date.
        $validdate = $timed->isvaliddate('invalid-date');
        $this->assertFalse($validdate);
    }

    /**
     * Test the get_restriction_status function.
     * @covers \local_adele\course_restriction\conditions\timed::get_restriction_status
     */
    public function test_get_restriction_status() {
        $timed = new timed();

        // Test with valid start and end date.
        $node = [
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 1,
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => '2024-01-01T00:00',
                                'end' => '2026-12-31T23:59',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $userpath = (object) ['userid' => 1];
        $status = $timed->get_restriction_status($node, $userpath);

        $this->assertArrayHasKey(1, $status);
        $this->assertTrue($status[1]['completed']);
        $this->assertNotEmpty($status[1]['inbetween_info']);
        $this->assertEquals("01.01.2024 00:00", $status[1]['inbetween_info']['starttime']);
        $this->assertEquals("31.12.2026 23:59", $status[1]['inbetween_info']['endtime']);

        // Test with future start date (restriction should be incomplete).
        $futurenode = [
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 2,
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => '2099-01-01T00:00',
                                'end' => '2099-12-31T23:59',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $futurestatus = $timed->get_restriction_status($futurenode, $userpath);

        $this->assertArrayHasKey(2, $futurestatus);
        $this->assertFalse($futurestatus[2]['completed']);
        $this->assertEquals("01.01.2099 00:00", $futurestatus[2]['inbetween_info']['starttime']);
        $this->assertEquals("31.12.2099 23:59", $futurestatus[2]['inbetween_info']['endtime']);
    }

}
