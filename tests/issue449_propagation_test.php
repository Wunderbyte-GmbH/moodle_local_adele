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
 * End-to-end regression test for GitHub issue #449:
 *   removing a node's access criteria must propagate to already-subscribed users
 *   and open the node.
 *
 * Exercises the full chain: learnpath_updated event ->
 * learning_path_update::updated_learning_path() -> passnodevalues() (rebuilds the
 * user's tree from the edited path) -> user_path_updated -> relation_update ->
 * getnodestatus(). After the access criteria are removed the child node must be
 * recomputed as 'accessible'.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use local_adele\event\learnpath_updated;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_path_update::updated_learning_path
 */
final class issue449_propagation_test extends advanced_testcase {
    /** @var int */
    private $c1;
    /** @var int */
    private $c2;

    /**
     * Build a learning-path json: a starting node (no restriction) and a child
     * node whose restriction conditions are given by $node2restrictionnodes.
     *
     * @param array $node2restrictionnodes Restriction condition nodes for node 2.
     * @return string Encoded learning-path json.
     */
    private function build_json(array $node2restrictionnodes): string {
        return json_encode([
            'name' => 'Issue 449 LP',
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1', 'type' => 'circle',
                        'parentCourse' => ['starting_node'], 'childCourse' => ['dndnode_2'],
                        'data' => ['course_node_id' => [(string) $this->c1], 'fullname' => 'Node 1'],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                    [
                        'id' => 'dndnode_2', 'type' => 'circle',
                        'parentCourse' => ['dndnode_1'], 'childCourse' => [],
                        'data' => ['course_node_id' => [(string) $this->c2], 'fullname' => 'Node 2'],
                        'restriction' => ['nodes' => $node2restrictionnodes, 'edges' => []],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                ],
                'edges' => [['source' => 'dndnode_1', 'target' => 'dndnode_2', 'data' => []]],
            ],
            'user_path_relation' => [],
        ]);
    }

    /**
     * Editing a path to remove a node's access criteria must open that node for
     * an already-subscribed user.
     *
     * @return void
     */
    public function test_removing_access_criteria_propagates_and_opens_node(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course1 = $gen->create_course();
        $course2 = $gen->create_course();
        $this->c1 = (int) $course1->id;
        $this->c2 = (int) $course2->id;
        $user = $gen->create_user();
        $this->setUser($user);

        // A learning path record (the edit event carries the new json separately).
        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Issue 449 LP',
            'json' => $this->build_json([]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);

        // The user is subscribed while node 2 still has a manual access criterion.
        $manualcondition = [[
            'id' => 'condition_1',
            'parentCondition' => ['starting_condition'],
            'childCondition' => [],
            'data' => ['label' => 'manual', 'id' => 150, 'visibility' => true],
        ]];
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id,
            'course_id' => $this->c1,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'json' => $this->build_json($manualcondition),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);

        // The teacher edits the path and removes node 2's access criteria.
        learnpath_updated::create([
            'objectid' => $lpid,
            'context' => \context_system::instance(),
            'other' => ['learningpathid' => $lpid, 'json' => $this->build_json([])],
        ])->trigger();

        // The subscribed user's node 2 must now be recomputed as accessible.
        $record = $DB->get_record('local_adele_path_user', ['learning_path_id' => $lpid, 'user_id' => $user->id]);
        $json = json_decode($record->json, true);
        $status = $json['user_path_relation']['dndnode_2']['feedback']['status'] ?? null;

        $this->assertSame(
            'accessible',
            $status,
            'Removing node 2 access criteria must propagate to the subscribed user and open the node (issue #449).'
        );
    }
}
