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
 * Regression test for the "Trying to access array offset on null" crash in
 * relation_update::updated_single when a node carries a restriction condition
 * whose feedback node is not wired up (childCondition === []).
 *
 * Reproduces the fatal seen when adding a freshly created learning path to a
 * course: searchnestedarray() returns null for the empty childCondition, and
 * the per-feedback-node "before_active" assignment dereferenced $feedback['id']
 * on null. The engine must tolerate an unwired/incomplete condition.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use local_adele\event\user_path_updated;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\relation_update::updated_single
 */
final class issue465_unwired_restriction_feedback_test extends advanced_testcase {
    /** @var int */
    private $c1;
    /** @var int */
    private $c2;

    /**
     * Two-node branch (course A -> course B). Node B carries a restriction whose
     * single condition is parented to the starting condition but whose
     * childCondition is empty - i.e. the feedback node 'condition_1_feedback'
     * exists but is not linked. The condition label is incidental: the crash is
     * triggered purely by the unwired feedback lookup.
     *
     * @return array Decoded learning-path tree json structure.
     */
    private function build_tree(): array {
        return [
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1', 'type' => 'circle',
                        'parentCourse' => ['starting_node'], 'childCourse' => ['dndnode_2'],
                        'data' => ['course_node_id' => [(string) $this->c1], 'fullname' => 'Node A'],
                        'restriction' => ['nodes' => [], 'edges' => []],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                    [
                        'id' => 'dndnode_2', 'type' => 'circle',
                        'parentCourse' => ['dndnode_1'], 'childCourse' => [],
                        'data' => ['course_node_id' => [(string) $this->c2], 'fullname' => 'Node B'],
                        'restriction' => [
                            'nodes' => [
                                [
                                    'id' => 'condition_1',
                                    'parentCondition' => ['starting_condition'],
                                    'childCondition' => [],
                                    'data' => [
                                        'id' => 150,
                                        'label' => 'manual',
                                        'description_before' => 'must be released manually',
                                    ],
                                ],
                                [
                                    'id' => 'condition_1_feedback',
                                    'parentCondition' => null,
                                    'childCondition' => null,
                                    'data' => ['label' => 'feedback', 'visibility' => true],
                                ],
                            ],
                            'edges' => [],
                        ],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                ],
                'edges' => [['source' => 'dndnode_1', 'target' => 'dndnode_2', 'data' => []]],
            ],
            'modules' => null,
        ];
    }

    /**
     * Recomputing a user path whose node has an unwired restriction-feedback
     * node must not fatal with "Trying to access array offset on null".
     *
     * @return void
     */
    public function test_unwired_feedback_does_not_crash(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $this->c1 = (int) $gen->create_course()->id;
        $this->c2 = (int) $gen->create_course()->id;
        $user = $gen->create_user();
        $this->setUser($user);

        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Issue 465 LP',
            'json' => json_encode($this->build_tree()),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);

        $id = $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id,
            'course_id' => $this->c1,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'json' => json_encode($this->build_tree()),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);
        $userpath = $DB->get_record('local_adele_path_user', ['id' => $id]);
        $userpath->json = json_decode($userpath->json, true);

        $event = user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => \context_system::instance(),
            'other' => ['userpath' => $userpath],
        ]);

        // Call the observer body directly (the event manager would otherwise
        // swallow the observer exception into debugging()). Before the fix this
        // raised "Trying to access array offset on null" in the restriction
        // feedback assignment; PHPUnit promotes that warning to a failure.
        relation_update::updated_single($event);

        // The recompute completed and persisted a relation for the node.
        $json = json_decode($DB->get_record('local_adele_path_user', ['id' => $id])->json, true);
        $this->assertArrayHasKey('dndnode_2', $json['user_path_relation']);
    }
}
