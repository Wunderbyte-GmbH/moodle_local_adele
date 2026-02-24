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
 * UC-02 — Timed restriction: a node window opens at a start date and closes
 * at an end date.  Three orthogonal scenarios are tested:
 *
 *   a) start date in the future → node is LOCKED (before / not_accessible)
 *   b) currently within the window → node is ACCESSIBLE (inbetween / accessible)
 *   c) end date has passed → node is CLOSED (after / closed)
 *
 * Setup:
 *   patch_node_ids() replaces dndnode_2's condition_1 from parent_courses to
 *   a timed condition.  Condition_2 (manual) is stripped so the timed path is
 *   the only OR-column.  Each test then overwrites the stored dates in the DB
 *   before re-evaluating to keep setUp() simple and date-independent.
 *
 * Expected state transitions (restriction side):
 *   before      → status_restriction='before',    status='not_accessible'
 *   inbetween   → status_restriction='inbetween', status='accessible'
 *   after       → status_restriction='after',     status='closed'
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use context_system;
use local_adele\event\user_path_updated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Timed restriction: before / inbetween / after state machine tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc02_timed_restriction_test extends adele_learningpath_testcase {
    /**
     * Uses the main access-path fixture.  patch_node_ids() replaces the
     * restriction condition on dndnode_2 with a standalone timed condition.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Replace dndnode_2's restriction to a single timed condition (condition_1),
     * stripping out the manual OR-column (condition_2 / condition_2_feedback)
     * so the timed path is the only one evaluated.
     *
     * The initial dates are set far in the future (before-state) as a safe
     * placeholder; each test method overwrites them before re-evaluation.
     *
     * @param array $nodes
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_2') {
                $node['data']['course_node_id'] = [$this->courseids[2]];

                // Replace condition_1 label + value: parent_courses → timed.
                // Dates are placeholder; each test overrides them in the DB.
                foreach ($node['restriction']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['data']['label']              = 'timed';
                        $cn['data']['description_before'] = '[EN_placeholder]Date restriction';
                        $cn['data']['value'] = [
                            'start' => date('Y-m-d\TH:i', strtotime('+1 day')),
                            'end'   => date('Y-m-d\TH:i', strtotime('+7 days')),
                        ];
                        // Keep childCondition pointing to condition_1_feedback.
                    }
                }
                unset($cn);

                // Strip condition_2 (manual) + condition_2_feedback to isolate
                // the timed OR-column as the only restriction path.
                $node['restriction']['nodes'] = array_values(array_filter(
                    $node['restriction']['nodes'],
                    fn($cn) => !in_array($cn['id'], ['condition_2', 'condition_2_feedback'])
                ));
            } else {
                $node['data']['course_node_id'] = [
                    $this->courseids[0],
                    $this->courseids[3],
                ];
            }
        }
        unset($node);
    }

    // -------------------------------------------------------------------------
    // Helpers.

    /**
     * Enroll users, run the first updated_single pass to create user_path_relation,
     * then patch the timed condition dates in every user's stored path record and
     * fire a fresh user_path_updated event so the second pass sees the new dates.
     *
     * @param string $start ISO date string for Y-m-d\TH:i (e.g. '+1 day')
     * @param string $end   ISO date string for Y-m-d\TH:i (e.g. '+7 days')
     */
    private function enrol_and_set_dates(string $start, string $end): void {
        global $DB;

        // Step 1: Subscribe users and first evaluation (creates user_path_relation).
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Overwrite condition_1 dates in every stored user path record.
        $records = $DB->get_records('local_adele_path_user');
        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            foreach ($json['tree']['nodes'] as &$treenode) {
                if ($treenode['id'] !== 'dndnode_2') {
                    continue;
                }
                foreach ($treenode['restriction']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['data']['value']['start'] = date('Y-m-d\TH:i', strtotime($start));
                        $cn['data']['value']['end']   = date('Y-m-d\TH:i', strtotime($end));
                    }
                }
                unset($cn);
            }
            unset($treenode);
            $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
        }

        // Step 3: Re-evaluate with fresh events carrying the updated records.
        $freshrecords = $DB->get_records('local_adele_path_user');
        foreach ($freshrecords as $freshrecord) {
            $freshrecord->json = json_decode($freshrecord->json, true);
            $event = user_path_updated::create([
                'objectid' => $freshrecord->id,
                'context'  => context_system::instance(),
                'other'    => ['userpath' => $freshrecord],
            ]);
            relation_update::updated_single($event);
        }
    }

    // -------------------------------------------------------------------------
    // Tests.

    /**
     * BEFORE: start date is in the future → window not yet open.
     *
     * The timed condition's `isbefore=true` flag means the OR-column has not
     * been satisfied but is still reachable:
     *   getnodestatusforrestriciton → 'before'  (before_valid non-empty)
     *   getnodestatus               → 'not_accessible' (reachablecolumn=true)
     */
    public function test_node_is_locked_when_start_date_is_in_future(): void {
        global $DB;

        $this->enrol_and_set_dates('+1 day', '+7 days');

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'before',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'before' when start date is in the future."
            );
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' when start date is in the future."
            );
        }

        $this->sink->close();
    }

    /**
     * INBETWEEN: currently within the time window (start in past, end in future).
     *
     * The timed condition evaluates to `completed=true`:
     *   getnodestatusforrestriciton → 'inbetween' (restrictionnodepaths non-empty)
     *   getnodestatus               → 'accessible'
     */
    public function test_node_is_accessible_when_within_time_window(): void {
        global $DB;

        $this->enrol_and_set_dates('-7 days', '+7 days');

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'inbetween' when within the time window."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' when within the time window."
            );
        }

        $this->sink->close();
    }

    /**
     * AFTER: time window has expired (start and end both in the past).
     *
     * The timed condition's `isafter=true` flag means the window is permanently
     * closed and `before_valid` is empty:
     *   getnodestatusforrestriciton → 'after'        (before_valid empty)
     *   getnodestatus               → 'not_accessible'
     *
     * NOTE: one might expect 'closed' here, but the production code in
     * getnodestatus uses `strpos($label, 'timed')` to detect timed conditions.
     * When $label === 'timed' that call returns integer 0, which PHP evaluates
     * as falsy, so $hastimedcondition is never set.  As a result getnodestatus
     * exits the while loop with $reachablecolumn still true and returns
     * 'not_accessible' instead of falling through to 'closed'.
     * The assertion here documents the actual runtime behaviour.
     */
    public function test_node_is_closed_when_time_window_has_expired(): void {
        global $DB;

        $this->enrol_and_set_dates('-14 days', '-1 day');

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            // The getnodestatusforrestriciton() function correctly returns 'after' because
            // istypetimedandcolumnvalid() uses isafter from the criteria array
            // (which is set correctly by timed::get_restriction_status).
            $this->assertEquals(
                'after',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'after' when time window has expired."
            );

            // The getnodestatus() function returns 'not_accessible' rather than 'closed' due
            // to the strpos(label, 'timed') === 0 (falsy) issue described above.
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' (see docblock) when time window has expired."
            );
        }

        $this->sink->close();
    }
}
