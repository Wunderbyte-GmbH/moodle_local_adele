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
 * Unit tests for the timed restriction condition class.
 *
 * Tests the correctness of isbefore, isafter and completed flags
 * for all combinations of start/end dates (past, future, null).
 * Verifies that isbefore and isafter are never simultaneously true
 * (the original bug with dndnode_3).
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
use local_adele\course_restriction\conditions\timed;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/adele/lib.php');

/**
 * Tests for the timed restriction condition.
 *
 * @package     local_adele
 * @category    test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\course_restriction\conditions\timed
 */
class timed_condition_test extends advanced_testcase {

    /** @var timed The timed condition instance under test. */
    private timed $timed;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->timed = new timed();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Build a minimal node array containing a single timed restriction node.
     *
     * @param string $conditionid The condition node id
     * @param string|null $start Start date in Y-m-d\TH:i format or null
     * @param string|null $end End date in Y-m-d\TH:i format or null
     * @return array
     */
    private function build_node($conditionid, $start = null, $end = null): array {
        return [
            'id' => 'dndnode_test',
            'data' => ['course_node_id' => [99]],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => $conditionid,
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
     * Helper to evaluate a timed restriction and return the result for a
     * single condition node.
     *
     * @param string $conditionid
     * @param string|null $start
     * @param string|null $end
     * @return array The evaluation result for the condition node
     */
    private function evaluate($conditionid, $start, $end): array {
        $node = $this->build_node($conditionid, $start, $end);
        $result = $this->timed->get_restriction_status($node, new \stdClass());
        return $result[$conditionid];
    }

    /**
     * Format a DateTime relative to now.
     *
     * @param string $modifier e.g. '+1 hour', '-30 minutes'
     * @return string Date in Y-m-d\TH:i format
     */
    private function relative_date(string $modifier): string {
        return (new DateTime($modifier))->format('Y-m-d\TH:i');
    }

    // =========================================================================
    // TESTS: Single start date scenarios
    // =========================================================================

    /**
     * Start date in the future, no end date.
     * The time window has not opened yet.
     */
    public function test_start_in_future_no_end() {
        $result = $this->evaluate('c1', $this->relative_date('+1 hour'), null);

        $this->assertFalse($result['completed'], 'Window not yet open');
        $this->assertTrue($result['isbefore'], 'Before the start date');
        $this->assertFalse($result['isafter'], 'No end date to be after');
    }

    /**
     * Start date in the past, no end date.
     * The time window is open and never closes.
     */
    public function test_start_in_past_no_end() {
        $result = $this->evaluate('c1', $this->relative_date('-1 hour'), null);

        $this->assertTrue($result['completed'], 'Window is open');
        $this->assertFalse($result['isbefore'], 'Start date has passed');
        $this->assertFalse($result['isafter'], 'No end date to be after');
    }

    // =========================================================================
    // TESTS: Single end date scenarios
    // =========================================================================

    /**
     * End date in the future, no start date.
     * The time window is open (started at the beginning of time).
     */
    public function test_end_in_future_no_start() {
        $result = $this->evaluate('c1', null, $this->relative_date('+1 hour'));

        $this->assertTrue($result['completed'], 'Window is open');
        $this->assertFalse($result['isbefore'], 'No start date to be before');
        $this->assertFalse($result['isafter'], 'End date not yet reached');
    }

    /**
     * End date in the past, no start date.
     * The time window has closed permanently.
     * BUG FIX: Previously isbefore was true AND isafter was true.
     */
    public function test_end_in_past_no_start() {
        $result = $this->evaluate('c1', null, $this->relative_date('-1 hour'));

        $this->assertFalse($result['completed'], 'Window has closed');
        $this->assertFalse($result['isbefore'],
            'BUG FIX: isbefore must be false when no start date exists');
        $this->assertTrue($result['isafter'], 'End date has passed');
    }

    // =========================================================================
    // TESTS: Both start and end date scenarios
    // =========================================================================

    /**
     * Current time is between start and end.
     * The time window is open.
     */
    public function test_between_start_and_end() {
        $result = $this->evaluate(
            'c1',
            $this->relative_date('-1 hour'),
            $this->relative_date('+1 hour')
        );

        $this->assertTrue($result['completed'], 'Window is open');
        $this->assertFalse($result['isbefore'], 'Start date has passed');
        $this->assertFalse($result['isafter'], 'End date not yet reached');
    }

    /**
     * Both start and end in the future.
     * The time window has not opened yet.
     */
    public function test_both_in_future() {
        $result = $this->evaluate(
            'c1',
            $this->relative_date('+1 hour'),
            $this->relative_date('+2 hours')
        );

        $this->assertFalse($result['completed'], 'Window not yet open');
        $this->assertTrue($result['isbefore'], 'Before the start date');
        $this->assertFalse($result['isafter'], 'End date not yet reached');
    }

    /**
     * Both start and end in the past.
     * The time window has closed permanently.
     */
    public function test_both_in_past() {
        $result = $this->evaluate(
            'c1',
            $this->relative_date('-2 hours'),
            $this->relative_date('-1 hour')
        );

        $this->assertFalse($result['completed'], 'Window has closed');
        $this->assertFalse($result['isbefore'], 'Start date has passed');
        $this->assertTrue($result['isafter'], 'End date has passed');
    }

    // =========================================================================
    // TESTS: No dates
    // =========================================================================

    /**
     * No start date, no end date.
     * No time restriction – always open.
     */
    public function test_no_dates() {
        $result = $this->evaluate('c1', null, null);

        $this->assertTrue($result['completed'], 'No restriction – always open');
        $this->assertFalse($result['isbefore'], 'No start date');
        $this->assertFalse($result['isafter'], 'No end date');
    }

    // =========================================================================
    // TESTS: Mutual exclusivity of isbefore and isafter
    // =========================================================================

    /**
     * Verify that isbefore and isafter are NEVER both true simultaneously.
     * This was the original bug with dndnode_3 (end date in the past,
     * no start date → isbefore=true AND isafter=true).
     *
     * @dataProvider all_date_combinations_provider
     * @param string|null $start
     * @param string|null $end
     */
    public function test_isbefore_isafter_mutually_exclusive($start, $end) {
        $result = $this->evaluate('c1', $start, $end);

        $this->assertFalse(
            $result['isbefore'] && $result['isafter'],
            "isbefore and isafter must never both be true. "
            . "start=" . ($start ?? 'null') . ", end=" . ($end ?? 'null') . ", "
            . "isbefore=" . var_export($result['isbefore'], true) . ", "
            . "isafter=" . var_export($result['isafter'], true)
        );
    }

    /**
     * Data provider: all meaningful combinations of start/end dates.
     *
     * @return array
     */
    public static function all_date_combinations_provider(): array {
        $past2h = (new DateTime('-2 hours'))->format('Y-m-d\TH:i');
        $past1h = (new DateTime('-1 hour'))->format('Y-m-d\TH:i');
        $future1h = (new DateTime('+1 hour'))->format('Y-m-d\TH:i');
        $future2h = (new DateTime('+2 hours'))->format('Y-m-d\TH:i');

        return [
            'no dates'              => [null, null],
            'only start past'       => [$past1h, null],
            'only start future'     => [$future1h, null],
            'only end past'         => [null, $past1h],
            'only end future'       => [null, $future1h],
            'both past'             => [$past2h, $past1h],
            'both future'           => [$future1h, $future2h],
            'in between'            => [$past1h, $future1h],
            'start far past'        => [$past2h, $future2h],
        ];
    }

    // =========================================================================
    // TESTS: Logical consistency
    // =========================================================================

    /**
     * When completed=true, isbefore must be false.
     * You cannot be "before the window" and "inside the window" at the same time.
     *
     * @dataProvider all_date_combinations_provider
     * @param string|null $start
     * @param string|null $end
     */
    public function test_completed_implies_not_isbefore($start, $end) {
        $result = $this->evaluate('c1', $start, $end);

        if ($result['completed']) {
            $this->assertFalse($result['isbefore'],
                "completed=true implies isbefore=false. "
                . "start=" . ($start ?? 'null') . ", end=" . ($end ?? 'null'));
        }
    }

    /**
     * When completed=true, isafter must be false.
     * You cannot be "after the window" and "inside the window" at the same time.
     *
     * @dataProvider all_date_combinations_provider
     * @param string|null $start
     * @param string|null $end
     */
    public function test_completed_implies_not_isafter($start, $end) {
        $result = $this->evaluate('c1', $start, $end);

        if ($result['completed']) {
            $this->assertFalse($result['isafter'],
                "completed=true implies isafter=false. "
                . "start=" . ($start ?? 'null') . ", end=" . ($end ?? 'null'));
        }
    }

    /**
     * When isbefore=true, completed must be false.
     * If we haven't reached the start date, the condition cannot be met.
     *
     * @dataProvider all_date_combinations_provider
     * @param string|null $start
     * @param string|null $end
     */
    public function test_isbefore_implies_not_completed($start, $end) {
        $result = $this->evaluate('c1', $start, $end);

        if ($result['isbefore']) {
            $this->assertFalse($result['completed'],
                "isbefore=true implies completed=false. "
                . "start=" . ($start ?? 'null') . ", end=" . ($end ?? 'null'));
        }
    }

    /**
     * When isafter=true, completed must be false.
     * If the window has closed, the condition cannot be met.
     *
     * @dataProvider all_date_combinations_provider
     * @param string|null $start
     * @param string|null $end
     */
    public function test_isafter_implies_not_completed($start, $end) {
        $result = $this->evaluate('c1', $start, $end);

        if ($result['isafter']) {
            $this->assertFalse($result['completed'],
                "isafter=true implies completed=false. "
                . "start=" . ($start ?? 'null') . ", end=" . ($end ?? 'null'));
        }
    }

    // =========================================================================
    // TESTS: Placeholder formatting
    // =========================================================================

    /**
     * Verify that start_date placeholder is formatted as d.m.Y H:i.
     */
    public function test_start_date_placeholder_format() {
        $start = '2026-06-15T14:30';
        $result = $this->evaluate('c1', $start, null);

        $this->assertEquals('15.06.2026 14:30', $result['placeholders']['start_date']);
    }

    /**
     * Verify that end_date placeholder is formatted as d.m.Y H:i.
     */
    public function test_end_date_placeholder_format() {
        $end = '2026-06-15T14:30';
        $result = $this->evaluate('c1', null, $end);

        $this->assertEquals('15.06.2026 14:30', $result['placeholders']['end_date']);
    }

    /**
     * Verify that inbetween_info contains formatted start and end times.
     */
    public function test_inbetween_info_contains_formatted_dates() {
        $start = $this->relative_date('-1 hour');
        $end = $this->relative_date('+1 hour');
        $result = $this->evaluate('c1', $start, $end);

        $this->assertArrayHasKey('inbetween_info', $result);
        $this->assertNotFalse($result['inbetween_info']['starttime']);
        $this->assertNotFalse($result['inbetween_info']['endtime']);
        // Verify d.m.Y H:i format.
        $this->assertMatchesRegularExpression(
            '/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}$/',
            $result['inbetween_info']['starttime']
        );
        $this->assertMatchesRegularExpression(
            '/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}$/',
            $result['inbetween_info']['endtime']
        );
    }

    /**
     * Verify that inbetween_info starttime is false when no start date.
     */
    public function test_inbetween_info_no_start() {
        $result = $this->evaluate('c1', null, $this->relative_date('+1 hour'));

        $this->assertFalse($result['inbetween_info']['starttime']);
    }

    /**
     * Verify that inbetween_info endtime is false when no end date.
     */
    public function test_inbetween_info_no_end() {
        $result = $this->evaluate('c1', $this->relative_date('-1 hour'), null);

        $this->assertFalse($result['inbetween_info']['endtime']);
    }

    // =========================================================================
    // TESTS: Non-timed nodes in restriction are handled gracefully
    // =========================================================================

    /**
     * Verify that non-timed restriction nodes get a default result
     * with completed=false and inbetween_info=null.
     */
    public function test_non_timed_node_gets_default_result() {
        $node = [
            'id' => 'dndnode_test',
            'data' => ['course_node_id' => [99]],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'cond_parent',
                        'type' => 'condition',
                        'data' => [
                            'label' => 'parent_courses',
                            'value' => [],
                        ],
                        'parentCondition' => ['starting_condition'],
                        'childCondition' => [],
                    ],
                ],
            ],
        ];

