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
 * UC-07 — Course-completed completion: a node's completion status transitions
 * from 'inbetween' to 'after' as the user moves from enrolled-but-incomplete
 * to having all required courses marked done in course_completions.
 *
 * Focus: dndnode_1 has no restriction (restriction = null in the fixture) so
 * it is always accessible.  Its sole completion condition is course_completed
 * with min_courses = 1.  courseids[0] is the only assigned course and the two
 * test users are enrolled in it by the base setUp().
 *
 * Two tests:
 *   a) test_completion_is_inbetween_when_enrolled_not_completed
 *      — immediately after enrollment status_completion is 'inbetween'.
 *
 *      This is intentional by design: as soon as a node's restriction conditions
 *      are met (i.e. the node is accessible), the completion status moves to
 *      'inbetween' to signal "you can work on this now", even at 0 % progress.
 *      course_completed::get_completion_status() sets
 *        completioncriteria['course_completed']['inbetween']['condition_1'] = true
 *      for any enrolled user, and getnodestatusforcompletion() returns 'inbetween'.
 *      The overall node status is 'accessible' because no completion path has
 *      fired yet.
 *
 *   b) test_completion_transitions_to_after_when_course_completed
 *      — after inserting a course_completions row via mark_course_complete_in_db()
 *        and re-evaluating with a fresh user_path_updated event,
 *        status_completion transitions to 'after' and status to 'completed'.
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
 * Course-completed completion: inbetween and after state tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_completion\course_completion_status::class)]
final class uc07_course_completed_completion_test extends adele_learningpath_testcase {
    /**
     * Uses the main fixture.  Only dndnode_1 is asserted; dndnode_2 is left
     * with its default parent_courses restriction and is not checked here.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Assign courseids[0] to dndnode_1 and set min_courses = 1 so that
     * completing the single enrolled course is the pass criterion.
     * dndnode_2 gets courseids[2] (structure unchanged).
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
                // Reduce min_courses from default 2 to 1 for a single-course node.
                foreach ($node['completion']['nodes'] as &$cn) {
                    if (isset($cn['data']['label']) && $cn['data']['label'] === 'course_completed') {
                        $cn['data']['value']['min_courses'] = 1;
                    }
                }
                unset($cn);
            } else {
                $node['data']['course_node_id'] = [$this->courseids[2]];
            }
        }
        unset($node);
    }

    /**
     * INBETWEEN: enrolled in courseids[0] but no course_completions record.
     *
     * By design, once a node's restriction is satisfied the completion status
     * immediately enters 'inbetween' — this signals "the node is accessible
     * and the user is working on it", even at 0 % progress.
     * course_completed::get_completion_status() sets
     *   completioncriteria['course_completed']['inbetween']['condition_1'] = true
     * for any enrolled user, and getnodestatusforcompletion() returns 'inbetween'.
     *
     * Because no completion path has fired, getnodestatus() returns 'accessible'
     * (restriction is null → feedback['restriction']['before'] is null →
     * is_null() branch executes).
     *
     * Expected:
     *   status_completion = 'inbetween'
     *   status            = 'accessible'
     *
     * @return void
     */
    public function test_completion_is_inbetween_when_enrolled_not_completed(): void {
        global $DB;

        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after enrollment.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_1']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'inbetween' when enrolled but course not completed."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' when course not yet complete."
            );
        }

        $this->sink->close();
    }

    /**
     * AFTER: course_completions row inserted → completion transitions to 'after'.
     *
     * Flow:
     *   1. Subscribe + first evaluation (writes user_path_relation, fires events 0–1).
     *   2. Insert course_completions row for every enrolled user via
     *      mark_course_complete_in_db(); purges the MUC cache entry so
     *      is_course_complete() reads the new row immediately.
     *   3. Re-evaluate with fresh user_path_updated events built from the
     *      stored records (same pattern as UC-03/UC-05).
     *
     * course_completed::get_completion_status() now finds
     *   $completion->is_course_complete($userid) == true
     *   → finished = 1 >= min_courses = 1 → completed['condition_1'] = true
     *
     * validatenodecompletion() fires the completion path:
     *   feedback['completion']['after'][] = after_all['condition_1']  (non-null array)
     *
     * getnodestatusforcompletion() → count($completionnodepaths) > 0 → 'after'
     * getnodestatus()              → feedback['completion']['after'] truthy  → 'completed'
     *
     * Expected:
     *   status_completion = 'after'
     *   status            = 'completed'
     *
     * @return void
     */
    public function test_completion_transitions_to_after_when_course_completed(): void {
        global $DB;

        // Step 1: Subscribe + first evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Mark courseids[0] complete for every enrolled user.
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $this->mark_course_complete_in_db((int)$this->courseids[0], (int)$record->user_id);
        }

        // Step 3: Re-evaluate with fresh events so is_course_complete() picks up
        // the new course_completions row.
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

        // Step 4: Assert dndnode_1 is now completed.
        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after course completion.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_1']['feedback'];

            $this->assertEquals(
                'after',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'after' when course is completed."
            );
            $this->assertEquals(
                'completed',
                $fb['status'],
                "User {$record->user_id}: expected 'completed' when course is completed."
            );
        }

        $this->sink->close();
    }
}
