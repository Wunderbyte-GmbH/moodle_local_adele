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

namespace local_adele;

use context_system;
use local_adele\event\user_path_updated;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// The attribute stands above the class comment: between comment and class
// it would hide the class-level @covers tags from the coding-style check.
#[RunTestsInSeparateProcesses]
/**
 * The bracketed criterion (P AND T) OR M at runtime - its whole truth table.
 *
 * Chain E2 of the E2E plan stores bracketed combinations in the editor; the
 * E2E issue (section 2, E2) asks for the RUNTIME truth table of such a
 * stored graph, not only for its columns in the database. The browser chain
 * checks the stored structure; here the same structure is evaluated by
 * relation_update for all eight combinations:
 *
 *     column 1: P (predecessor completed) -> T (time window open)   = AND
 *     column 2: M (released manually)                                = OR
 *
 * Built on the AND fixture (simpleconcatlp.json: P -> T) with a second
 * column added: the manual condition, as the editor writes it.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\relation_update
 * @covers     \local_adele\course_restriction\conditions\manual
 * @covers     \local_adele\course_restriction\conditions\timed
 * @covers     \local_adele\course_restriction\conditions\parent_courses
 */
final class uc23_bracketed_criterion_truth_table_test extends adele_learningpath_testcase {
    /**
     * The fixture: P -> T in one column.
     *
     * @return string
     */
    protected function fixturefile(): string {
        return 'simpleconcatlp.json';
    }

    /**
     * Real course ids; node 1 completes on its course alone; node 2 gets the
     * second column M.
     *
     * @param array $nodes
     * @return void
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_1') {
                $node['data']['course_node_id'] = [$this->courseids[0]];
                foreach ($node['completion']['nodes'] as &$condition) {
                    if ($condition['id'] === 'condition_1') {
                        $condition['childCondition'] = ['condition_1_feedback'];
                    }
                }
                unset($condition);
                $node['completion']['nodes'] = array_values(array_filter(
                    $node['completion']['nodes'],
                    fn($condition) => $condition['id'] !== 'condition_2'
                ));
                continue;
            }
            $node['data']['course_node_id'] = [$this->courseids[2]];
            $node['restriction']['nodes'][] = [
                'id' => 'condition_3',
                'type' => 'custom',
                'data' => [
                    'name' => 'Node restriction checkbox',
                    'description' => 'Access will be granted manually',
                    'description_before' => 'eine manuelle Freigabe durch den Lehrenden stattgefunden hat',
                    'label' => 'manual',
                    'visibility' => true,
                    'node_id' => 'condition_3',
                ],
                'parentCondition' => ['starting_condition'],
                'childCondition' => ['condition_3_feedback'],
            ];
            $node['restriction']['nodes'][] = [
                'id' => 'condition_3_feedback',
                'type' => 'feedback',
                'data' => [
                    'visibility' => true,
                    'feedback_before' => 'eine manuelle Freigabe durch den Lehrenden stattgefunden hat',
                    'feedback_before_checkmark' => true,
                    'childCondition' => 'condition_3',
                ],
            ];
        }
        unset($node);
    }

    /**
     * The eight rows.
     *
     * @return array
     */
    public static function rows(): array {
        $rows = [];
        foreach ([false, true] as $p) {
            foreach ([false, true] as $t) {
                foreach ([false, true] as $m) {
                    $rows[sprintf('P=%d T=%d M=%d', $p, $t, $m)] = [$p, $t, $m];
                }
            }
        }
        return $rows;
    }

    /**
     * One row: node 2 is accessible exactly when (P AND T) OR M.
     *
     * @dataProvider rows
     * @param bool $p Predecessor completed.
     * @param bool $t Time window open (otherwise not yet open).
     * @param bool $m Released manually.
     * @return void
     */
    public function test_row(bool $p, bool $t, bool $m): void {
        global $DB;
        $this->subscribe_users_to_lp();
        foreach ($this->get_update_events() as $event) {
            relation_update::updated_single($event);
        }
        foreach ($DB->get_records('local_adele_path_user') as $record) {
            $json = json_decode($record->json, true);
            foreach ($json['tree']['nodes'] as &$treenode) {
                if ($treenode['id'] !== 'dndnode_2') {
                    continue;
                }
                $treenode['data']['manualrestriction'] = true;
                $treenode['data']['manualrestrictionvalue'] = $m;
                foreach ($treenode['restriction']['nodes'] as &$condition) {
                    if (($condition['data']['label'] ?? '') === 'timed') {
                        $condition['data']['value']['start'] = strtotime($t ? '-7 days' : '+7 days');
                        $condition['data']['value']['end'] = strtotime('+14 days');
                    }
                }
                unset($condition);
            }
            unset($treenode);
            $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
            if ($p) {
                $this->mark_course_complete_in_db((int) $this->courseids[0], (int) $record->user_id);
            }
        }
        foreach ($DB->get_records('local_adele_path_user') as $record) {
            $record->json = json_decode($record->json, true);
            relation_update::updated_single(user_path_updated::create([
                'objectid' => $record->id,
                'context' => context_system::instance(),
                'other' => ['userpath' => $record],
            ]));
        }
        $expected = (($p && $t) || $m) ? 'accessible' : 'not_accessible';
        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records);
        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $this->assertSame(
                $expected,
                $json['user_path_relation']['dndnode_2']['feedback']['status'],
                "user {$record->user_id}"
            );
        }
        $this->sink->close();
    }
}
