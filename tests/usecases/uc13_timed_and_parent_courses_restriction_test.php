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
 * UC-13 — Restriction AND chain: dndnode_2's restriction is a single column
 * with two conditions in sequence:
 *   condition_1 = parent_courses  (dndnode_1 must be completed)
 *   condition_2 = timed           (current date within the window)
 *
 * Both conditions must pass for the column to be satisfied.  The AND wiring is
 * expressed in the fixture (simpleconcatlp.json):
 *   condition_1.childCondition  = ['condition_1_feedback', 'condition_2']
 *   condition_2.parentCondition = ['condition_1']        ← NOT starting_condition
 *
 * Three tests:
 *   a) test_and_chain_before_when_timed_active_parent_not_done
 *      — timed window active, parent node NOT yet completed.
 *        restriction AND walk: parent_courses fails → failedrestriction=true;
 *        timed active → validationcondition=true.  Column not pushed.
 *        getnodestatusforrestriciton → 'before' (before_valid populated).
 *        Expected: status_restriction='before', status='not_accessible'.
 *
 *   b) test_and_chain_inbetween_when_timed_active_parent_done
 *      — timed window active, parent node completed.
 *        Both conditions pass → column pushed → restrictionnodepaths non-empty.
 *        getnodestatusforrestriciton → 'inbetween'.
 *        Expected: status_restriction='inbetween', status='accessible'.
 *
 *   c) test_and_chain_after_when_timed_window_expired
 *      — timed window has expired (both start and end in the past).
 *        istypetimedandcolumnvalid(condition_2=timed, isafter=true) → false.
 *        getnodestatusforrestriciton → 'after' (before_valid empty).
 *        Expected: status_restriction='after', status='not_accessible'.
 *        (Same strpos(label,'timed')=0 falsy-zero cosmetic issue as UC-02/UC-06.)
 *
 * Setup: patch_node_ids() simplifies dndnode_1's completion to course_completed
 * only (modquiz AND condition stripped) so marking courseids[0] complete is
 * sufficient to flip dndnode_1 to 'completed'.
 *
 * Fixture: simpleconcatlp.json
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
 * Restriction AND chain: parent_courses AND timed tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc13_timed_and_parent_courses_restriction_test extends adele_learningpath_testcase {
    /**
     * Uses the AND-chain fixture where dndnode_2.restriction is
     * parent_courses → timed in a single column.
     */
    protected function fixturefile(): string {
        return 'simpleconcatlp.json';
    }

    /**
     * Wire real course IDs and simplify dndnode_1's completion.
     *
     * dndnode_1:
     *   - courseids[0]
     *   - completion stripped to course_completed only (modquiz AND condition
     *     removed) so a single mark_course_complete_in_db() call is enough
     *     to flip dndnode_1 to 'completed'.
     *
     * dndnode_2:
     *   - courseids[2]
     *   - restriction AND chain left as-is (parent_courses → timed).
     *     Timed dates are placeholder; each test patches them in the DB.
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

                // Set min_courses=1 and remove the AND pointer to condition_2
                // so that course_completed is the sole completion criterion.
                foreach ($node['completion']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['childCondition'] = ['condition_1_feedback'];
                        if (isset($cn['data']['value']['min_courses'])) {
                            $cn['data']['value']['min_courses'] = 1;
                        }
                    }
                }
                unset($cn);

                // Remove condition_2 (modquiz) node from completion entirely.
                $node['completion']['nodes'] = array_values(array_filter(
                    $node['completion']['nodes'],
                    fn($cn) => $cn['id'] !== 'condition_2'
                ));
            } else {
                // dndnode_2: restriction AND chain (parent_courses → timed) intact.
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
     * re-evaluate so updated_single sees the final combined state.
     *
     * The timed dates are written into tree.nodes (the LP definition stored
     * inside local_adele_path_user.json) so that get_restriction_status()
     * picks them up during the second evaluation pass.
     *
     * When $completeparent=true a course_completions row is inserted for
     * courseids[0] before the re-evaluation so that dndnode_1 is evaluated
     * as 'completed' first, allowing parent_courses on dndnode_2 to succeed.
     *
     * @param string $start         strtotime()-compatible expression for timed start.
     * @param string $end           strtotime()-compatible expression for timed end.
     * @param bool   $completeparent If true, mark courseids[0] complete for all users.
     */
    private function enrol_and_set_dates(string $start, string $end, bool $completeparent = false): void {
        global $DB;

        // Step 1: Subscribe + initial evaluation (creates user_path_relation rows).
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Overwrite condition_2 (timed) dates in every stored path record.
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

        // Step 3: Optionally complete the parent node (dndnode_1).
        if ($completeparent) {
            $userpathrecords = $DB->get_records('local_adele_path_user');
            foreach ($userpathrecords as $record) {
                $this->mark_course_complete_in_db((int)$this->courseids[0], (int)$record->user_id);
            }
        }

        // Step 4: Re-evaluate with fresh events carrying the updated path records.
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
     * BEFORE: timed window is active but the parent node is NOT completed.
     *
     * restriction AND walk (relation_update.php lines 86-163):
     *   condition_1 (parent_courses): completed=false → failedrestriction=true,
     *                                 validationcondition=false
     *   advance → condition_2 (timed, active window): completed=true,
     *                                 validationcondition=true
     *   End check: validationcondition=true && !failedrestriction=false → NOT pushed
     *
     * getnodestatusforrestriciton():
     *   Walks starting_condition nodes → condition_1 (parent_courses):
     *     istypetimedandcolumnvalid → true  (switch default case)
     *     childconditionid = childCondition[1] = 'condition_2'
     *     istypetimedandcolumnvalid(condition_2=timed, isafter=false) → true
     *     → while continues; condition_2.childCondition=[] → null → while exits
     *     → childcondition=null → isvalid stays true
     *   → before_valid populated → returns 'before'
     *
     * getnodestatus():
     *   restriction nodes walked via _feedback nodes → condition_1_feedback
     *   → nextid='condition_1' (parent_courses, not timed) → $reachablecolumn=true
     *   → returns 'not_accessible' immediately on first while iteration
     *
     * Expected:
     *   status_restriction = 'before'
     *   status             = 'not_accessible'
     *
     * @return void
     */
    public function test_and_chain_before_when_timed_active_parent_not_done(): void {
        global $DB;

        $this->enrol_and_set_dates('-7 days', '+7 days', false);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'before',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'before' — timed active but parent not done."
            );
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' — AND chain fails at parent_courses."
            );
        }

        $this->sink->close();
    }

    /**
     * INBETWEEN: timed window is active AND parent node is completed.
     *
     * restriction AND walk:
     *   condition_1 (parent_courses): completed=true  → no failedrestriction
     *   condition_2 (timed, active):  completed=true
     *   End check: true && !false → pushed → restrictionnodepaths non-empty
     *
     * getnodestatusforrestriciton() → 'inbetween'  (non-empty paths short-circuit)
     * getnodestatus()               → 'accessible' ($restrictionnodepaths truthy)
     *
     * Flow: mark_course_complete_in_db writes a course_completions row for
     * courseids[0].  On re-evaluation updated_single processes dndnode_1 first
     * (first in tree.nodes), evaluates it as 'completed', and persists that in
     * user_path_relation.  When dndnode_2 is then evaluated, parent_courses reads
     * dndnode_1.feedback.status = 'completed' and is satisfied.
     *
     * Expected:
     *   status_restriction = 'inbetween'
     *   status             = 'accessible'
     *
     * @return void
     */
    public function test_and_chain_inbetween_when_timed_active_parent_done(): void {
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
                "User {$record->user_id}: expected 'inbetween' — both AND conditions satisfied."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' — restriction column passes."
            );
        }

        $this->sink->close();
    }

    /**
     * AFTER: timed window has expired — the AND column is permanently closed.
     *
     * restriction AND walk:
     *   condition_2 (timed, expired): isafter=true → completed=false,
     *                                 validationcondition=false
     *   End: not pushed regardless of parent state
     *
     * getnodestatusforrestriciton():
     *   istypetimedandcolumnvalid(condition_1=parent_courses) → true (default)
     *   childconditionid = 'condition_2'
     *   istypetimedandcolumnvalid(condition_2=timed, isafter=true) → false
     *   → while condition fails immediately; childcondition=condition_2 (non-null)
     *   → isvalid=false → before_valid stays empty → returns 'after'
     *
     * getnodestatus():
     *   strpos('timed', 'timed') = 0 (falsy) → $hastimedcondition never set
     *   → $reachablecolumn=true → returns 'not_accessible'
     *   (Same strpos falsy-zero cosmetic issue documented in UC-02 and UC-06.)
     *
     * Expected:
     *   status_restriction = 'after'
     *   status             = 'not_accessible'
     *
     * @return void
     */
    public function test_and_chain_after_when_timed_window_expired(): void {
        global $DB;

        $this->enrol_and_set_dates('-14 days', '-1 day', false);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'after',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'after' — timed window expired closes the AND column."
            );
            // getnodestatus() returns 'not_accessible' rather than 'closed' due to
            // strpos('timed', 'timed') === 0 being falsy (see class docblock).
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' (strpos falsy-zero; see docblock)."
            );
        }

        $this->sink->close();
    }
}
