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
 * Regression test for GitHub issue #445:
 *   Deleting node A leaves dependent node B locked forever, because B's
 *   parent_courses access criterion still references the now-deleted A
 *   (a dangling node reference that can never be completed). B is now a
 *   start node and must be accessible.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
final class issue445_deleted_parent_node_test extends adele_learningpath_testcase {
    /**
     * Reuse the parent_courses access fixture.
     *
     * @return string
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Simulate the teacher having deleted node A (dndnode_1): remove it from the
     * tree, but leave dndnode_2's parent_courses criterion still pointing at it
     * (the dangling reference) and promote dndnode_2 to a start node.
     *
     * @param array $nodes
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (($node['id'] ?? '') === 'dndnode_2') {
                $node['data']['course_node_id'] = [$this->courseids[2]];
                // dndnode_2 is now a start node (its only parent was deleted) ...
                $node['parentCourse'] = ['starting_node'];
                // ... but its parent_courses criterion still references dndnode_1.
                // Keep only condition_1 (parent_courses) + its feedback.
                $node['restriction']['nodes'] = array_values(array_filter(
                    $node['restriction']['nodes'],
                    fn($cn) => in_array($cn['id'], ['condition_1', 'condition_1_feedback'])
                ));
            }
        }
        unset($node);

        // Delete node A (dndnode_1) entirely.
        $nodes = array_values(array_filter(
            $nodes,
            fn($n) => ($n['id'] ?? '') !== 'dndnode_1'
        ));
    }

    /**
     * dndnode_2 must be accessible: its only access criterion referenced a node
     * that no longer exists, so it cannot keep the node locked (issue #445).
     *
     * @return void
     */
    public function test_node_with_deleted_parent_is_accessible(): void {
        global $DB;

        $this->subscribe_users_to_lp();
        foreach ($this->get_update_events() as $event) {
            relation_update::updated_single($event);
        }

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after enrollment.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertSame(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: dndnode_2 must be accessible once its deleted "
                    . "parent's dangling criterion can no longer lock it (issue #445)."
            );
        }

        $this->sink->close();
    }
}