        $result = $this->timed->get_restriction_status($node, new \stdClass());

        $this->assertArrayHasKey('cond_parent', $result);
        $this->assertFalse($result['cond_parent']['completed']);
        $this->assertNull($result['cond_parent']['inbetween_info']);
    }

    // =========================================================================
    // TESTS: isvaliddate()
    // =========================================================================

    /**
     * Valid date string returns a DateTime object.
     */
    public function test_isvaliddate_valid() {
        $result = $this->timed->isvaliddate('2026-06-15T14:30');
        $this->assertInstanceOf(\DateTime::class, $result);
    }

    /**
     * Invalid date string returns false.
     */
    public function test_isvaliddate_invalid() {
        $result = $this->timed->isvaliddate('not-a-date');
        $this->assertFalse($result);
    }

    /**
     * Null date string returns false.
     */
    public function test_isvaliddate_null() {
        $result = $this->timed->isvaliddate(null);
        $this->assertFalse($result);
    }

    /**
     * Empty string returns false.
     */
    public function test_isvaliddate_empty() {
        $result = $this->timed->isvaliddate('');
        $this->assertFalse($result);
    }

    /**
     * Date with wrong format returns false.
     */
    public function test_isvaliddate_wrong_format() {
        $result = $this->timed->isvaliddate('15.06.2026 14:30');
        $this->assertFalse($result);
    }

    /**
     * Date with correct custom format returns DateTime.
     */
    public function test_isvaliddate_custom_format() {
        $result = $this->timed->isvaliddate('15.06.2026 14:30', 'd.m.Y H:i');
        $this->assertInstanceOf(\DateTime::class, $result);
    }
}
