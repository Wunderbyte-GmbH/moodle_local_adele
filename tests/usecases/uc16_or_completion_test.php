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
 * UC-16 — Completion OR chain: dndnode_2's completion has two independent
 * columns, each rooted at starting_condition:
 *   Column A (condition_1) = course_completed  (dndnode_2's course is done)
 *   Column B (condition_2) = manual            (teacher has ticked the checkbox)
 *
 * OR semantics: if ANY column's condition is satisfied, that column is pushed
 * onto $completionnodepaths, which causes getnodestatusforcompletion to return
 * 'after' and getnodestatus to return 'completed'.
 *
 * Fixture: simpleorconcat.json
 *   dndnode_1: single completion (course_completed), no restriction.
 *   dndnode_2: OR completion (course_completed | manual),
 *              OR restriction (parent_courses | timed).
 *
 * All three tests keep the timed window in the future (not active) so the
 * restriction is satisfied exclusively via the parent_courses OR column
 * (complete dndnode_1 first).  This isolates completion OR from restriction.
 *
 * Three tests:
 *
 *   a) test_or_completion_before_when_no_completion_met
 *      — Restriction satisfied (parent done), but neither course_completed nor
 *        manual is set.
 *        Both OR columns fail → $completionnodepaths empty.
 *        course_completed sets inbetween=true via progress ?? 0 (0 !== null)
 *        even without enrolment → getnodestatusforcompletion returns 'inbetween'.
 *        getnodestatus: $restrictionnodepaths truthy → 'accessible'.
 *        Expected: status_completion='inbetween', status='accessible'.
 *
 *   b) test_or_completion_after_when_course_completed
 *      — Restriction satisfied; dndnode_2's course is marked complete.
 *        Column A (course_completed): validationcondition=true → PUSHED.
 *        $completionnodepaths non-empty → 'after' / 'completed'.
 *        Expected: status_completion='after', status='completed'.
 *
 *   c) test_or_completion_after_when_manual_set
 *      — Restriction satisfied; manual flags (manualcompletion=true,
 *        manualcompletionvalue=true) are set on dndnode_2.
 *        Column B (manual): completioncriteria['manual']['completed']=true → PUSHED.
 *        $completionnodepaths non-empty → 'after' / 'completed'.
 *        Expected: status_completion='after', status='completed'.
 *
 * Setup:
 *   patch_node_ids() assigns real course IDs.  A helper:
 *   1) Subscribes users and runs an initial evaluation.
 *   2) Completes dndnode_1's course (makes restriction pass via parent_courses).
 *   3) Optionally completes dndnode_2's course OR patches manual flags.
 *   4) Re-evaluates.
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
 * Completion OR chain: course_completed OR manual tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_completion\course_completion_status::class)]
final class uc16_or_completion_test extends adele_learningpath_testcase {
    /**
     * Uses the OR-chain fixture where dndnode_2.completion has two independent
     * OR columns: course_completed and manual.
     */
    protected function fixturefile(): string {
        return 'simpleorconcat.json';
    }

    /**
     * Wire real course IDs to the two nodes.
     *
     * dndnode_1 (courseids[0]): single course_completed condition, no restriction.
     * dndnode_2 (courseids[2]): OR restriction + OR completion as-is from fixture.
     *
     * Timed dates in the fixture (2026-03-*) remain set to a future window so
     * the timed OR column for restriction never fires; restriction is satisfied
     * exclusively by parent_courses (dndnode_1 completed).
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
     * Subscribe users, run the initial evaluation, complete dndnode_1 (so the
     * parent_courses OR restriction column for dndnode_2 is satisfied), then
     * optionally complete dndnode_2's course and/or set manual flags, and
     * finally fire a fresh evaluation pass.
     *
     * The timed restriction window (condition_2) is in the fixture as a 2026
     * range, which is in the future at test time and therefore never fires.
     *
     * @param bool $completechild  If true, mark courseids[2] complete for all users.
     * @param bool $manualset      If true, patch manualcompletion + manualcompletionvalue
     *                             on dndnode_2 in all stored path records.
     */
    private function enrol_complete_and_eval(
        bool $completechild = false,
        bool $manualset     = false
    ): void {
        global $DB;

        // Pass 1: subscribe users and run initial evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Complete dndnode_1's course for all users (satisfies parent_courses restriction).
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $this->mark_course_complete_in_db((int)$this->courseids[0], (int)$record->user_id);
        }

