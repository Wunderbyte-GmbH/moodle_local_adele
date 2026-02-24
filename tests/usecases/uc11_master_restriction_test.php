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
 * UC-R6 — Master restriction override: setting
 * node.data.completion.master.restriction=true makes the node immediately
 * report status='accessible', bypassing all other restriction conditions.
 *
 * The master restriction condition reads:
 *   $master = $node['data']['completion']['master']['restriction']
 * When true, relation_update pushes 'master' into $restrictionnodepaths before
 * the normal restriction walk, so getnodestatusforrestriciton() sees a
 * non-empty $restrictionnodepaths and returns 'inbetween', and
 * getnodestatus() sees a truthy $restrictionnodepaths and returns 'accessible'.
 * getfeedback() also writes status_restriction='accessible' and status='accessible'
 * directly.
 *
 * Fixture: alise_zugangs_lp_einfach.json
 *   dndnode_2: has a parent_courses restriction pointing at dndnode_1.
 *              Without the master flag this node stays 'locked' because dndnode_1
 *              has never been completed.
 *              With master.restriction=true the restriction is bypassed.
 *
 * One test:
 *   test_master_restriction_flag_gives_accessible
 *     — master.restriction=true on dndnode_2, dndnode_1 never completed.
 *       Expected: status_restriction='inbetween', status='accessible'.
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
 * Master restriction condition test.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc11_master_restriction_test extends adele_learningpath_testcase {
    /**
     * Uses the main access-path fixture.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Assign course IDs and set master.restriction=true on dndnode_2.
     *
     * dndnode_2 already has a parent_courses restriction in the fixture that
     * depends on dndnode_1 being completed.  We never complete dndnode_1, so
     * without the master flag dndnode_2 would remain 'locked'.  The master
     * flag must override that and make it 'accessible'.
     *
     * Condition_2 (manual restriction) is stripped so the only restriction
     * path is parent_courses, making the override clearly visible.
     *
     * @param array $nodes
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_2') {
                $node['data']['course_node_id']                     = [$this->courseids[2]];
                $node['data']['completion']['master']['restriction'] = true;

                // Keep only the parent_courses column so the bypass is unambiguous.
                $node['restriction']['nodes'] = array_values(array_filter(
                    $node['restriction']['nodes'],
                    fn($cn) => !in_array($cn['id'], ['condition_2', 'condition_2_feedback'])
                ));
            } else {
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

    // -------------------------------------------------------------------------
    // Tests.

    /**
     * The master restriction flag short-circuits the entire restriction walk.
     *
     * relation_update detects $restrictioncriteria['master'] === true and
     * pushes 'master' into $restrictionnodepaths before the normal column
     * walk even starts.  As a result:
     *   getnodestatusforrestriciton() sees count(['master']) > 0 → 'inbetween'
     *   getnodestatus() sees truthy $restrictionnodepaths → 'accessible'
     *   getfeedback() also writes status_restriction='accessible' directly.
     *
     * dndnode_1 is never completed, so without the master flag dndnode_2
     * would have status_restriction='before' and status='locked'.
     *
     * Expected:
     *   status_restriction = 'inbetween'  (master path satisfies restriction)
     *   status             = 'accessible' (restriction overridden)
     *
     * @return void
     */
    public function test_master_restriction_flag_gives_accessible(): void {
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
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: master restriction must set status_restriction='inbetween'."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: master restriction flag must override lock and give status='accessible'."
            );
        }
    }
}
