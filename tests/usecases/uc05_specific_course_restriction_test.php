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
 * UC-05 — Specific-course restriction: dndnode_2 is locked until the node
 * whose ID is referenced in restriction.condition_1.data.value.courseid
 * (here: 'dndnode_1') reaches completion status 'completed'.
 *
 * This differs from UC-03 (parent_courses) in that the condition label is
 * 'specific_course' and the value references a single sibling node by its
 * string node-ID rather than by a courses_id list.  Internally both conditions
 * read $usernode['data']['completion']['feedback']['status'] from the live
 * tree, so the trigger mechanism is identical.
 *
 * Two tests:
 *   a) test_node_is_locked_before_specific_sibling_is_complete
 *      — immediately after enrollment dndnode_2 must be 'before'/'not_accessible'
 *        because dndnode_1 has no completion record yet.
 *   b) test_node_unlocks_after_specific_sibling_is_complete
 *      — after marking dndnode_1 completed in the DB and re-evaluating with
 *        fresh user_path_updated events, dndnode_2 must flip to
 *        'inbetween'/'accessible'.
 *
 * Setup: patch_node_ids() changes condition_1 label from 'parent_courses' to
 * 'specific_course', sets value['courseid'] = 'dndnode_1' (node ID), removes
 * the manual OR-column (condition_2 / condition_2_feedback), and overrides
 * dndnode_1's min_courses to 1 so completing courseids[0] alone is sufficient.
 *
 * Note: specific_course::get_restriction_status() contains a known typo —
 * it sets $specificcourses[$id]['intbetween'] (not 'inbetween') — but only
 * 'completed' is read by the evaluator, so the typo does not affect behaviour.
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
 * Specific-course restriction: locked/unlocked lifecycle tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc05_specific_course_restriction_test extends adele_learningpath_testcase {
    /**
     * Uses the main access-path fixture which has parent_courses on dndnode_2.
     * patch_node_ids() re-labels condition_1 as specific_course.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Swap condition_1 from parent_courses → specific_course (value: node ID),
     * remove the manual OR-column, and reduce dndnode_1 to a single course with
     * min_courses = 1.
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_2') {
                $node['data']['course_node_id'] = [$this->courseids[2]];

                // Replace condition_1: parent_courses → specific_course.
                // value['courseid'] is the NODE ID of the sibling to watch.
                foreach ($node['restriction']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['data']['label'] = 'specific_course';
                        $cn['data']['description_before'] =
                            '[EN_placeholder]Sie den Knoten {node_name} abgeschlossen haben';
                        $cn['data']['value'] = ['courseid' => 'dndnode_1'];
                    }
                }
                unset($cn);

                // Strip condition_2 (manual) and its feedback so specific_course
                // is the only OR-column evaluated.
                $node['restriction']['nodes'] = array_values(array_filter(
                    $node['restriction']['nodes'],
                    fn($cn) => !in_array($cn['id'], ['condition_2', 'condition_2_feedback'])
                ));
            } else {
                // Dndnode_1: single course, min_courses = 1.
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
     * Before the specific sibling node is completed, dndnode_2 must be locked.
     *
     * specific_course::get_restriction_status() walks tree nodes and sets
     * completed only when $usernode['data']['completion']['feedback']['status']
     * == 'completed'.  Since dndnode_1 has no course completion record yet, its
     * status is 'before', so the restriction is not satisfied.
     *
     * Expected:
     *   status_restriction = 'before'
     *   status             = 'not_accessible'
     *
     * @return void
     */
    public function test_node_is_locked_before_specific_sibling_is_complete(): void {
        global $DB;

        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after enrollment.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'before',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'before' when specific sibling not yet complete."
            );
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' when specific sibling not yet complete."
            );
        }

        $this->sink->close();
    }

    /**
     * After dndnode_1 is fully completed, dndnode_2 must unlock.
     *
     * Flow:
     *   1. Subscribe + first evaluation (creates user_path_relation, events 0–1).
     *   2. Mark courseids[0] complete in the DB for every enrolled user.
     *   3. Build fresh user_path_updated events from DB records and re-evaluate;
     *      updated_single processes dndnode_1 first, writes its completion status
     *      into the live tree, and dndnode_2's specific_course check then sees
     *      $usernode['data']['completion']['feedback']['status'] == 'completed'.
     *
     * Expected:
     *   status_restriction = 'inbetween'  (specific_course path satisfied)
     *   status             = 'accessible'
     *
     * @return void
     */
    public function test_node_unlocks_after_specific_sibling_is_complete(): void {
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

        // Step 3: Re-evaluate with fresh events so updated_single sees the new
        // course_completions row and propagates dndnode_1's completion to the
        // specific_course restriction check on dndnode_2.
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
        $this->assertNotEmpty($records, 'Expected user path records after sibling completion.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'inbetween' after specific sibling completes."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' after specific sibling completes."
            );
        }

        $this->sink->close();
    }
}