        // Optionally complete dndnode_2's course.
        if ($completechild) {
            $userpathrecords = $DB->get_records('local_adele_path_user');
            foreach ($userpathrecords as $record) {
                $this->mark_course_complete_in_db((int)$this->courseids[2], (int)$record->user_id);
            }
        }

        // Optionally set manual completion flags on dndnode_2 in all DB records.
        if ($manualset) {
            $records = $DB->get_records('local_adele_path_user');
            foreach ($records as $record) {
                $json = json_decode($record->json, true);
                foreach ($json['tree']['nodes'] as &$treenode) {
                    if ($treenode['id'] === 'dndnode_2') {
                        $treenode['data']['manualcompletion']      = true;
                        $treenode['data']['manualcompletionvalue'] = true;
                    }
                }
                unset($treenode);
                $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
            }
        }

        // Pass 2: fire fresh evaluation events from the (possibly updated) records.
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
     * INBETWEEN: restriction satisfied (parent done), but no completion condition fully met.
     *
     * Both completion OR columns fail to push:
     *   Column A (course_completed): courseids[2] not complete → validationcondition=false → NOT pushed.
     *   Column B (manual): manualcompletion not set → false → NOT pushed.
     *
     * $completionnodepaths empty → getnodestatusforcompletion does not return 'after'.
     *
     * However, course_completed::get_completion_status() sets inbetween=true for the
     * condition_1 node because progress::get_course_progress_percentage() receives the
     * null-coalescing default (0%), and 0 !== null → $isinbetween=true.  Therefore
     * $completioncriteria['course_completed']['inbetween']['condition_1'] = true →
     * getnodestatusforcompletion returns 'inbetween' (not 'before').
     *
     * $restrictionnodepaths non-empty (parent_courses pushed) → getnodestatus 'accessible'.
     *
     * Expected: status_completion='inbetween', status='accessible'.
     *
     * @return void
     */
    public function test_or_completion_before_when_no_completion_met(): void {
        global $DB;

        $this->enrol_complete_and_eval(false, false);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'inbetween' — course_completed inbetween flag set by progress check."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' — restriction passes, completion pending."
            );
        }

        $this->sink->close();
    }

    /**
     * AFTER via course_completed column: restriction satisfied; dndnode_2 course complete.
     *
     * Column A (course_completed): courseids[2] is complete → validationcondition=true → PUSHED.
     * Column B (manual): manualcompletion not set → false (irrelevant, already pushed).
     *
     * $completionnodepaths non-empty (Column A) → getnodestatusforcompletion 'after'.
     * getnodestatus: feedback['completion']['after'] truthy → 'completed'.
     *
     * Expected: status_completion='after', status='completed'.
     *
     * @return void
     */
    public function test_or_completion_after_when_course_completed(): void {
        global $DB;

        $this->enrol_complete_and_eval(true, false);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'after',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'after' — course_completed OR column satisfied."
            );
            $this->assertEquals(
                'completed',
                $fb['status'],
                "User {$record->user_id}: expected 'completed' — course_completed column pushed."
            );
        }

        $this->sink->close();
    }

    /**
     * AFTER via manual column: restriction satisfied; manual flags set on dndnode_2.
     *
     * Column A (course_completed): courseids[2] not complete → false → NOT pushed.
     * Column B (manual): manualcompletion=true AND manualcompletionvalue=true →
     *   completioncriteria['manual']['completed'] = true → PUSHED.
     *
     * $completionnodepaths non-empty (Column B) → getnodestatusforcompletion 'after'.
     * getnodestatus → 'completed'.
     *
     * Expected: status_completion='after', status='completed'.
     *
     * @return void
     */
    public function test_or_completion_after_when_manual_set(): void {
        global $DB;

        $this->enrol_complete_and_eval(false, true);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'after',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'after' — manual OR column satisfied."
            );
            $this->assertEquals(
                'completed',
                $fb['status'],
                "User {$record->user_id}: expected 'completed' — manual column pushed."
            );
        }

        $this->sink->close();
    }
}
