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
 * UC-01 — Manual restriction baseline: node stays locked when neither
 * restriction condition (parent_courses OR manual) is satisfied.
 *
 * dndnode_2 in the alise_zugangs_lp_einfach fixture has two OR-linked
 * restriction conditions:
 *   condition_1 (parent_courses)  — requires dndnode_1 to be complete
 *   condition_2 (manual)          — requires teacher's explicit unlock
 *
 * Neither condition is pre-set here, so after the initial enrollment
 * evaluation dndnode_2 must remain before / not_accessible.
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Locked-baseline test for the manual restriction condition.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc01a_manual_restriction_locked_test extends adele_learningpath_testcase {
    /**
     * Uses the main access-path fixture which contains the manual restriction
     * condition (condition_2) on dndnode_2.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Standard node assignment — no manual restriction pre-set.
     * dndnode_2: manualrestriction = null (fixture default = condition disabled).
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
                // Manualrestriction deliberately left as fixture default (null / not set).
            } else {
                $node['data']['course_node_id'] = [
                    $this->courseids[0],
                    $this->courseids[3],
                ];
            }
        }
    }

    /**
     * After enrollment, dndnode_2 must be locked when no restriction condition
     * is satisfied (neither parent_courses nor manual).
     *
     * Expected:
     *   status_restriction = 'before'   (no OR-condition met)
     *   status             = 'not_accessible'
     */
    public function test_node_is_locked_when_no_restriction_condition_is_satisfied(): void {
        global $DB;

        $this->subscribe_users_to_lp();

        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist after enrollment.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'before',
                $fb['status_restriction'],
                "User {$record->user_id}: expected status_restriction='before' when no restriction met."
            );
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected status='not_accessible' when no restriction met."
            );
        }

        $this->sink->close();
    }
}
