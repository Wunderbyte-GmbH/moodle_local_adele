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
 * UC-15 — Restriction OR chain: dndnode_2's restriction has two independent
 * columns, each rooted at starting_condition:
 *   Column A (condition_1) = parent_courses  (dndnode_1 must be 'completed')
 *   Column B (condition_2) = timed           (current date within a window)
 *
 * OR semantics: if ANY column's single condition is satisfied, the column is
 * pushed onto $restrictionnodepaths → getnodestatusforrestriciton returns
 * 'inbetween' as soon as the array is non-empty.
 *
 * Fixture: simpleorconcat.json
 *   dndnode_1: single completion condition (course_completed), no restriction.
 *   dndnode_2: OR restriction (parent_courses | timed),
 *              OR completion (course_completed | manual).
 *
 * Three tests:
 *
 *   a) test_or_before_when_timed_not_active_and_parent_not_done
 *      — Timed window has not opened yet (start in the future); parent node
 *        (dndnode_1) NOT completed.
 *        Both OR columns fail → $restrictionnodepaths empty.
 *        getnodestatusforrestriciton: Column A (parent_courses) is not a timed
 *        type → istypetimedandcolumnvalid → true → isvalid=true → before_valid.
 *        Column B (timed, isbefore=true, isafter=false) → istypetimedandcolumnvalid
 *        → true → isvalid=true → before_valid.
 *        before_valid non-empty → returns 'before'.
 *        getnodestatus: walks _feedback nodes; first column head is parent_courses
 *        (non-timed, always reachable) → returns 'not_accessible'.
 *        Expected: status_restriction='before', status='not_accessible'.
 *
 *   b) test_or_inbetween_when_timed_active_parent_not_done
 *      — Timed window is active (start in past, end in future); parent node
 *        NOT completed.
 *        Column A (parent_courses): completed=false → NOT pushed.
 *        Column B (timed, active): completed=true → PUSHED.
 *        $restrictionnodepaths non-empty → getnodestatusforrestriciton returns
 *        'inbetween'; getnodestatus returns 'accessible'.
 *        Expected: status_restriction='inbetween', status='accessible'.
 *
 *   c) test_or_inbetween_when_timed_not_active_parent_done
 *      — Timed window has not opened yet (start in the future); parent node
 *        IS completed.
 *        Column A (parent_courses): completed=true → PUSHED.
 *        Column B (timed, isbefore): completed=false → NOT pushed.
 *        $restrictionnodepaths non-empty → 'inbetween' / 'accessible'.
 *        Expected: status_restriction='inbetween', status='accessible'.
 *
 * Setup:
 *   patch_node_ids() assigns real course IDs.  A two-pass helper runs the
 *   initial evaluation (creates path rows), patches timed dates in all DB
 *   records, optionally completes dndnode_1's course, then fires a fresh
 *   evaluation event.
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
 * Restriction OR chain: parent_courses OR timed tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc15_or_restriction_test extends adele_learningpath_testcase {
    /**
     * Uses the OR-chain fixture where dndnode_2.restriction has two independent
     * OR columns: parent_courses and timed.
     */
    protected function fixturefile(): string {
        return 'simpleorconcat.json';
    }

    /**
     * Wire real course IDs to the two nodes.
     *
     * dndnode_1 (course_node_id → courseids[0]):
     *   Single course_completed condition; no restriction.  No changes needed
     *   beyond setting the course ID.
     *
     * dndnode_2 (course_node_id → courseids[2]):
     *   OR restriction (parent_courses | timed) and OR completion
     *   (course_completed | manual) left as-is — the fixture provides them.
     *   Timed dates in the fixture (2026-03-*) are overwritten by the test
     *   helper before each re-evaluation, so no patching here.
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
            } else {
                // dndnode_2.
                $node['data']['course_node_id'] = [$this->courseids[2]];
            }
        }
        unset($node);
    }

    // -------------------------------------------------------------------------
    // Helper.

    /**
     * Subscribe users, run the initial evaluation, patch the timed window in
     * every stored path record, optionally complete dndnode_1, then
     * re-evaluate so updated_single sees the combined restriction state.
     *
     * Timed dates are overwritten directly in the local_adele_path_user rows
     * (inside tree.nodes[dndnode_2].restriction.nodes[condition_2].data.value)
     * so that timed::get_restriction_status() picks them up on the second pass.
     *
     * @param string $timedstart  strtotime()-compatible expression, e.g. '-7 days'.
     * @param string $timedend    strtotime()-compatible expression, e.g. '+7 days'.
     * @param bool   $completeparent  If true, mark courseids[0] complete for all users.
     */
    private function enrol_patch_and_eval(
        string $timedstart,
        string $timedend,
        bool $completeparent = false
    ): void {
        global $DB;

        // Pass 1: subscribe users and run initial evaluation.
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
                        $cn['data']['value']['start'] = date('Y-m-d\TH:i', strtotime($timedstart));
                        $cn['data']['value']['end']   = date('Y-m-d\TH:i', strtotime($timedend));
                    }
                }
                unset($cn);
            }
            unset($treenode);
            $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
        }

        // Optionally complete the parent node's course (dndnode_1 → courseids[0]).
        if ($completeparent) {
            $userpathrecords = $DB->get_records('local_adele_path_user');
            foreach ($userpathrecords as $record) {
                $this->mark_course_complete_in_db((int)$this->courseids[0], (int)$record->user_id);
            }
        }

        // Pass 2: fire fresh events from the updated records.
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
     * BEFORE: timed window has not opened yet; parent not completed.
     *
     * Both OR columns fail:
     *   Column A (parent_courses): completed=false → NOT pushed.
     *   Column B (timed, start in future → isbefore=true): completed=false → NOT pushed.
     *
     * getnodestatusforrestriciton:
     *   Column A head (parent_courses): istypetimedandcolumnvalid → true
     *     childCondition[1]=null → while skipped; childcondition=false → isvalid=true
     *     → before_valid populated.
     *   Column B head (timed, isafter=false): istypetimedandcolumnvalid → true
     *     → before_valid populated.
     *   before_valid non-empty → returns 'before'.
     *
     * getnodestatus: first _feedback column head = parent_courses (always reachable)
     *   → returns 'not_accessible'.
     *
     * Expected: status_restriction='before', status='not_accessible'.
     *
     * @return void
     */
    public function test_or_before_when_timed_not_active_and_parent_not_done(): void {
        global $DB;

        $this->enrol_patch_and_eval('+14 days', '+21 days', false);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'before',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'before' — timed not active and parent not done."
            );
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' — both OR columns fail."
            );
        }

        $this->sink->close();
    }

    /**
     * INBETWEEN via timed column: timed window is active; parent NOT completed.
     *
     * Column A (parent_courses): completed=false → NOT pushed.
     * Column B (timed, active window): completed=true → PUSHED.
     *
     * $restrictionnodepaths non-empty → getnodestatusforrestriciton returns
     * 'inbetween'; getnodestatus returns 'accessible'.
     *
     * Expected: status_restriction='inbetween', status='accessible'.
     *
     * @return void
     */
    public function test_or_inbetween_when_timed_active_parent_not_done(): void {
        global $DB;

        $this->enrol_patch_and_eval('-7 days', '+7 days', false);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'inbetween' — timed OR column passes."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' — timed column satisfied."
            );
        }

        $this->sink->close();
    }

    /**
     * INBETWEEN via parent_courses column: timed window NOT active; parent IS completed.
     *
     * Column A (parent_courses): dndnode_1 has status='completed' → completed=true → PUSHED.
     * Column B (timed, start in future → isbefore=true): completed=false → NOT pushed.
     *
     * $restrictionnodepaths non-empty (Column A pushed) → 'inbetween' / 'accessible'.
     *
     * Expected: status_restriction='inbetween', status='accessible'.
     *
     * @return void
     */
    public function test_or_inbetween_when_timed_not_active_parent_done(): void {
        global $DB;

        $this->enrol_patch_and_eval('+14 days', '+21 days', true);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'inbetween' — parent_courses OR column passes."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' — parent_courses column satisfied."
            );
        }

        $this->sink->close();
    }
}
