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
final class timed_test extends advanced_testcase {
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
     * @covers \local_adele\course_restriction\conditions\timed::get_description
     */
    public function test_get_description(): void {
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
    public function test_isvaliddate(): void {
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
     * A window around "now" is open, a window in the future is not.
     *
     * Everything is relative to one frozen instant, so the test means the same
     * on any date it runs. Exact boundaries, time zones and legacy values are
     * covered in timed_timestamps_test.php.
     *
     * @covers \local_adele\course_restriction\conditions\timed::get_restriction_status
     */
    public function test_get_restriction_status(): void {
        $this->resetAfterTest();
        $now = 1893456000;
        $this->mock_clock_with_frozen($now);
        $timed = new timed();
        $userpath = (object) ['timecreated' => 0];

        $window = static fn(int $id, int $start, int $end): array => ['restriction' => ['nodes' => [[
            'id' => $id,
            'data' => ['label' => 'timed', 'value' => ['start' => $start, 'end' => $end]],
        ]]]];

        $current = $timed->get_restriction_status($window(1, $now - DAYSECS, $now + DAYSECS), $userpath);
        $this->assertTrue($current[1]['completed'], 'a window around now must be open');
        $this->assertSame($now - DAYSECS, $current[1]['inbetween_info']['starttime']);
        $this->assertSame($now + DAYSECS, $current[1]['inbetween_info']['endtime']);

        $future = $timed->get_restriction_status($window(2, $now + YEARSECS, $now + 2 * YEARSECS), $userpath);
        $this->assertFalse($future[2]['completed'], 'a window in the future must be closed');
        $this->assertTrue($future[2]['isbefore']);
    }
}
