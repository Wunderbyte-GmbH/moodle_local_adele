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
 * Unit tests for timezone consistency across timed restriction evaluation,
 * date_to_timestamp conversion and adhoc task scheduling.
 *
 * Verifies that:
 * - timed.php evaluates dates consistently in all server timezones
 * - date_to_timestamp() and date_to_timestamp_formatted() produce correct
 *   UTC timestamps regardless of the configured Moodle timezone
 * - A date 30 minutes in the future is ALWAYS recognised as future
 * - A date 30 minutes in the past is ALWAYS recognised as past
 * - timed.php and node_completion agree on whether a date is future/past
 *   (the original bug was a timezone mismatch between these two)
 *
 * @package     local_adele
 * @category    test
 * @author      Ralf Erlebach
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use DateTime;
use DateTimeZone;
use local_adele\course_restriction\conditions\timed;
use ReflectionMethod;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/adele/lib.php');

/**
 * Tests for timezone consistency.
 *
 * @package     local_adele
 * @category    test
 * @author      Ralf Erlebach
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\node_completion
 * @covers \local_adele\course_restriction\conditions\timed
 */
class timezone_consistency_test extends advanced_testcase {

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Call a private/protected static method on node_completion.
     *
     * @param string $methodname
     * @param array $args
     * @return mixed
     */
    private function call_method(string $methodname, array $args) {
        $method = new ReflectionMethod(node_completion::class, $methodname);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $args);
    }

    /**
     * Build a minimal node with a single timed restriction.
     *
     * @param string $start Start date in Y-m-d\TH:i format
     * @param string|null $end End date or null
     * @return array
     */
    private function build_timed_node(string $start, ?string $end = null): array {
        return [
            'id' => 'dndnode_test',
            'data' => ['course_node_id' => [99]],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'type' => 'condition',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => $start,
                                'end' => $end,
                            ],
                        ],
                        'parentCondition' => ['starting_condition'],
                        'childCondition' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Create a date string in Y-m-d\TH:i format relative to now
     * in a specific timezone.
     *
     * @param string $modifier e.g. '+30 minutes', '-1 hour'
     * @param string $timezone e.g. 'Europe/London'
     * @return string
     */
    private function relative_date_in_tz(string $modifier, string $timezone): string {
        $tz = new DateTimeZone($timezone);
        $dt = new DateTime($modifier, $tz);
        return $dt->format('Y-m-d\TH:i');
    }

    // =========================================================================
    // DATA PROVIDERS
    // =========================================================================

    /**
     * Timezones covering a wide range of UTC offsets.
     *
     * @return array
     */
    public static function timezone_provider(): array {
        return [
            'UTC (+0)'                  => ['UTC'],
            'Europe/London (GMT/BST)'   => ['Europe/London'],
            'Europe/Berlin (CET/CEST)'  => ['Europe/Berlin'],
            'Europe/Moscow (MSK +3)'    => ['Europe/Moscow'],
            'America/New_York (EST/EDT)' => ['America/New_York'],
            'America/Los_Angeles (PST/PDT)' => ['America/Los_Angeles'],
            'Asia/Tokyo (JST +9)'       => ['Asia/Tokyo'],
            'Asia/Kolkata (IST +5:30)'  => ['Asia/Kolkata'],
            'Pacific/Auckland (NZST +12)' => ['Pacific/Auckland'],
            'Pacific/Honolulu (HST -10)' => ['Pacific/Honolulu'],
        ];
    }

    // =========================================================================
    // TESTS: date_to_timestamp() – Y-m-d\TH:i format
    // =========================================================================

    /**
     * A date 30 minutes in the future must produce a future timestamp
     * regardless of the configured Moodle timezone.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_date_to_timestamp_future_date($timezone) {
        set_config('timezone', $timezone);

        $futuredate = $this->relative_date_in_tz('+30 minutes', $timezone);
        $timestamp = $this->call_method('date_to_timestamp', [$futuredate]);

        $this->assertNotFalse($timestamp,
            "date_to_timestamp must parse '$futuredate' in $timezone");
        $this->assertGreaterThan(time(), $timestamp,
            "date_to_timestamp('$futuredate') must return a future timestamp in $timezone");
    }

    /**
     * A date 30 minutes in the past must produce a past timestamp
     * regardless of the configured Moodle timezone.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_date_to_timestamp_past_date($timezone) {
        set_config('timezone', $timezone);

        $pastdate = $this->relative_date_in_tz('-30 minutes', $timezone);
        $timestamp = $this->call_method('date_to_timestamp', [$pastdate]);

        $this->assertNotFalse($timestamp,
            "date_to_timestamp must parse '$pastdate' in $timezone");
        $this->assertLessThan(time(), $timestamp,
            "date_to_timestamp('$pastdate') must return a past timestamp in $timezone");
    }

    /**
     * date_to_timestamp with empty string returns false.
     */
    public function test_date_to_timestamp_empty() {
        $this->assertFalse($this->call_method('date_to_timestamp', ['']));
    }

    /**
     * date_to_timestamp with null returns false.
     */
    public function test_date_to_timestamp_null() {
        $this->assertFalse($this->call_method('date_to_timestamp', [null]));
    }

    /**
     * date_to_timestamp with invalid string does not throw an exception.
     */
    public function test_date_to_timestamp_invalid() {
        $result = $this->call_method('date_to_timestamp', ['not-a-date']);
        // Should return false or a fallback timestamp, but never throw.
        $this->assertTrue($result === false || is_int($result));
    }

    // =========================================================================
    // TESTS: date_to_timestamp_formatted() – d.m.Y H:i format
    // =========================================================================

    /**
     * A formatted date 30 minutes in the future must produce a future timestamp.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_date_to_timestamp_formatted_future($timezone) {
        set_config('timezone', $timezone);

        $tz = new DateTimeZone($timezone);
        $futuredate = (new DateTime('+30 minutes', $tz))->format('d.m.Y H:i');
        $timestamp = $this->call_method('date_to_timestamp_formatted', [$futuredate]);

        $this->assertNotFalse($timestamp,
            "date_to_timestamp_formatted must parse '$futuredate' in $timezone");
        $this->assertGreaterThan(time(), $timestamp,
            "date_to_timestamp_formatted('$futuredate') must return future in $timezone");
    }

    /**
     * A formatted date 30 minutes in the past must produce a past timestamp.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_date_to_timestamp_formatted_past($timezone) {
        set_config('timezone', $timezone);

        $tz = new DateTimeZone($timezone);
        $pastdate = (new DateTime('-30 minutes', $tz))->format('d.m.Y H:i');
        $timestamp = $this->call_method('date_to_timestamp_formatted', [$pastdate]);

        $this->assertNotFalse($timestamp,
            "date_to_timestamp_formatted must parse '$pastdate' in $timezone");
        $this->assertLessThan(time(), $timestamp,
            "date_to_timestamp_formatted('$pastdate') must return past in $timezone");
    }

    /**
     * date_to_timestamp_formatted with empty string returns false.
     */
    public function test_date_to_timestamp_formatted_empty() {
        $this->assertFalse($this->call_method('date_to_timestamp_formatted', ['']));
    }

    /**
     * date_to_timestamp_formatted with wrong format returns false.
     */
    public function test_date_to_timestamp_formatted_wrong_format() {
        $this->assertFalse($this->call_method('date_to_timestamp_formatted', ['2026-03-16T18:00']));
    }

    // =========================================================================
    // TESTS: timed.php timezone consistency
    // =========================================================================

    /**
     * timed.php must evaluate a future start date as isbefore=true
     * regardless of the configured Moodle timezone.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_timed_future_start_across_timezones($timezone) {
        set_config('timezone', $timezone);

        $futuredate = $this->relative_date_in_tz('+30 minutes', $timezone);
        $node = $this->build_timed_node($futuredate);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertFalse($result['c1']['completed'],
            "Future start must not be completed in $timezone (date=$futuredate)");
        $this->assertTrue($result['c1']['isbefore'],
            "Future start must be isbefore=true in $timezone (date=$futuredate)");
    }

    /**
     * timed.php must evaluate a past start date as completed=true
     * regardless of the configured Moodle timezone.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_timed_past_start_across_timezones($timezone) {
        set_config('timezone', $timezone);

        $pastdate = $this->relative_date_in_tz('-30 minutes', $timezone);
        $node = $this->build_timed_node($pastdate);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertTrue($result['c1']['completed'],
            "Past start must be completed in $timezone (date=$pastdate)");
        $this->assertFalse($result['c1']['isbefore'],
            "Past start must be isbefore=false in $timezone (date=$pastdate)");
    }

    /**
     * timed.php must evaluate a past end date as isafter=true
     * regardless of the configured Moodle timezone.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_timed_past_end_across_timezones($timezone) {
        set_config('timezone', $timezone);

        $pastdate = $this->relative_date_in_tz('-30 minutes', $timezone);
        $node = $this->build_timed_node('2020-01-01T00:00', $pastdate);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertFalse($result['c1']['completed'],
            "Past end must not be completed in $timezone");
        $this->assertTrue($result['c1']['isafter'],
            "Past end must be isafter=true in $timezone");
    }

    // =========================================================================
    // TESTS: Consistency between timed.php and date_to_timestamp()
    // =========================================================================

    /**
     * When timed.php says isbefore=true (start date in the future),
     * date_to_timestamp() must also return a future timestamp.
     *
     * This was the ORIGINAL BUG: timed.php used PHP default timezone
     * while date_to_timestamp() used Moodle timezone. If they differed,
     * timed.php said "future" but date_to_timestamp() said "past",
     * so no adhoc task was scheduled.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_timed_and_date_to_timestamp_agree_on_future($timezone) {
        set_config('timezone', $timezone);

        $futuredate = $this->relative_date_in_tz('+30 minutes', $timezone);

        // timed.php evaluation.
        $node = $this->build_timed_node($futuredate);
        $timed = new timed();
        $timedresult = $timed->get_restriction_status($node, new \stdClass());
        $timedisbefore = $timedresult['c1']['isbefore'];

        // date_to_timestamp evaluation.
        $timestamp = $this->call_method('date_to_timestamp', [$futuredate]);
        $timestampisfuture = ($timestamp !== false && $timestamp > time());

        $this->assertEquals($timedisbefore, $timestampisfuture,
            "timed.php and date_to_timestamp must agree: "
            . "timed says isbefore=" . var_export($timedisbefore, true)
            . ", date_to_timestamp says future=" . var_export($timestampisfuture, true)
            . " for date=$futuredate in timezone=$timezone"
        );
    }

    /**
     * When timed.php says isbefore=false (start date in the past),
     * date_to_timestamp() must also return a past timestamp.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_timed_and_date_to_timestamp_agree_on_past($timezone) {
        set_config('timezone', $timezone);

        $pastdate = $this->relative_date_in_tz('-30 minutes', $timezone);

        // timed.php evaluation.
        $node = $this->build_timed_node($pastdate);
        $timed = new timed();
        $timedresult = $timed->get_restriction_status($node, new \stdClass());
        $timedisbefore = $timedresult['c1']['isbefore'];

        // date_to_timestamp evaluation.
        $timestamp = $this->call_method('date_to_timestamp', [$pastdate]);
        $timestampisfuture = ($timestamp !== false && $timestamp > time());

        // Both should say "not future" / "not isbefore".
        $this->assertFalse($timedisbefore,
            "timed.php should say isbefore=false for past date in $timezone");
        $this->assertFalse($timestampisfuture,
            "date_to_timestamp should return past timestamp for past date in $timezone");
    }

    // =========================================================================
    // TESTS: check_node_restrictions() timezone consistency
    // =========================================================================

    /**
     * check_node_restrictions() must set next_start_date for a future
     * timed condition regardless of the configured timezone.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_check_restrictions_sets_next_start_date_across_timezones($timezone) {
        set_config('timezone', $timezone);

        $futuredate = $this->relative_date_in_tz('+30 minutes', $timezone);

        $nodearray = [
            'id' => 'dndnode_test',
            'data' => ['course_node_id' => [99]],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'type' => 'condition',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => $futuredate,
                                'end' => null,
                            ],
                        ],
                        'parentCondition' => ['starting_condition'],
                        'childCondition' => [],
                    ],
                ],
            ],
        ];

        $userpathrecord = new \stdClass();
        $userpathrecord->id = 2;
        $userpathrecord->user_id = 9;
        $userpathrecord->learning_path_id = 1;
        $userpathrecord->status = 'active';
        $userpathrecord->timecreated = time() - 3600;
        $userpathrecord->json = [
            'tree' => ['nodes' => []],
            'user_path_relation' => [],
        ];

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met'],
            "Future timed condition must not be met in $timezone");
        $this->assertNotNull($result['next_start_date'],
            "next_start_date must be set for future timed condition in $timezone");
        $this->assertEquals($futuredate, $result['next_start_date'],
            "next_start_date must match the future start date in $timezone");
    }

    /**
     * check_node_restrictions() must return met=true for a past
     * timed condition regardless of the configured timezone.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_check_restrictions_met_for_past_date_across_timezones($timezone) {
        set_config('timezone', $timezone);

        $pastdate = $this->relative_date_in_tz('-30 minutes', $timezone);

        $nodearray = [
            'id' => 'dndnode_test',
            'data' => ['course_node_id' => [99]],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'type' => 'condition',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => $pastdate,
                                'end' => null,
                            ],
                        ],
                        'parentCondition' => ['starting_condition'],
                        'childCondition' => [],
                    ],
                ],
            ],
        ];

        $userpathrecord = new \stdClass();
        $userpathrecord->id = 2;
        $userpathrecord->user_id = 9;
        $userpathrecord->learning_path_id = 1;
        $userpathrecord->status = 'active';
        $userpathrecord->timecreated = time() - 3600;
        $userpathrecord->json = [
            'tree' => ['nodes' => []],
            'user_path_relation' => [],
        ];

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertTrue($result['met'],
            "Past timed condition must be met in $timezone");
        $this->assertNull($result['next_start_date'],
            "No retry needed for past timed condition in $timezone");
    }

    // =========================================================================
    // TESTS: DST transition edge cases
    // =========================================================================

    /**
     * Test a date during the spring-forward DST transition in Europe/London.
     * On 2026-03-29 at 01:00 GMT, clocks move forward to 02:00 BST.
     * The time 01:30 does not exist in BST.
     * date_to_timestamp should handle this gracefully.
     */
    public function test_dst_spring_forward_london() {
        set_config('timezone', 'Europe/London');

        // 2026-03-29T01:30 does not exist in Europe/London (spring forward).
        // DateTime::createFromFormat should still return a valid object
        // (PHP adjusts to 02:30 BST automatically).
        $result = $this->call_method('date_to_timestamp', ['2026-03-29T01:30']);

        // Should not return false – PHP adjusts the time.
        $this->assertNotFalse($result,
            'date_to_timestamp should handle DST spring-forward gracefully');
        $this->assertIsInt($result);
    }

    /**
     * Test a date during the fall-back DST transition in Europe/London.
     * On 2026-10-25 at 02:00 BST, clocks move back to 01:00 GMT.
     * The time 01:30 exists twice (in BST and GMT).
     * date_to_timestamp should handle this gracefully.
     */
    public function test_dst_fall_back_london() {
        set_config('timezone', 'Europe/London');

        // 2026-10-25T01:30 is ambiguous in Europe/London (fall back).
        $result = $this->call_method('date_to_timestamp', ['2026-10-25T01:30']);

        $this->assertNotFalse($result,
            'date_to_timestamp should handle DST fall-back gracefully');
        $this->assertIsInt($result);
    }

    /**
     * Test a date during the spring-forward DST transition in Europe/Berlin.
     * On 2026-03-29 at 02:00 CET, clocks move forward to 03:00 CEST.
     * The time 02:30 does not exist.
     */
    public function test_dst_spring_forward_berlin() {
        set_config('timezone', 'Europe/Berlin');

        $result = $this->call_method('date_to_timestamp', ['2026-03-29T02:30']);

        $this->assertNotFalse($result,
            'date_to_timestamp should handle DST spring-forward in Berlin gracefully');
        $this->assertIsInt($result);
    }

    /**
     * Test a date during the spring-forward DST transition in America/New_York.
     * On 2026-03-08 at 02:00 EST, clocks move forward to 03:00 EDT.
     * The time 02:30 does not exist.
     */
    public function test_dst_spring_forward_new_york() {
        set_config('timezone', 'America/New_York');

        $result = $this->call_method('date_to_timestamp', ['2026-03-08T02:30']);

        $this->assertNotFalse($result,
            'date_to_timestamp should handle DST spring-forward in New York gracefully');
        $this->assertIsInt($result);
    }

    // =========================================================================
    // TESTS: Half-hour offset timezones
    // =========================================================================

    /**
     * Asia/Kolkata has a +5:30 offset. Verify that dates are correctly
     * interpreted in this non-standard offset timezone.
     */
    public function test_half_hour_offset_kolkata() {
        set_config('timezone', 'Asia/Kolkata');

        $tz = new DateTimeZone('Asia/Kolkata');
        $futuredt = new DateTime('+30 minutes', $tz);
        $futuredate = $futuredt->format('Y-m-d\TH:i');

        $timestamp = $this->call_method('date_to_timestamp', [$futuredate]);

        $this->assertNotFalse($timestamp);
        $this->assertGreaterThan(time(), $timestamp,
            'Future date in Asia/Kolkata (+5:30) must produce future timestamp');

        // Also verify timed.php agrees.
        $node = $this->build_timed_node($futuredate);
        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertTrue($result['c1']['isbefore'],
            'timed.php must say isbefore=true for future date in Asia/Kolkata');
    }

    /**
     * Pacific/Chatham has a +12:45 offset. Verify correct handling.
     */
    public function test_unusual_offset_chatham() {
        set_config('timezone', 'Pacific/Chatham');

        $tz = new DateTimeZone('Pacific/Chatham');
        $futuredt = new DateTime('+30 minutes', $tz);
        $futuredate = $futuredt->format('Y-m-d\TH:i');

        $timestamp = $this->call_method('date_to_timestamp', [$futuredate]);

        $this->assertNotFalse($timestamp);
        $this->assertGreaterThan(time(), $timestamp,
            'Future date in Pacific/Chatham (+12:45) must produce future timestamp');
    }

    // =========================================================================
    // TESTS: Timestamp precision
    // =========================================================================

    /**
     * Verify that date_to_timestamp and timed.php produce timestamps
     * that are within 60 seconds of each other for the same date string.
     * This ensures they interpret the date in the same timezone.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_timestamp_precision_between_methods($timezone) {
        set_config('timezone', $timezone);

        $datestring = '2026-06-15T14:30';

        // date_to_timestamp result.
        $nctimestamp = $this->call_method('date_to_timestamp', [$datestring]);

        // timed.php result (extract from inbetween_info).
        $node = $this->build_timed_node($datestring);
        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        // timed.php formats the start date as d.m.Y H:i.
        $formattedstart = $result['c1']['inbetween_info']['starttime'];
        $this->assertEquals('15.06.2026 14:30', $formattedstart,
            "timed.php should format the date as d.m.Y H:i in $timezone");

        // Convert the formatted date back to a timestamp.
        $timedtimestamp = $this->call_method('date_to_timestamp_formatted', [$formattedstart]);

        // Both timestamps should be identical (same date, same timezone).
        $this->assertNotFalse($nctimestamp);
        $this->assertNotFalse($timedtimestamp);
        $this->assertEquals($nctimestamp, $timedtimestamp,
            "date_to_timestamp and date_to_timestamp_formatted must produce "
            . "the same timestamp for the same date in $timezone. "
            . "Y-m-d\\TH:i=$nctimestamp, d.m.Y H:i=$timedtimestamp"
        );
    }
}
