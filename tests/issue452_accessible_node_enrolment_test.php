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
 * Regression test for GitHub issue #452:
 *   A branch of two criteria-less nodes (course A -> course B). Node B has no
 *   access restriction so it is displayed as 'accessible', but the user is
 *   never enrolled into course B, so clicking it shows "you can't enrol
 *   yourself in this course".
 *
 * Enrolment must follow accessibility: when a node is computed 'accessible',
 * the subscribed user must be enrolled into that node's course.
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
final class issue452_accessible_node_enrolment_test extends advanced_testcase {
    /** @var int */
    private $c1;
    /** @var int */
    private $c2;

    /**
     * Two-node branch: a starting node (course A) and a child node (course B),
     * neither carrying any restriction or completion criteria.
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
                        'restriction' => ['nodes' => [], 'edges' => []],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                ],
                'edges' => [['source' => 'dndnode_1', 'target' => 'dndnode_2', 'data' => []]],
            ],
            'modules' => null,
        ];
    }

    /**
     * A criteria-less child node shown as 'accessible' must also have the user
     * enrolled into its course (issue #452: display must equal access).
     *
     * @return void
     */
    public function test_accessible_node_enrols_user(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course1 = $gen->create_course();
        $course2 = $gen->create_course();
        $this->c1 = (int) $course1->id;
        $this->c2 = (int) $course2->id;
        $user = $gen->create_user();
        $this->setUser($user);

        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Issue 452 LP',
            'json' => json_encode($this->build_tree()),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);

        // Fresh subscription (no user_path_relation yet), exactly as
        // enrollment::subscribe_user_to_learning_path builds it.
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

        user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => \context_system::instance(),
            'other' => ['userpath' => $userpath],
        ])->trigger();

        // Re-read the recomputed user path.
        $record = $DB->get_record('local_adele_path_user', ['id' => $id]);
        $json = json_decode($record->json, true);
        $status = $json['user_path_relation']['dndnode_2']['feedback']['status'] ?? null;

        // Sanity: the starting node's course is always enrolled.
        $this->assertTrue(
            is_enrolled(\context_course::instance($this->c1), $user->id),
            'The starting node course (A) must be enrolled.'
        );

        // The child node carries no restriction, so it is shown accessible ...
        $this->assertSame('accessible', $status, 'Node B has no restriction and must be accessible.');

        // ... and therefore the user must actually be enrolled into its course.
        $this->assertTrue(
            is_enrolled(\context_course::instance($this->c2), $user->id),
            'BUG #452: node B is shown accessible but the user was never enrolled into its course.'
        );
    }
}
