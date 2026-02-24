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
 * UC-04 — Manual completion: a teacher can mark a node as "complete" by
 * setting manualcompletion=true (enabled) and manualcompletionvalue=true
 * (actually completed) on the node data.
 *
 * The manual completion condition (COURSES_COND_MANUALLY) reads:
 *   completed = isset($node['data']['manualcompletion'])
 *               && $node['data']['manualcompletion']
 *               && $node['data']['manualcompletionvalue']
 *
 * When both flags are true the condition returns completed=true, which
 * makes validatenodecompletion() push the path into completionnodepaths
 * and ultimately set feedback['status'] = 'completed'.
 *
 * Fixture: alise_zugangs_lp_einfach.json
 *   dndnode_1: completion = course_completed (fixture default).
 *   dndnode_2: completion = course_completed (fixture default).
 *
 * Both test classes target dndnode_1's completion status.  The
 * course_completed condition is still present in the fixture; the manual
 * flags are an additional per-node override.
 *
 * Two tests:
 *   a) test_node_completion_stays_before_when_manual_flag_disabled
 *      — manualcompletion=true but manualcompletionvalue=false (teacher has
 *        enabled the feature but not ticked the box).  No course completions
 *        exist, so course_completed is also unsatisfied.  Expected status='accessible'.
 *      NOTE: with restriction 'before' and completion 'before', getnodestatus
 *      returns 'accessible' (not 'not_accessible') because
 *      is_null($feedback['restriction']['before']) is true for dndnode_1
 *      which has no restriction nodes at all.
 *   b) test_node_completion_is_after_when_manual_flag_enabled
 *      — manualcompletion=true AND manualcompletionvalue=true.  The manual
 *        condition is satisfied, so the node's overall status must be 'completed'.
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
 * Manual completion condition tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_completion\course_completion_status::class)]
final class uc04_manual_completion_test extends adele_learningpath_testcase {
    /**
     * Uses the main access-path fixture.  patch_node_ids() applies the manual
     * completion flags to dndnode_1 via the subclass-specific $manualvalue.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Subclass-provided override: whether manualcompletionvalue should be true
     * or false.  Set in each concrete subclass below.
     *
     * @var bool
     */
    protected bool $manualcompletionvalue = false;

    /**
     * Assign course IDs and replace dndnode_1's completion condition node
     * label from 'course_completed' to 'manual', then set the manual flags
     * on the node data.
     *
     * The manual label makes validatenodecompletion() evaluate
     * $completioncriteria['manual']['completed'], which is driven by
     * manual::get_completion_status() reading the node's manualcompletion
     * and manualcompletionvalue flags.
     *
     * dndnode_2 gets a plain single course assignment.
     *
     * @param array $nodes
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_1') {
                $node['data']['course_node_id']       = [$this->courseids[0]];
                $node['data']['manualcompletion']      = true;
                $node['data']['manualcompletionvalue'] = $this->manualcompletionvalue;

                // Replace the completion condition label from 'course_completed'
                // to 'manual' so the evaluation loop picks up the manual flags.
                foreach ($node['completion']['nodes'] as &$cn) {
                    if (isset($cn['data']['label']) && $cn['data']['label'] === 'course_completed') {
                        $cn['data']['label'] = 'manual';
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
     * When manualcompletion=true but manualcompletionvalue=false the teacher
     * has enabled the widget but not yet ticked the completion box.  No course
     * completions exist either.
     *
     * The manual condition returns completed=false.
     * The course_completed condition also returns completed=false (no DB row).
     * Therefore validatenodecompletion() finds no satisfied path and
     * getnodestatusforcompletion() returns 'before'.
     *
     * dndnode_1 has no restriction nodes, so
     * is_null($feedback['restriction']['before']) == true and
     * getnodestatus() returns 'accessible' (not 'not_accessible').
     *
     * Expected:
     *   status_completion = 'before'
     *   status            = 'accessible'
     *
     * @return void
     */
    public function test_node_completion_stays_before_when_manual_flag_disabled(): void {
        global $DB;

        // Manualcompletionvalue defaults to false (set at property level).
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
                'before',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'before' completion when manual flag not ticked."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' when no restriction on dndnode_1."
            );
        }

        $this->sink->close();
    }

    /**
     * When both manualcompletion=true and manualcompletionvalue=true the
     * teacher has ticked the completion box for this node.
     *
     * The manual::get_completion_status() returns completed=true.
     * validatenodecompletion() pushes the path into completionnodepaths, which
     * triggers getfeedback() to move the after_all entry into after[].
     * getnodestatus() then returns 'completed'.
     *
     * Expected:
     *   status_completion = 'after'
     *   status            = 'completed'
     *
     * @return void
     */
    public function test_node_completion_is_after_when_manual_flag_enabled(): void {
        global $DB;

        // Override the manual value flag to true for this test only.
        // We do it in the stored records after the first evaluation so we
        // mirror the real flow: a teacher ticks the box in the UI, which
        // writes manualcompletionvalue=true into the tree nodes and fires a
        // new user_path_updated event.

        // Step 1: Subscribe + initial evaluation (manualcompletionvalue=false).
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Simulate teacher tick — write manualcompletionvalue=true into
        // the stored tree nodes and fire a fresh event for each user.
        $records = $DB->get_records('local_adele_path_user');
        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            foreach ($json['tree']['nodes'] as &$treenode) {
                if ($treenode['id'] === 'dndnode_1') {
                    $treenode['data']['manualcompletionvalue'] = true;
                }
            }
            unset($treenode);
            $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
        }

        // Step 3: Fire fresh user_path_updated events so updated_single
        // sees the new manualcompletionvalue=true node data.
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
        $updatedrecords = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($updatedrecords, 'Expected user path records after teacher tick.');

        foreach ($updatedrecords as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_1']['feedback'];

            $this->assertEquals(
                'after',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'after' completion when manual flag is ticked."
            );
            $this->assertEquals(
                'completed',
                $fb['status'],
                "User {$record->user_id}: expected 'completed' when manual completion is ticked."
            );
        }

        $this->sink->close();
    }
}
