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
 * UC-01b — Manual restriction unlock: a teacher setting manualrestrictionvalue=true
 * on a node makes it accessible (status_restriction='inbetween').
 *
 * dndnode_2 in the alise_zugangs_lp_einfach fixture has two OR-linked
 * restriction conditions:
 *   condition_1 (parent_courses)  — requires dndnode_1 to be complete
 *   condition_2 (manual)          — requires teacher's explicit unlock
 *
 * Both tests in this class pre-set manualrestriction=true on dndnode_2.
 * The first test keeps manualrestrictionvalue=true (pre-unlocked in the
 * fixture) and asserts the node is accessible.
 * The second test starts from the same unlocked state, then re-locks the
 * node by setting manualrestrictionvalue=false in the DB and re-evaluating,
 * verifying the node returns to before / not_accessible.
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
 * Manual restriction unlock tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc01b_manual_restriction_unlocked_test extends adele_learningpath_testcase {
    /**
     * Uses the main access-path fixture which contains the manual restriction
     * condition (condition_2) on dndnode_2.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Pre-set manualrestriction=true AND manualrestrictionvalue=true on dndnode_2.
     * This simulates a teacher who has already enabled and unlocked the manual
     * restriction for this node.
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
                // Teacher has enabled the manual restriction and unlocked the node.
                $node['data']['manualrestriction']      = true;
                $node['data']['manualrestrictionvalue'] = true;
            } else {
                $node['data']['course_node_id'] = [
                    $this->courseids[0],
                    $this->courseids[3],
                ];
            }
        }
    }

    /**
     * When the manual restriction is enabled and its value is true, dndnode_2
     * must flip to inbetween / accessible even though the parent_courses
     * condition (condition_1) is still unsatisfied.
     *
     * The manual condition (condition_2) is an OR-sibling of parent_courses:
     * satisfying either one is sufficient for access.
     *
     * Expected after the second evaluation (creation events):
     *   status_restriction = 'inbetween'  (manual OR-path satisfied)
     *   status             = 'accessible'
     */
    public function test_node_is_accessible_when_manual_restriction_unlocked(): void {
        global $DB;

        // Step 1: Subscribe and first evaluation.
        // updated_single on indices 0,1 writes user_path_relation to the DB
        // and fires two "creation" user_path_updated events (indices 2,3).
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Re-evaluate using the "creation" events (indices 2,3).
        // These carry the persisted userpath objects so updated_single sees
        // the manualrestrictionvalue=true node data that was stored in step 1.
        $allupdateevents = $this->get_update_events();
        relation_update::updated_single($allupdateevents[2]);
        relation_update::updated_single($allupdateevents[3]);

        // Step 3: Assert dndnode_2 is now accessible.
        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after enrollment.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'inbetween' when manual restriction unlocked."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' when manual restriction unlocked."
            );
        }

        $this->sink->close();
    }

    /**
     * Verify that setting manualrestrictionvalue=false re-locks the node.
     *
     * Scenario:
     *   1. Start from the pre-unlocked state (manualrestrictionvalue=true in fixture).
     *   2. First evaluation: node is initially accessible.
     *   3. A teacher "re-locks" the node by writing manualrestrictionvalue=false
     *      into the persisted tree nodes in the DB.
     *   4. Re-evaluate with a manually created user_path_updated event that
     *      carries the updated userpath.
     *   5. dndnode_2 returns to before / not_accessible because:
     *      - manual condition: manualrestrictionvalue=false → NOT satisfied
     *      - parent_courses condition: dndnode_1 not complete → NOT satisfied
     */
    public function test_node_returns_to_locked_when_manual_restriction_value_set_false(): void {
        global $DB;

        // Step 1: Subscribe and first evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Roll back manualrestrictionvalue to false in the persisted
        // tree nodes, simulating a teacher re-locking the node via the admin UI.
        $records = $DB->get_records('local_adele_path_user');
        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            foreach ($json['tree']['nodes'] as &$treenode) {
                if ($treenode['id'] === 'dndnode_2') {
                    $treenode['data']['manualrestrictionvalue'] = false;
                }
            }
            unset($treenode);
            $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
        }

        // Step 3: Fire a user_path_updated event for each updated record so
        // updated_single re-evaluates with the new manualrestrictionvalue=false.
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

        // Step 4: Assert dndnode_2 is locked again.
        $updatedrecords = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($updatedrecords, 'Expected user path records after re-locking.');

        foreach ($updatedrecords as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'before',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'before' after re-locking manual restriction."
            );
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' after re-locking manual restriction."
            );
        }

        $this->sink->close();
    }
}
