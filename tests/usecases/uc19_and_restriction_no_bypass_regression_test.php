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
 * UC-19 — Regression: AND restriction must NOT grant access when only one
 * condition in the chain is satisfied.
 *
 * Customer bug report (2026-03):
 *   "node a - node b: node a has completion not yet achieved and node b has
 *    restriction set as parent completed AND date requirements matched.
 *    He claims the user is getting access to the second node even though the
 *    completion for node a is not met."
 *
 * Root cause investigation:
 *   In an AND restriction chain (condition_2.parentCondition = ['condition_1']),
 *   the evaluation loop walks condition_1 → condition_2 in sequence.  If
 *   condition_1 (parent_courses) fails, $failedrestriction is set to true.
 *   The loop continues to condition_2 (timed), and if the timed window is
 *   active $validationcondition becomes true.  The final gate:
 *     if ($validationcondition && !$failedrestriction)
 *   correctly rejects the column because $failedrestriction is still true.
 *   $restrictionnodepaths therefore remains empty → getnodestatus returns
 *   'not_accessible'.
 *
 *   A possible OR-chain misunderstanding can also reproduce the symptom: if
 *   the LP is built as two independent OR columns (parent_courses | timed),
 *   an active timed column alone is enough to satisfy the restriction even
 *   when the parent is not done.  This is correct OR behaviour (documented in
 *   UC-15), but may be unexpected to users who intended AND semantics.
 *
 * This test pins the correct AND-chain behaviour so that any future regression
 * that causes a bypassed restriction is immediately caught.
 *
 * Fixture: simpleconcatlp.json (AND chain: parent_courses → timed in one column)
 *
 * Three scenarios:
 *
 *   a) test_and_restriction_not_accessible_when_timed_active_parent_not_done
 *      — The timed window is active (start in past, end in future).
 *        The parent node (dndnode_1) is NOT completed.
 *        AND column: parent_courses fails → $failedrestriction=true.
 *        Even though timed passes, the overall column is NOT pushed.
 *        Expected: status='not_accessible'.   ← the regression guard
 *
 *   b) test_and_restriction_not_accessible_when_timed_not_active_parent_done
 *      — The timed window has not opened yet (start in future).
 *        The parent node IS completed.
 *        AND column: parent_courses passes; timed (isbefore) → completed=false.
 *        At end of column walk: validationcondition=false → NOT pushed.
 *        Expected: status='not_accessible'.
 *
 *   c) test_and_restriction_accessible_when_both_conditions_met
 *      — The timed window is active; the parent node IS completed.
 *        Both conditions pass → column pushed → status='accessible'.
 *        Expected: status='accessible'.
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
 * Regression: AND restriction does not grant access when parent node not done.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc19_and_restriction_no_bypass_regression_test extends adele_learningpath_testcase {
    /**
     * AND-chain fixture: dndnode_2.restriction is parent_courses → timed
     * in a single column.
     */
    protected function fixturefile(): string {
        return 'simpleconcatlp.json';
    }

    /**
     * Assign real course IDs and strip dndnode_1's completion to a single
     * course_completed condition (remove the modquiz AND condition from the
     * original fixture) so that a single mark_course_complete_in_db() call
     * is sufficient to flip dndnode_1 to 'completed'.
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_1') {
                $node['data']['course_node_id'] = [$this->courseids[0]];

                // Remove the AND pointer from condition_1 to condition_2 so
                // that course_completed is the sole completion criterion.
                foreach ($node['completion']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['childCondition'] = ['condition_1_feedback'];
                        if (isset($cn['data']['value']['min_courses'])) {
                            $cn['data']['value']['min_courses'] = 1;
                        }
                    }
                }
                unset($cn);

                // Remove condition_2 (modquiz) node entirely.
                $node['completion']['nodes'] = array_values(array_filter(
                    $node['completion']['nodes'],
                    fn($cn) => $cn['id'] !== 'condition_2'
                ));
            } else {
                // dndnode_2: AND restriction chain intact.
                $node['data']['course_node_id'] = [$this->courseids[2]];
            }
        }
        unset($node);
    }

    // -------------------------------------------------------------------------
    // Helper.

    /**
     * Subscribe users, run initial evaluation, patch the timed condition_2
     * dates in every stored path record, optionally complete dndnode_1, then
     * re-evaluate.
     *
     * @param string $start          strtotime()-compatible expression.
     * @param string $end            strtotime()-compatible expression.
     * @param bool   $completeparent If true, mark courseids[0] complete for all users.
     */
    private function enrol_and_set_dates(string $start, string $end, bool $completeparent = false): void {
        global $DB;

        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Patch timed condition_2 dates in every stored path record.
        $records = $DB->get_records('local_adele_path_user');
        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            foreach ($json['tree']['nodes'] as &$treenode) {
                if ($treenode['id'] !== 'dndnode_2') {
                    continue;
                }
                foreach ($treenode['restriction']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_2') {
                        $cn['data']['value']['start'] = date('Y-m-d\TH:i', strtotime($start));
                        $cn['data']['value']['end']   = date('Y-m-d\TH:i', strtotime($end));
                    }
                }
                unset($cn);
            }
            unset($treenode);
            $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
        }

        if ($completeparent) {
            $userpathrecords = $DB->get_records('local_adele_path_user');
            foreach ($userpathrecords as $record) {
                $this->mark_course_complete_in_db((int)$this->courseids[0], (int)$record->user_id);
            }
        }

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
     * REGRESSION GUARD: timed active but parent NOT done → NOT accessible.
     *
     * This test directly covers the customer bug report.  When timed is the
     * second condition in an AND column, its true value must NOT bypass the
     * failed parent_courses check earlier in the same column.
     *
     * AND walk:
     *   condition_1 (parent_courses): completed=false → failedrestriction=true
     *   condition_2 (timed, active): completed=true
     *   Final gate: validationcondition=true && !failedrestriction=false → NOT pushed
     *
     * Expected: status_restriction='before', status='not_accessible'.
     *
     * @return void
     */
    public function test_and_restriction_not_accessible_when_timed_active_parent_not_done(): void {
        global $DB;

        $this->enrol_and_set_dates('-7 days', '+7 days', false);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "REGRESSION: User {$record->user_id}: dndnode_2 must NOT be accessible " .
                "when timed is active but parent node (dndnode_1) is not yet completed."
            );
        }

        $this->sink->close();
    }

    /**
     * Timed window not yet open; parent IS completed → column still fails
     * because timed evaluates false (isbefore) → NOT pushed.
     *
     * AND walk:
     *   condition_1 (parent_courses): completed=true → no failedrestriction
     *   condition_2 (timed, isbefore): completed=false → failedrestriction=true
     *   Final gate: validationcondition=false → NOT pushed
     *
     * Expected: status_restriction='before', status='not_accessible'.
     *
     * @return void
     */
    public function test_and_restriction_not_accessible_when_timed_not_active_parent_done(): void {
        global $DB;

        $this->enrol_and_set_dates('+14 days', '+21 days', true);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: dndnode_2 must NOT be accessible " .
                "when timed window has not opened yet, even if parent is done."
            );
        }

        $this->sink->close();
    }

    /**
     * Both conditions met: timed active AND parent done → column pushed → accessible.
     *
     * This is the positive control — the AND chain only opens the node when
     * ALL conditions in the column are satisfied simultaneously.
     *
     * Expected: status_restriction='inbetween', status='accessible'.
     *
     * @return void
     */
    public function test_and_restriction_accessible_when_both_conditions_met(): void {
        global $DB;

        $this->enrol_and_set_dates('-7 days', '+7 days', true);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'inbetween' — both AND conditions met."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' — full AND column satisfied."
            );
        }

        $this->sink->close();
    }
}
