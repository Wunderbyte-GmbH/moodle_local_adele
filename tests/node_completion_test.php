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
 * Unit tests for node_completion restriction checks, timezone handling,
 * and adhoc task scheduling.
 *
 * @package     local_adele
 * @category    test
 * @author      Ralf Erlebach
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use DateTime;
use DateTimeZone;
use local_adele\course_restriction\conditions\timed;
use ReflectionClass;
use ReflectionMethod;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/adele/lib.php');

/**
 * Unit tests for node_completion and timed restriction.
 *
 * @package     local_adele
 * @category    test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class node_completion_test extends advanced_testcase {

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
    private function call_node_completion_method($methodname, array $args) {
        $method = new ReflectionMethod(node_completion::class, $methodname);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $args);
    }

    /**
     * Build a minimal restriction node for timed conditions.
     *
     * @param string $id
     * @param string|null $start
     * @param string|null $end
     * @param array $parentcondition
     * @param array $childcondition
     * @return array
     */
    private function build_timed_restriction_node(
        $id,
        $start = null,
        $end = null,
        $parentcondition = ['starting_condition'],
        $childcondition = []
    ) {
        return [
            'id' => $id,
            'type' => 'condition',
            'data' => [
                'label' => 'timed',
                'value' => [
                    'start' => $start,
                    'end' => $end,
                ],
            ],
            'parentCondition' => $parentcondition,
            'childCondition' => $childcondition,
        ];
    }

    /**
     * Build a feedback restriction node.
     *
     * @param string $id
     * @param string $parentid
     * @return array
     */
    private function build_feedback_node($id, $parentid) {
        return [
            'id' => $id,
            'type' => 'feedback',
            'data' => [
                'childCondition' => $parentid,
                'visibility' => true,
            ],
            'parentCondition' => [$parentid],
            'childCondition' => [],
        ];
    }

    /**
     * Build a parent_courses restriction node.
     *
     * @param string $id
     * @param array $parentcondition
     * @param array $childcondition
     * @return array
     */
    private function build_parent_courses_node(
        $id,
        $parentcondition = ['starting_condition'],
        $childcondition = []
    ) {
        return [
            'id' => $id,
            'type' => 'condition',
            'data' => [
                'label' => 'parent_courses',
                'value' => [],
            ],
            'parentCondition' => $parentcondition,
            'childCondition' => $childcondition,
        ];
    }

    /**
     * Build a minimal node array with restrictions.
     *
     * @param array $restrictionnodes
     * @return array
     */
    private function build_node_with_restrictions($restrictionnodes) {
        return [
            'id' => 'dndnode_2',
            'data' => [
                'course_node_id' => [19],
            ],
            'restriction' => [
                'nodes' => $restrictionnodes,
            ],
        ];
    }

    /**
     * Build a minimal userpath record.
     *
     * @param int $userid
     * @param int $learningpathid
     * @param array $nodes
     * @return object
     */
    private function build_userpath_record($userid = 9, $learningpathid = 1, $nodes = []) {
        $record = new \stdClass();
        $record->id = 2;
        $record->user_id = $userid;
        $record->learning_path_id = $learningpathid;
        $record->status = 'active';
        $record->timecreated = time() - 3600;
        $record->json = [
            'tree' => [
                'nodes' => $nodes,
            ],
            'user_path_relation' => [],
        ];
        return $record;
    }

    // =========================================================================
    // TESTS: timed.php – isbefore / isafter / completed flags
    // =========================================================================

    /**
     * Test timed condition: start date in the future.
     * Expected: completed=false, isbefore=true, isafter=false.
     */
    public function test_timed_start_in_future() {
        $futuredate = (new DateTime('+1 hour'))->format('Y-m-d\TH:i');

        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', $futuredate, null),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertFalse($result['cond_1']['completed']);
        $this->assertTrue($result['cond_1']['isbefore']);
        $this->assertFalse($result['cond_1']['isafter']);
    }

    /**
     * Test timed condition: start date in the past, no end date.
     * Expected: completed=true, isbefore=false, isafter=false.
     */
    public function test_timed_start_in_past_no_end() {
        $pastdate = (new DateTime('-1 hour'))->format('Y-m-d\TH:i');

        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', $pastdate, null),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertTrue($result['cond_1']['completed']);
        $this->assertFalse($result['cond_1']['isbefore']);
        $this->assertFalse($result['cond_1']['isafter']);
    }

    /**
     * Test timed condition: end date in the past, no start date.
     * Expected: completed=false, isbefore=false, isafter=true.
     * This was the bug: previously isbefore was true AND isafter was true.
     */
    public function test_timed_end_in_past_no_start() {
        $pastdate = (new DateTime('-1 hour'))->format('Y-m-d\TH:i');

        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', null, $pastdate),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertFalse($result['cond_1']['completed']);
        $this->assertFalse($result['cond_1']['isbefore'],
            'isbefore must be false when no start date exists');
        $this->assertTrue($result['cond_1']['isafter']);
    }

    /**
     * Test timed condition: end date in the future, no start date.
     * Expected: completed=true, isbefore=false, isafter=false.
     */
    public function test_timed_end_in_future_no_start() {
        $futuredate = (new DateTime('+1 hour'))->format('Y-m-d\TH:i');

        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', null, $futuredate),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertTrue($result['cond_1']['completed']);
        $this->assertFalse($result['cond_1']['isbefore']);
        $this->assertFalse($result['cond_1']['isafter']);
    }

    /**
     * Test timed condition: current time is between start and end.
     * Expected: completed=true, isbefore=false, isafter=false.
     */
    public function test_timed_in_between() {
        $pastdate = (new DateTime('-1 hour'))->format('Y-m-d\TH:i');
        $futuredate = (new DateTime('+1 hour'))->format('Y-m-d\TH:i');

        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', $pastdate, $futuredate),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertTrue($result['cond_1']['completed']);
        $this->assertFalse($result['cond_1']['isbefore']);
        $this->assertFalse($result['cond_1']['isafter']);
    }

    /**
     * Test timed condition: both start and end in the past.
     * Expected: completed=false, isbefore=false, isafter=true.
     */
    public function test_timed_both_in_past() {
        $paststart = (new DateTime('-2 hours'))->format('Y-m-d\TH:i');
        $pastend = (new DateTime('-1 hour'))->format('Y-m-d\TH:i');

        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', $paststart, $pastend),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertFalse($result['cond_1']['completed']);
        $this->assertFalse($result['cond_1']['isbefore']);
        $this->assertTrue($result['cond_1']['isafter']);
    }

    /**
     * Test timed condition: both start and end in the future.
     * Expected: completed=false, isbefore=true, isafter=false.
     */
    public function test_timed_both_in_future() {
        $futurestart = (new DateTime('+1 hour'))->format('Y-m-d\TH:i');
        $futureend = (new DateTime('+2 hours'))->format('Y-m-d\TH:i');

        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', $futurestart, $futureend),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertFalse($result['cond_1']['completed']);
        $this->assertTrue($result['cond_1']['isbefore']);
        $this->assertFalse($result['cond_1']['isafter']);
    }

    /**
     * Test timed condition: no start date, no end date.
     * Expected: completed=true (no restrictions), isbefore=false, isafter=false.
     */
    public function test_timed_no_dates() {
        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', null, null),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertTrue($result['cond_1']['completed']);
        $this->assertFalse($result['cond_1']['isbefore']);
        $this->assertFalse($result['cond_1']['isafter']);
    }

    /**
     * Test that isbefore and isafter are NEVER both true simultaneously.
     * This was the original bug with dndnode_3.
     *
     * @dataProvider timed_combinations_provider
     */
    public function test_timed_isbefore_isafter_mutually_exclusive($start, $end) {
        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', $start, $end),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertFalse(
            $result['cond_1']['isbefore'] && $result['cond_1']['isafter'],
            "isbefore and isafter must never both be true. "
            . "start=$start, end=$end, "
            . "isbefore=" . var_export($result['cond_1']['isbefore'], true) . ", "
            . "isafter=" . var_export($result['cond_1']['isafter'], true)
        );
    }

    /**
     * Data provider for timed combinations.
     *
     * @return array
     */
    public static function timed_combinations_provider(): array {
        $past2h = (new DateTime('-2 hours'))->format('Y-m-d\TH:i');
        $past1h = (new DateTime('-1 hour'))->format('Y-m-d\TH:i');
        $future1h = (new DateTime('+1 hour'))->format('Y-m-d\TH:i');
        $future2h = (new DateTime('+2 hours'))->format('Y-m-d\TH:i');

        return [
            'no dates' => [null, null],
            'only start in past' => [$past1h, null],
            'only start in future' => [$future1h, null],
            'only end in past' => [null, $past1h],
            'only end in future' => [null, $future1h],
            'both in past' => [$past2h, $past1h],
            'both in future' => [$future1h, $future2h],
            'in between' => [$past1h, $future1h],
        ];
    }

    // =========================================================================
    // TESTS: Timezone consistency
    // =========================================================================

    /**
     * Test that timed condition evaluates consistently across different
     * server timezones. The start date should be interpreted in the
     * server timezone, not UTC.
     *
     * @dataProvider timezone_provider
     */
    public function test_timed_timezone_consistency($timezone) {
        // Set the Moodle timezone.
        set_config('timezone', $timezone);

        $tz = new DateTimeZone($timezone);
        // Create a start date 30 minutes in the future in the given timezone.
        $futuredt = new DateTime('+30 minutes', $tz);
        $futuredate = $futuredt->format('Y-m-d\TH:i');

        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', $futuredate, null),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertFalse($result['cond_1']['completed'],
            "Start date in future should not be completed in timezone $timezone");
        $this->assertTrue($result['cond_1']['isbefore'],
            "Start date in future should be isbefore=true in timezone $timezone");
    }

    /**
     * Test that a start date in the past is correctly evaluated across timezones.
     *
     * @dataProvider timezone_provider
     */
    public function test_timed_past_date_timezone_consistency($timezone) {
        set_config('timezone', $timezone);

        $tz = new DateTimeZone($timezone);
        $pastdt = new DateTime('-30 minutes', $tz);
        $pastdate = $pastdt->format('Y-m-d\TH:i');

        $node = $this->build_node_with_restrictions([
            $this->build_timed_restriction_node('cond_1', $pastdate, null),
        ]);

        $timed = new timed();
        $result = $timed->get_restriction_status($node, new \stdClass());

        $this->assertTrue($result['cond_1']['completed'],
            "Start date in past should be completed in timezone $timezone");
        $this->assertFalse($result['cond_1']['isbefore'],
            "Start date in past should be isbefore=false in timezone $timezone");
    }

    /**
     * Data provider for timezone tests.
     *
     * @return array
     */
    public static function timezone_provider(): array {
        return [
            'UTC' => ['UTC'],
            'Europe/London (BST)' => ['Europe/London'],
            'Europe/Berlin (CEST)' => ['Europe/Berlin'],
            'America/New_York (EDT)' => ['America/New_York'],
            'Asia/Tokyo (JST)' => ['Asia/Tokyo'],
            'Pacific/Auckland (NZST)' => ['Pacific/Auckland'],
        ];
    }

    // =========================================================================
    // TESTS: is_feedback_node()
    // =========================================================================

    /**
     * Test feedback node detection by type field.
     */
    public function test_is_feedback_node_by_type() {
        $node = ['id' => 'cond_1_feedback', 'type' => 'feedback', 'data' => []];
        $result = $this->call_node_completion_method('is_feedback_node', [$node]);
        $this->assertTrue($result);
    }

    /**
     * Test feedback node detection by data.label.
     */
    public function test_is_feedback_node_by_label() {
        $node = ['id' => 'cond_1', 'type' => 'condition', 'data' => ['label' => 'feedback_positive']];
        $result = $this->call_node_completion_method('is_feedback_node', [$node]);
        $this->assertTrue($result);
    }

    /**
     * Test feedback node detection by id suffix.
     */
    public function test_is_feedback_node_by_id() {
        $node = ['id' => 'condition_1_feedback', 'type' => 'custom', 'data' => ['label' => 'something']];
        $result = $this->call_node_completion_method('is_feedback_node', [$node]);
        $this->assertTrue($result);
    }

    /**
     * Test non-feedback node is not detected as feedback.
     */
    public function test_is_not_feedback_node() {
        $node = ['id' => 'condition_1', 'type' => 'condition', 'data' => ['label' => 'timed']];
        $result = $this->call_node_completion_method('is_feedback_node', [$node]);
        $this->assertFalse($result);
    }

    // =========================================================================
    // TESTS: get_restriction_paths()
    // =========================================================================

    /**
     * Test that get_restriction_paths correctly skips feedback nodes
     * and finds the timed condition in the chain.
     * This was the root cause: condition_1_feedback was not recognized
     * as feedback because it had type="feedback" but no data.label.
     */
    public function test_get_restriction_paths_skips_feedback_by_type() {
        $restrictionnodes = [
            $this->build_parent_courses_node(
                'condition_1',
                ['starting_condition'],
                ['condition_1_feedback', 'condition_2']
            ),
            $this->build_feedback_node('condition_1_feedback', 'condition_1'),
            $this->build_timed_restriction_node(
                'condition_2',
                '2026-03-16T18:00',
                null,
                ['condition_1'],
                []
            ),
        ];

        $nodemap = [];
        foreach ($restrictionnodes as $rnode) {
            $nodemap[$rnode['id']] = $rnode;
        }

        $paths
