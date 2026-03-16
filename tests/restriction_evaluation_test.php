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
 * Unit tests for restriction evaluation logic in node_completion.
 *
 * Tests the is_condition_met() method for correct evaluation of individual
 * conditions, and check_node_restrictions() for correct AND/OR path logic.
 * Verifies that:
 * - AND-linked conditions require ALL conditions to be met
 * - OR-linked paths require at least ONE path to be fully met
 * - Unknown/missing condition labels return false (not true)
 * - Master and manual overrides bypass all conditions
 * - next_start_date is correctly set for future timed conditions
 * - Expired end dates do NOT schedule a retry
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
use ReflectionMethod;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/adele/lib.php');

/**
 * Tests for restriction evaluation logic.
 *
 * @package     local_adele
 * @category    test
 * @author      Ralf Erlebach
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\node_completion
 */
class restriction_evaluation_test extends advanced_testcase {

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
     * Format a DateTime relative to now.
     *
     * @param string $modifier e.g. '+1 hour', '-30 minutes'
     * @return string Date in Y-m-d\TH:i format
     */
    private function relative_date(string $modifier): string {
        return (new DateTime($modifier))->format('Y-m-d\TH:i');
    }

    /**
     * Build a feedback node (type="feedback", no data.label).
     *
     * @param string $id
     * @param string $parentid
     * @return array
     */
    private function build_feedback_node(string $id, string $parentid): array {
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
     * Build a parent_courses condition node.
     *
     * @param string $id
     * @param array $parentcondition
     * @param array $childcondition
     * @return array
     */
    private function build_parent_courses_node(
        string $id,
        array $parentcondition = ['starting_condition'],
        array $childcondition = []
    ): array {
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
     * Build a timed condition node.
     *
     * @param string $id
     * @param string|null $start
     * @param string|null $end
     * @param array $parentcondition
     * @param array $childcondition
     * @return array
     */
    private function build_timed_node(
        string $id,
        ?string $start = null,
        ?string $end = null,
        array $parentcondition = ['starting_condition'],
        array $childcondition = []
    ): array {
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
     * Build a node array with restrictions.
     *
     * @param array $restrictionnodes
     * @return array
     */
    private function build_node_with_restrictions(array $restrictionnodes): array {
        return [
            'id' => 'dndnode_test',
            'data' => [
                'course_node_id' => [99],
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
    private function build_userpath_record(
        int $userid = 9,
        int $learningpathid = 1,
        array $nodes = []
    ): object {
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
    // TESTS: is_condition_met() – individual condition evaluation
    // =========================================================================

    /**
     * Unknown condition label must return false.
     * This was a critical bug: previously returned true, allowing
     * premature enrolment when a condition type was not evaluated.
     */
    public function test_unknown_label_returns_false() {
        $allcriteria = [];

        $result = $this->call_method('is_condition_met', ['unknown_label', 'c1', $allcriteria]);

        $this->assertFalse($result,
            'Unknown condition label must return false to prevent premature enrolment');
    }

    /**
     * Missing condition label (empty allcriteria) must return false.
     */
    public function test_missing_label_returns_false() {
        $allcriteria = [
            'parent_courses' => [
                'c1' => ['completed' => true],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['timed', 'c2', $allcriteria]);

        $this->assertFalse($result,
            'Condition label not in allcriteria must return false');
    }

    /**
     * Timed condition with completed=true returns true.
     */
    public function test_timed_completed_returns_true() {
        $allcriteria = [
            'timed' => [
                'c1' => ['completed' => true, 'isbefore' => false, 'isafter' => false],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['timed', 'c1', $allcriteria]);

        $this->assertTrue($result);
    }

    /**
     * Timed condition with completed=false returns false.
     */
    public function test_timed_not_completed_returns_false() {
        $allcriteria = [
            'timed' => [
                'c1' => ['completed' => false, 'isbefore' => true, 'isafter' => false],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['timed', 'c1', $allcriteria]);

        $this->assertFalse($result);
    }

    /**
     * Parent_courses condition with completed=true returns true.
     */
    public function test_parent_courses_completed_returns_true() {
        $allcriteria = [
            'parent_courses' => [
                'c1' => ['completed' => true],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['parent_courses', 'c1', $allcriteria]);

        $this->assertTrue($result);
    }

    /**
     * Parent_courses condition with completed=false returns false.
     */
    public function test_parent_courses_not_completed_returns_false() {
        $allcriteria = [
            'parent_courses' => [
                'c1' => ['completed' => false],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['parent_courses', 'c1', $allcriteria]);

        $this->assertFalse($result);
    }

    /**
     * Master override as boolean true returns true.
     */
    public function test_master_boolean_true() {
        $allcriteria = ['master' => true];

        $result = $this->call_method('is_condition_met', ['master', 'any', $allcriteria]);

        $this->assertTrue($result);
    }

    /**
     * Master override as boolean false returns false.
     */
    public function test_master_boolean_false() {
        $allcriteria = ['master' => false];

        $result = $this->call_method('is_condition_met', ['master', 'any', $allcriteria]);

        $this->assertFalse($result);
    }

    /**
     * Manual condition with flat completed=true structure.
     */
    public function test_manual_flat_completed() {
        $allcriteria = ['manual' => ['completed' => true]];

        $result = $this->call_method('is_condition_met', ['manual', 'any', $allcriteria]);

        $this->assertTrue($result);
    }

    /**
     * Manual condition with flat completed=false structure.
     */
    public function test_manual_flat_not_completed() {
        $allcriteria = ['manual' => ['completed' => false]];

        $result = $this->call_method('is_condition_met', ['manual', 'any', $allcriteria]);

        $this->assertFalse($result);
    }

    /**
     * Condition id not found within the label's criteria returns false.
     */
    public function test_condition_id_not_found() {
        $allcriteria = [
            'timed' => [
                'c1' => ['completed' => true],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['timed', 'c_nonexistent', $allcriteria]);

        $this->assertFalse($result);
    }

    // =========================================================================
    // TESTS: check_node_restrictions() – single path (AND logic)
    // =========================================================================

    /**
     * Single timed condition in the future.
     * Expected: met=false, next_start_date set.
     */
    public function test_single_timed_future_not_met() {
        $futuredate = $this->relative_date('+1 hour');

        $nodearray = $this->build_node_with_restrictions([
            $this->build_timed_node('c1', $futuredate, null),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met'], 'Timed condition in future → not met');
        $this->assertEquals($futuredate, $result['next_start_date'],
            'next_start_date should be the future start date');
    }

    /**
     * Single timed condition in the past.
     * Expected: met=true, next_start_date null.
     */
    public function test_single_timed_past_met() {
        $pastdate = $this->relative_date('-1 hour');

        $nodearray = $this->build_node_with_restrictions([
            $this->build_timed_node('c1', $pastdate, null),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertTrue($result['met'], 'Timed condition in past → met');
        $this->assertNull($result['next_start_date']);
    }

    /**
     * AND-linked: parent_courses (met) AND timed (future, not met).
     * Expected: met=false, next_start_date set.
     *
     * This simulates the real-world dndnode_2 scenario:
     * condition_1 (parent_courses) → condition_1_feedback → condition_2 (timed)
     */
    public function test_and_linked_parent_met_timed_future() {
        $futuredate = $this->relative_date('+1 hour');

        $nodearray = $this->build_node_with_restrictions([
            $this->build_parent_courses_node(
                'condition_1',
                ['starting_condition'],
                ['condition_1_feedback', 'condition_2']
            ),
            $this->build_feedback_node('condition_1_feedback', 'condition_1'),
            $this->build_timed_node(
                'condition_2',
                $futuredate,
                null,
                ['condition_1'],
                []
            ),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met'],
            'AND: parent_courses met + timed future → overall not met');
        $this->assertEquals($futuredate, $result['next_start_date'],
            'next_start_date should be set for the future timed condition');
    }

    /**
     * AND-linked: parent_courses (not met) AND timed (met).
     * Expected: met=false, next_start_date null (no future timed to retry).
     */
    public function test_and_linked_parent_not_met_timed_past() {
        $pastdate = $this->relative_date('-1 hour');

        $nodearray = $this->build_node_with_restrictions([
            $this->build_parent_courses_node(
                'c1',
                ['starting_condition'],
                ['c2']
            ),
            $this->build_timed_node('c2', $pastdate, null, ['c1'], []),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        // parent_courses is not met (no completed parent in userpath),
        // so the path fails at c1 before reaching c2.
        $this->assertFalse($result['met'],
            'AND: parent_courses not met → overall not met');
        $this->assertNull($result['next_start_date'],
            'No future timed condition on the failing path');
    }

    /**
     * AND-linked: parent_courses (met) AND timed (met, past start).
     * Expected: met=true.
     */
    public function test_and_linked_both_met() {
        $pastdate = $this->relative_date('-1 hour');

        $nodearray = $this->build_node_with_restrictions([
            $this->build_timed_node(
                'c1',
                $pastdate,
                null,
                ['starting_condition'],
                ['c2']
            ),
            $this->build_timed_node(
                'c2',
                $this->relative_date('-30 minutes'),
                null,
                ['c1'],
                []
            ),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertTrue($result['met'], 'AND: both timed conditions met → overall met');
    }

    // =========================================================================
    // TESTS: check_node_restrictions() – OR logic (parallel paths)
    // =========================================================================

    /**
     * OR-linked: Path A (timed future, not met) OR Path B (timed past, met).
     * Expected: met=true (Path B satisfies).
     */
    public function test_or_linked_one_path_met() {
        $nodearray = $this->build_node_with_restrictions([
            $this->build_timed_node(
                'c_a',
                $this->relative_date('+1 hour'),
                null,
                ['starting_condition'],
                []
            ),
            $this->build_timed_node(
                'c_b',
                $this->relative_date('-1 hour'),
                null,
                ['starting_condition'],
                []
            ),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertTrue($result['met'],
            'OR: one path met → overall met');
        $this->assertNull($result['next_start_date'],
            'No retry needed when at least one path is met');
    }

    /**
     * OR-linked: Path A (timed future +1h) OR Path B (timed future +2h).
     * Neither path met. next_start_date should be the EARLIEST future date.
     */
    public function test_or_linked_no_path_met_earliest_date() {
        $future1h = $this->relative_date('+1 hour');
        $future2h = $this->relative_date('+2 hours');

        $nodearray = $this->build_node_with_restrictions([
            $this->build_timed_node(
                'c_a',
                $future1h,
                null,
                ['starting_condition'],
                []
            ),
            $this->build_timed_node(
                'c_b',
                $future2h,
                null,
                ['starting_condition'],
                []
            ),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met'], 'OR: no path met → overall not met');
        $this->assertEquals($future1h, $result['next_start_date'],
            'next_start_date should be the earliest future start date');
    }

    /**
     * OR-linked: Path A (timed future +2h) OR Path B (timed future +1h).
     * Reversed order – should still pick the earliest date.
     */
    public function test_or_linked_earliest_date_reversed_order() {
        $future1h = $this->relative_date('+1 hour');
        $future2h = $this->relative_date('+2 hours');

        $nodearray = $this->build_node_with_restrictions([
            $this->build_timed_node(
                'c_a',
                $future2h,
                null,
                ['starting_condition'],
                []
            ),
            $this->build_timed_node(
                'c_b',
                $future1h,
                null,
                ['starting_condition'],
                []
            ),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met']);
        $this->assertEquals($future1h, $result['next_start_date'],
            'Earliest date should be selected regardless of path order');
    }

    // =========================================================================
    // TESTS: check_node_restrictions() – mixed AND/OR
    // =========================================================================

    /**
     * Mixed AND/OR:
     * Path A: parent_courses (not met) → timed (future)   [AND chain, fails at c_a1]
     * Path B: timed (met, past)                             [single, succeeds]
     *
     * Expected: met=true (Path B satisfies).
     */
    public function test_mixed_and_or_one_or_path_met() {
        $nodearray = $this->build_node_with_restrictions([
            // Path A: AND chain.
            $this->build_parent_courses_node(
                'c_a1',
                ['starting_condition'],
                ['c_a1_fb', 'c_a2']
            ),
            $this->build_feedback_node('c_a1_fb', 'c_a1'),
            $this->build_timed_node(
                'c_a2',
                $this->relative_date('+1 hour'),
                null,
                ['c_a1'],
                []
            ),
            // Path B: single timed (met).
            $this->build_timed_node(
                'c_b1',
                $this->relative_date('-1 hour'),
                null,
                ['starting_condition'],
                []
            ),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertTrue($result['met'],
            'Mixed AND/OR: Path B (single timed, met) satisfies overall');
    }

    /**
     * Mixed AND/OR:
     * Path A: parent_courses (not met) → timed (future +2h)  [AND chain, fails at c_a1]
     * Path B: timed (future +1h)                               [single, not met]
     *
     * Expected: met=false, next_start_date = +1h (earliest from Path B).
     * Path A fails at parent_courses (no timed isbefore on that path).
     */
    public function test_mixed_and_or_no_path_met() {
        $future1h = $this->relative_date('+1 hour');
        $future2h = $this->relative_date('+2 hours');

        $nodearray = $this->build_node_with_restrictions([
            // Path A: AND chain (fails at parent_courses).
            $this->build_parent_courses_node(
                'c_a1',
                ['starting_condition'],
                ['c_a2']
            ),
            $this->build_timed_node('c_a2', $future2h, null, ['c_a1'], []),
            // Path B: single timed (not met).
            $this->build_timed_node(
                'c_b1',
                $future1h,
                null,
                ['starting_condition'],
                []
            ),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met']);
        $this->assertEquals($future1h, $result['next_start_date'],
            'next_start_date should be from Path B (earliest reachable timed)');
    }

    // =========================================================================
    // TESTS: check_node_restrictions() – overrides
    // =========================================================================

    /**
     * Master override bypasses all conditions.
     * Even if timed condition is in the future, master=true → met=true.
     *
     * Note: This test depends on course_restriction_status returning
     * master=true. Since we cannot easily mock that class, we test
     * the master check logic in is_condition_met() instead.
     */
    public function test_master_override_in_is_condition_met() {
        $allcriteria = ['master' => true];

        $result = $this->call_method('is_condition_met', ['master', 'any', $allcriteria]);

        $this->assertTrue($result, 'Master override should return true');
    }

    /**
     * Manual override with completed=true bypasses conditions.
     */
    public function test_manual_override_in_is_condition_met() {
        $allcriteria = ['manual' => ['completed' => true]];

        $result = $this->call_method('is_condition_met', ['manual', 'any', $allcriteria]);

        $this->assertTrue($result, 'Manual override should return true');
    }

    // =========================================================================
    // TESTS: check_node_restrictions() – expired end dates
    // =========================================================================

    /**
     * Timed condition with only an end date in the past (expired window).
     * Expected: met=false, next_start_date=null.
     * An expired window should NOT schedule a retry because the window
     * will never open again.
     */
    public function test_expired_end_date_no_retry() {
        $pastend = $this->relative_date('-1 hour');

        $nodearray = $this->build_node_with_restrictions([
            $this->build_timed_node('c1', null, $pastend),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met'], 'Expired window → not met');
        $this->assertNull($result['next_start_date'],
            'Expired end date must NOT set next_start_date (window permanently closed)');
    }

    /**
     * Timed condition with start in past and end in past (closed window).
     * Expected: met=false, next_start_date=null.
     */
    public function test_closed_window_no_retry() {
        $nodearray = $this->build_node_with_restrictions([
            $this->build_timed_node(
                'c1',
                $this->relative_date('-2 hours'),
                $this->relative_date('-1 hour')
            ),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met']);
        $this->assertNull($result['next_start_date'],
            'Closed window must NOT schedule a retry');
    }

    /**
     * Timed condition with start and end both in the future.
     * Expected: met=false, next_start_date set to start date.
     */
    public function test_future_window_schedules_retry() {
        $futurestart = $this->relative_date('+1 hour');
        $futureend = $this->relative_date('+2 hours');

        $nodearray = $this->build_node_with_restrictions([
            $this->build_timed_node('c1', $futurestart, $futureend),
        ]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met']);
        $this->assertEquals($futurestart, $result['next_start_date'],
            'Future window should schedule retry at start date');
    }

    // =========================================================================
    // TESTS: check_node_restrictions() – empty/missing restrictions
    // =========================================================================

    /**
     * Node with empty restriction nodes array.
     * Expected: met=false (no paths found, default).
     */
    public function test_empty_restriction_nodes() {
        $nodearray = $this->build_node_with_restrictions([]);

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met']);
        $this->assertNull($result['next_start_date']);
    }

    // =========================================================================
    // TESTS: is_condition_met() – edge cases
    // =========================================================================

    /**
     * Condition with completed=0 (integer zero) should be treated as false.
     */
    public function test_completed_zero_is_false() {
        $allcriteria = [
            'timed' => [
                'c1' => ['completed' => 0],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['timed', 'c1', $allcriteria]);

        $this->assertFalse($result);
    }

    /**
     * Condition with completed=1 (integer one) should be treated as true.
     */
    public function test_completed_one_is_true() {
        $allcriteria = [
            'timed' => [
                'c1' => ['completed' => 1],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['timed', 'c1', $allcriteria]);

        $this->assertTrue($result);
    }

    /**
     * Condition with completed=null should be treated as false.
     */
    public function test_completed_null_is_false() {
        $allcriteria = [
            'timed' => [
                'c1' => ['completed' => null],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['timed', 'c1', $allcriteria]);

        $this->assertFalse($result);
    }

    /**
     * Condition with empty array (no completed key) should return false.
     */
    public function test_no_completed_key_returns_false() {
        $allcriteria = [
            'timed' => [
                'c1' => ['isbefore' => true, 'isafter' => false],
            ],
        ];

        $result = $this->call_method('is_condition_met', ['timed', 'c1', $allcriteria]);

        $this->assertFalse($result);
    }
}
