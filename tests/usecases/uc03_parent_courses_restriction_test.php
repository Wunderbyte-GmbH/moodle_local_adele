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
 * UC-03 — Parent-courses restriction: dndnode_2 is locked until dndnode_1 is
 * marked as completed by the user.  The manual OR-column is stripped from the
 * fixture so parent_courses is the sole restriction path.
 *
 * Two tests:
 *   a) test_node_is_locked_before_parent_is_complete
 *      — immediately after enrollment, dndnode_2 must be 'before'/'not_accessible'
 *        because dndnode_1 has no completion record yet.
 *   b) test_node_unlocks_after_parent_is_complete
 *      — after marking dndnode_1 as completed in the DB and re-evaluating with
 *        fresh user_path_updated events (built from the current DB records),
 *        dndnode_2 must flip to 'inbetween'/'accessible'.
 *
 * Setup: patch_node_ids() assigns courseids[0] to dndnode_1 with min_courses
 * overridden to 1 (so completing the single course satisfies the criterion),
 * and courseids[2] to dndnode_2.  The manual restriction columns (condition_2 /
 * condition_2_feedback) are stripped so the test is purely parent-courses.
 *
 * The parent_courses condition reads:
 *   $usernode['data']['completion']['feedback']['status'] == 'completed'
 * i.e. it checks the COMPLETION status of the referenced parent node, not
 * a course-completion record.  Therefore the trigger is:
 *   1. Insert a course_completions row (mark_course_complete_in_db).
 *   2. Fire a fresh user_path_updated event so updated_single evaluates
 *      dndnode_1 first, writes 'after' into its completion feedback, and
 *      dndnode_2's parent_courses check then sees 'completed'.
 *
 * Expected rendered restriction.before string (condition_1_feedback):
 *   "[EN_233]Sie {node_name} abgeschlossen haben"
 *   {node_name} = dndnode_1.data.fullname = '[EN_386]Collection' (fixture)
 *   rendered:  "[EN_233]Sie [EN_386]Collection abgeschlossen haben"
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
 * Parent-courses restriction: locked/unlocked lifecycle tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc03_parent_courses_restriction_test extends adele_learningpath_testcase {
    /**
     * Uses the main access-path fixture which has the parent_courses restriction
     * on dndnode_2 (condition_1).  The manual OR-column is stripped in patch_node_ids().
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Assign course IDs and remove the manual OR-column so only parent_courses
     * remains as the sole restriction path on dndnode_2.
     *
     * dndnode_1: courseids[0] only, min_courses overridden to 1.
     * dndnode_2: courseids[2], restriction = parent_courses only.
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

                // Strip condition_2 (manual) and condition_2_feedback so
                // parent_courses is the only restriction path evaluated.
                $node['restriction']['nodes'] = array_values(array_filter(
                    $node['restriction']['nodes'],
                    fn($cn) => !in_array($cn['id'], ['condition_2', 'condition_2_feedback'])
                ));
            } else {
                // Assign a single course and set min_courses=1 so that
                // completing courseids[0] alone satisfies dndnode_1's criterion.
                $node['data']['course_node_id'] = [$this->courseids[0]];
                foreach ($node['completion']['nodes'] as &$cn) {
                    if (isset($cn['data']['label']) && $cn['data']['label'] === 'course_completed') {
                        $cn['data']['value']['min_courses'] = 1;
                    }
                }
                unset($cn);
            }
        }
        unset($node);
    }

    /**
     * Immediately after enrollment dndnode_2 must be locked because dndnode_1
     * has not been completed yet.
     *
     * The parent_courses condition checks:
     *   $usernode['data']['completion']['feedback']['status'] == 'completed'
     * for dndnode_1.  That status starts as 'before', so the restriction is not
     * satisfied and getnodestatusforrestriciton() returns 'before'.
     *
     * Expected:
     *   status_restriction = 'before'
     *   status             = 'not_accessible'
     *   restriction.before['condition_1_feedback'] = rendered {node_name} string
     *
     * @return void
     */
    public function test_node_is_locked_before_parent_is_complete(): void {
        global $DB;

        // Step 1: Subscribe and initial evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Assert dndnode_2 is locked.
        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after enrollment.');

        $expectedbefore = '[EN_233]Sie [EN_386]Collection abgeschlossen haben';

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'before',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'before' when parent not yet complete."
            );
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' when parent not yet complete."
            );
            // The rendered before feedback must contain the parent node's fullname.
            $this->assertEquals(
                $expectedbefore,
                $fb['restriction']['before']['condition_1_feedback'],
                "User {$record->user_id}: expected rendered parent-courses restriction string."
            );
        }

        $this->sink->close();
    }

    /**
     * After dndnode_1 is fully completed, dndnode_2 must unlock.
     *
     * Flow:
     *   1. Subscribe + first evaluation (writes user_path_relation, fires events 0–1).
     *   2. Re-evaluate with "creation" events (indices 2–3) to stamp initial
     *      completion status for both nodes.
     *   3. Mark courseids[0] complete in the DB (dndnode_1's first course;
     *      min_courses=1 so this is sufficient).
     *   4. Re-evaluate again with events from the second pass (indices 2–3 again,
     *      as they carry the latest persisted path and propagate the completion).
     *   5. Assert dndnode_2 flips to 'inbetween' / 'accessible'.
     *
     * Why does one re-evaluation pass suffice?
     *   After step 3 the course_completions row exists.  updated_single in step 4
     *   evaluates dndnode_1 first (it appears first in tree.nodes), sets its
     *   completion status to 'after' (completed), writes that into the stored
     *   user_path_relation, and then evaluates dndnode_2.  When the parent_courses
     *   condition reads dndnode_1's stored completion feedback it now sees 'completed',
     *   so the restriction path is satisfied.
     *
     * Expected:
     *   status_restriction = 'inbetween'  (parent_courses OR-path satisfied)
     *   status             = 'accessible'
     *
     * @return void
     */
    public function test_node_unlocks_after_parent_is_complete(): void {
        global $DB;

        // Step 1: Subscribe + first evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Mark courseids[0] complete for every enrolled user.
        // dndnode_1 has min_courses=1 (patched) and courseids[0] is its sole
        // course, so completing it is sufficient to satisfy the completion.
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $this->mark_course_complete_in_db((int)$this->courseids[0], (int)$record->user_id);
        }

        // Step 3: Build and dispatch a fresh user_path_updated event from each
        // stored record so updated_single sees the new course_completions row.
        // This mirrors the UC02 pattern: we read back the persisted records and
        // create events from them so the evaluation sees the latest DB state.
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

        // Step 4: Assert dndnode_2 is now accessible.
        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after parent completion.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'inbetween' after parent node completes."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' after parent node completes."
            );
        }

        $this->sink->close();
    }
}
