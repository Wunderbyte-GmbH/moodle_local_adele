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
 * UC-C3 — Master completion override: setting
 * node.data.completion.master.completion=true makes the node immediately
 * report status='completed', bypassing all other completion conditions.
 *
 * The master completion condition reads:
 *   $master = $node['data']['completion']['master']['completion']
 * When true, relation_update skips validatenodecompletion() entirely and
 * builds the feedback directly via getfeedback(), which sets:
 *   feedback.status_completion = 'completed'
 *   feedback.status            = 'completed'
 *
 * One test:
 *   test_master_completion_flag_gives_completed
 *     — master.completion=true on dndnode_1, no course completions in DB.
 *       Expected: status_completion='completed', status='completed'.
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
 * Master completion condition test.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_completion\course_completion_status::class)]
final class uc10_master_completion_test extends adele_learningpath_testcase {
    /**
     * Uses the main access-path fixture.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Assign course IDs and set master.completion=true on dndnode_1.
     *
     * No course completions are inserted — the master flag alone must be
     * sufficient to make the node report 'completed'.
     *
     * @param array $nodes
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_1') {
                $node['data']['course_node_id']                    = [$this->courseids[0]];
                $node['data']['completion']['master']['completion'] = true;
            } else {
                $node['data']['course_node_id'] = [$this->courseids[2]];
            }
        }
        unset($node);
    }

    // -------------------------------------------------------------------------
    // Tests.

    /**
     * The master completion flag short-circuits all other completion logic.
     *
     * relation_update detects $completioncriteria['master'] === true and
     * calls getfeedback() directly, which writes
     *   feedback.status_completion = 'completed'
     *   feedback.status            = 'completed'
     * without evaluating course_completed, modquiz, catquiz, etc.
     *
     * No course completions, quiz attempts, or any other DB rows are needed.
     *
     * Expected:
     *   status_completion = 'completed'
     *   status            = 'completed'
     *
     * @return void
     */
    public function test_master_completion_flag_gives_completed(): void {
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
                'completed',
                $fb['status_completion'],
                "User {$record->user_id}: master completion flag must set status_completion='completed'."
            );
            $this->assertEquals(
                'completed',
                $fb['status'],
                "User {$record->user_id}: master completion flag must set status='completed'."
            );
        }
    }
}
