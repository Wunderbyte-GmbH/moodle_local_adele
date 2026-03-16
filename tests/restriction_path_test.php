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
 * Unit tests for feedback node detection and restriction path extraction.
 *
 * Tests the is_feedback_node() method for correct detection of feedback
 * nodes by type, data.label and id suffix. Tests get_restriction_paths()
 * for correct traversal of restriction chains with AND/OR logic,
 * skipping feedback nodes.
 *
 * The ROOT CAUSE of the original bug was that condition_1_feedback had
 * type="feedback" but no data.label field. The old code only checked
 * data.label for "feedback", so the feedback node was not recognized.
 * The path followed the feedback node instead of condition_2 (timed),
 * so the timed restriction was never evaluated.
 *
 * @package     local_adele
 * @category    test
 * @author      Ralf Erlebach
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use ReflectionMethod;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/adele/lib.php');

/**
 * Tests for feedback node detection and restriction path extraction.
 *
 * @package     local_adele
 * @category    test
 * @author      Ralf Erlebach
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\node_completion
 */
class restriction_path_test extends advanced_testcase {

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Call a private/protected static method on node_completion.
     *
     * @param string $methodname
     * @param array $args
     * @return mixed
     */
    private function call_method(string $methodname, array $args) {
        $method = new ReflectionMethod(node_completion::class, $methodname);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $args);
    }

    /**
     * Build a feedback node as it appears in real data:
     * type="feedback", no data.label, only data.childCondition and data.visibility.
     *
     * @param string $id
     * @param string $parentid
     * @return array
     */
    private function build_feedback_node(string $id, string $parentid): array {
        return [
            'id' => $id,
            'type' => 'feedback',
            'data' => [
                'childCondition' => $parentid,
                'visibility' => true,
            ],
            'parentCondition' => [$parentid],
            'childCondition' => [],
        ];
    }

    /**
     * Build a parent_courses condition node.
     *
     * @param string $id
     * @param array $parentcondition
     * @param array $childcondition
     * @return array
     */
    private function build_parent_courses_node(
        string $id,
        array $parentcondition = ['starting_condition'],
        array $childcondition = []
    ): array {
        return [
            'id' => $id,
            'type' => 'condition',
            'data' => [
                'label' => 'parent_courses',
                'value' => [],
            ],
            'parentCondition' => $parentcondition,
            'childCondition' => $childcondition,
        ];
    }

    /**
     * Build a timed condition node.
     *
     * @param string $id
     * @param array $parentcondition
     * @param array $childcondition
     * @return array
     */
    private function build_timed_node(
        string $id,
        array $parentcondition = ['starting_condition'],
        array $childcondition = []
    ): array {
        return [
            'id' => $id,
            'type' => 'condition',
            'data' => [
                'label' => 'timed',
                'value' => [
                    'start' => '2026-06-15T09:00',
                    'end' => null,
                ],
            ],
            'parentCondition' => $parentcondition,
            'childCondition' => $childcondition,
        ];
    }

    /**
     * Build a specific_course condition node.
     *
     * @param string $id
     * @param array $parentcondition
     * @param array $childcondition
     * @return array
     */
    private function build_specific_course_node(
        string $id,
        array $parentcondition = ['starting_condition'],
        array $childcondition = []
    ): array {
        return [
            'id' => $id,
            'type' => 'condition',
            'data' => [
                'label' => 'specific_course',
                'value' => [],
            ],
            'parentCondition' => $parentcondition,
            'childCondition' => $childcondition,
        ];
    }

    /**
     * Build a nodemap from an array of restriction nodes.
     *
     * @param array $nodes
     * @return array
     */
    private function build_nodemap(array $nodes): array {
        $nodemap = [];
        foreach ($nodes as $node) {
            $nodemap[$node['id']] = $node;
        }
        return $nodemap;
    }

    /**
     * Extract path node ids from the paths returned by get_restriction_paths.
     *
     * @param array $paths
     * @return array Array of arrays of node ids
     */
    private function extract_path_ids(array $paths): array {
        $result = [];
        foreach ($paths as $path) {
            $ids = [];
            foreach ($path as $node) {
                $ids[] = $node['id'];
            }
            $result[] = $ids;
        }
        return $result;
    }

    // =========================================================================
    // TESTS: is_feedback_node()
    // =========================================================================

    /**
     * Detection by type="feedback" (the most common real-world case).
     * The node has type="feedback" but NO data.label field.
     * This was the ROOT CAUSE of the original bug.
     */
    public function test_feedback_detected_by_type_field() {
        $node = [
            'id' => 'condition_1_feedback',
            'type' => 'feedback',
            'data' => [
                'childCondition' => 'condition_1',
                'visibility' => true,
            ],
        ];

        $this->assertTrue(
            $this->call_method('is_feedback_node', [$node]),
            'Node with type="feedback" must be detected as feedback'
        );
    }

    /**
     * Detection by data.label containing "feedback".
     */
    public function test_feedback_detected_by_data_label() {
        $node = [
            'id' => 'cond_1',
            'type' => 'condition',
            'data' => ['label' => 'feedback_positive'],
        ];

        $this->assertTrue(
            $this->call_method('is_feedback_node', [$node]),
            'Node with data.label containing "feedback" must be detected'
        );
    }

    /**
     * Detection by data.label being exactly "feedback".
     */
    public function test_feedback_detected_by_exact_label() {
        $node = [
            'id' => 'cond_1',
            'type' => 'condition',
            'data' => ['label' => 'feedback'],
        ];

        $this->assertTrue(
            $this->call_method('is_feedback_node', [$node])
        );
    }

    /**
     * Detection by id suffix "_feedback".
     */
    public function test_feedback_detected_by_id_suffix() {
        $node = [
            'id' => 'condition_1_feedback',
            'type' => 'custom',
            'data' => ['label' => 'something_else'],
        ];

        $this->assertTrue(
            $this->call_method('is_feedback_node', [$node]),
            'Node with id containing "_feedback" must be detected'
        );
    }

    /**
     * A regular timed condition is NOT feedback.
     */
    public function test_timed_node_is_not_feedback() {
        $node = [
            'id' => 'condition_2',
            'type' => 'condition',
            'data' => ['label' => 'timed'],
        ];

        $this->assertFalse(
            $this->call_method('is_feedback_node', [$node])
        );
    }

    /**
     * A regular parent_courses condition is NOT feedback.
     */
    public function test_parent_courses_node_is_not_feedback() {
        $node = [
            'id' => 'condition_1',
            'type' => 'condition',
            'data' => ['label' => 'parent_courses'],
        ];

        $this->assertFalse(
            $this->call_method('is_feedback_node', [$node])
        );
    }

    /**
     * A node with empty data is NOT feedback.
     */
    public function test_empty_data_node_is_not_feedback() {
        $node = [
            'id' => 'condition_1',
            'type' => 'condition',
            'data' => [],
        ];

        $this->assertFalse(
            $this->call_method('is_feedback_node', [$node])
        );
    }

    /**
     * A node with no type and no label is NOT feedback.
     */
    public function test_minimal_node_is_not_feedback() {
        $node = [
            'id' => 'condition_1',
            'data' => [],
        ];

        $this->assertFalse(
            $this->call_method('is_feedback_node', [$node])
        );
    }

    // =========================================================================
    // TESTS: get_restriction_paths() – single path (no branching)
    // =========================================================================

    /**
     * Single condition, no chain.
     * Expected: one path with one node.
     */
    public function test_single_condition_path() {
        $nodes = [
            $this->build_timed_node('c1'),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids);
        $this->assertEquals(['c1'], $pathids[0]);
    }

    /**
     * Two AND-chained conditions without feedback.
     * Expected: one path with two nodes.
     */
    public function test_two_and_chained_conditions() {
        $nodes = [
            $this->build_parent_courses_node('c1', ['starting_condition'], ['c2']),
            $this->build_timed_node('c2', ['c1'], []),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids);
        $this->assertEquals(['c1', 'c2'], $pathids[0]);
    }

    /**
     * Three AND-chained conditions without feedback.
     * Expected: one path with three nodes.
     */
    public function test_three_and_chained_conditions() {
        $nodes = [
            $this->build_parent_courses_node('c1', ['starting_condition'], ['c2']),
            $this->build_specific_course_node('c2', ['c1'], ['c3']),
            $this->build_timed_node('c3', ['c2'], []),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids);
        $this->assertEquals(['c1', 'c2', 'c3'], $pathids[0]);
    }

    // =========================================================================
    // TESTS: get_restriction_paths() – feedback node skipping
    // =========================================================================

    /**
     * THE ORIGINAL BUG: parent_courses → [feedback, timed]
     * The feedback node has type="feedback" but no data.label.
     * The path must skip the feedback node and follow condition_2 (timed).
     */
    public function test_skips_feedback_by_type_in_chain() {
        $nodes = [
            $this->build_parent_courses_node(
                'condition_1',
                ['starting_condition'],
                ['condition_1_feedback', 'condition_2']
            ),
            $this->build_feedback_node('condition_1_feedback', 'condition_1'),
            $this->build_timed_node('condition_2', ['condition_1'], []),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids, 'Should find exactly one path');
        $this->assertCount(2, $pathids[0], 'Path should have 2 nodes (feedback skipped)');
        $this->assertEquals(['condition_1', 'condition_2'], $pathids[0]);
    }

    /**
     * Feedback node is the FIRST child in childCondition array.
     * The path must skip it and find the real next condition.
     */
    public function test_feedback_first_in_child_list() {
        $nodes = [
            $this->build_parent_courses_node(
                'c1',
                ['starting_condition'],
                ['c1_feedback', 'c2']
            ),
            $this->build_feedback_node('c1_feedback', 'c1'),
            $this->build_timed_node('c2', ['c1'], []),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertEquals(['c1', 'c2'], $pathids[0]);
    }

    /**
     * Feedback node is the LAST child in childCondition array.
     * The real condition should still be found.
     */
    public function test_feedback_last_in_child_list() {
        $nodes = [
            $this->build_parent_courses_node(
                'c1',
                ['starting_condition'],
                ['c2', 'c1_feedback']
            ),
            $this->build_timed_node('c2', ['c1'], []),
            $this->build_feedback_node('c1_feedback', 'c1'),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertEquals(['c1', 'c2'], $pathids[0]);
    }

    /**
     * Multiple feedback nodes interspersed in a chain.
     * c1 → [c1_fb, c2] → [c2_fb, c3]
     * Expected path: [c1, c2, c3]
     */
    public function test_multiple_feedback_nodes_in_chain() {
        $nodes = [
            $this->build_parent_courses_node(
                'c1',
                ['starting_condition'],
                ['c1_fb', 'c2']
            ),
            $this->build_feedback_node('c1_fb', 'c1'),
            $this->build_specific_course_node(
                'c2',
                ['c1'],
                ['c2_fb', 'c3']
            ),
            $this->build_feedback_node('c2_fb', 'c2'),
            $this->build_timed_node('c3', ['c2'], []),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids);
        $this->assertEquals(['c1', 'c2', 'c3'], $pathids[0]);
    }

    /**
     * Feedback node at the START of the chain (parentCondition = starting_condition).
     * This is unusual but should not break path extraction.
     * The feedback node should be skipped entirely – no path starts from it.
     */
    public function test_feedback_node_at_start_is_ignored() {
        $nodes = [
            // This feedback node has parentCondition = starting_condition.
            // It should NOT generate a path.
            [
                'id' => 'fb_start',
                'type' => 'feedback',
                'data' => ['visibility' => true],
                'parentCondition' => ['starting_condition'],
                'childCondition' => [],
            ],
            $this->build_timed_node('c1'),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        // The feedback node at start should not create a path.
        // Only the timed node should create a path.
        $this->assertCount(1, $pathids);
        $this->assertEquals(['c1'], $pathids[0]);
    }

    /**
     * Only feedback nodes, no real conditions.
     * Expected: no paths.
     */
    public function test_only_feedback_nodes() {
        $nodes = [
            [
                'id' => 'fb1',
                'type' => 'feedback',
                'data' => ['visibility' => true],
                'parentCondition' => ['starting_condition'],
                'childCondition' => [],
            ],
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);

        $this->assertEmpty($paths);
    }

    // =========================================================================
    // TESTS: get_restriction_paths() – OR logic (parallel paths)
    // =========================================================================

    /**
     * Two parallel paths from starting_condition (OR logic).
     * Path A: parent_courses
     * Path B: timed
     * Expected: two paths, each with one node.
     */
    public function test_two_parallel_or_paths() {
        $nodes = [
            $this->build_parent_courses_node('ca'),
            $this->build_timed_node('cb'),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(2, $pathids, 'Should find two OR-linked paths');

        $flatids = array_map(function ($p) {
            return $p[0];
        }, $pathids);
        $this->assertContains('ca', $flatids);
        $this->assertContains('cb', $flatids);
    }

    /**
     * Three parallel paths from starting_condition (OR logic).
     */
    public function test_three_parallel_or_paths() {
        $nodes = [
            $this->build_parent_courses_node('ca'),
            $this->build_timed_node('cb'),
            $this->build_specific_course_node('cc'),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(3, $pathids);
    }

    // =========================================================================
    // TESTS: get_restriction_paths() – mixed AND/OR with feedback
    // =========================================================================

    /**
     * Mixed AND/OR with feedback nodes:
     * Path A: c_a1 (parent_courses) → [c_a1_fb, c_a2 (timed)]   (AND chain)
     * Path B: c_b1 (timed)                                         (single)
     *
     * Expected:
     * - Path A: [c_a1, c_a2]  (feedback skipped)
     * - Path B: [c_b1]
     */
    public function test_mixed_and_or_with_feedback() {
        $nodes = [
            // Path A: AND chain with feedback.
            $this->build_parent_courses_node(
                'c_a1',
                ['starting_condition'],
                ['c_a1_fb', 'c_a2']
            ),
            $this->build_feedback_node('c_a1_fb', 'c_a1'),
            $this->build_timed_node('c_a2', ['c_a1'], []),
            // Path B: single condition.
            $this->build_timed_node('c_b1'),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(2, $pathids, 'Should find two paths');

        // Find paths by length.
        $andpath = null;
        $orpath = null;
        foreach ($pathids as $p) {
            if (count($p) === 2) {
                $andpath = $p;
            } else if (count($p) === 1) {
                $orpath = $p;
            }
        }

        $this->assertNotNull($andpath, 'AND path should exist');
        $this->assertNotNull($orpath, 'OR path should exist');
        $this->assertEquals(['c_a1', 'c_a2'], $andpath);
        $this->assertEquals(['c_b1'], $orpath);
    }

    /**
     * Complex real-world scenario matching the original dndnode_2 structure:
     * Path A: condition_1 (parent_courses) → [condition_1_feedback, condition_2 (timed)]
     *
     * With condition_1_feedback having:
     * - type: "feedback"
     * - NO data.label
     * - data.childCondition: "condition_1"
     */
    public function test_real_world_dndnode2_structure() {
        $nodes = [
            [
                'id' => 'condition_1',
                'type' => 'condition',
                'data' => [
                    'label' => 'parent_courses',
                    'value' => [],
                ],
                'parentCondition' => ['starting_condition'],
                'childCondition' => ['condition_1_feedback', 'condition_2'],
            ],
            [
                'id' => 'condition_1_feedback',
                'type' => 'feedback',
                'data' => [
                    'childCondition' => 'condition_1',
                    'visibility' => true,
                    'feedback_before' => 'Bitte schließen Sie den Vorgängerkurs ab.',
                    'feedback_after' => '',
                    'feedback_inbetween' => 'Vorgängerkurs abgeschlossen!',
                ],
                'parentCondition' => ['condition_1'],
                'childCondition' => [],
            ],
            [
                'id' => 'condition_2',
                'type' => 'condition',
                'data' => [
                    'label' => 'timed',
                    'value' => [
                        'start' => '2026-03-16T21:30',
                        'end' => null,
                    ],
                ],
                'parentCondition' => ['condition_1'],
                'childCondition' => [],
            ],
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids,
            'Real-world dndnode_2: exactly one path');
        $this->assertEquals(['condition_1', 'condition_2'], $pathids[0],
            'Real-world dndnode_2: path must include timed condition');
    }

    // =========================================================================
    // TESTS: get_restriction_paths() – edge cases
    // =========================================================================

    /**
     * Empty restriction nodes array.
     * Expected: no paths.
     */
    public function test_empty_restriction_nodes() {
        $paths = $this->call_method('get_restriction_paths', [[], []]);
        $this->assertEmpty($paths);
    }

    /**
     * Node without parentCondition field.
     * Expected: no paths (node is not reachable from starting_condition).
     */
    public function test_node_without_parent_condition() {
        $nodes = [
            [
                'id' => 'orphan',
                'type' => 'condition',
                'data' => ['label' => 'timed', 'value' => ['start' => null, 'end' => null]],
                // No parentCondition field.
                'childCondition' => [],
            ],
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $this->assertEmpty($paths);
    }

    /**
     * Node with parentCondition as string instead of array.
     * Expected: handled gracefully (converted to array internally).
     */
    public function test_parent_condition_as_string() {
        $nodes = [
            [
                'id' => 'c1',
                'type' => 'condition',
                'data' => ['label' => 'timed', 'value' => ['start' => null, 'end' => null]],
                'parentCondition' => 'starting_condition', // String instead of array.
                'childCondition' => [],
            ],
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids);
        $this->assertEquals(['c1'], $pathids[0]);
    }

    /**
     * Child references a non-existent node.
     * Expected: path ends at the current node (broken chain handled gracefully).
     */
    public function test_broken_child_reference() {
        $nodes = [
            $this->build_parent_courses_node(
                'c1',
                ['starting_condition'],
                ['nonexistent_node']
            ),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids);
        $this->assertEquals(['c1'], $pathids[0], 'Path should end at c1 (broken child reference)');
    }

    /**
     * All children are feedback nodes.
     * Expected: path ends at the current node (no real next condition).
     */
    public function test_all_children_are_feedback() {
        $nodes = [
            $this->build_parent_courses_node(
                'c1',
                ['starting_condition'],
                ['c1_fb1', 'c1_fb2']
            ),
            $this->build_feedback_node('c1_fb1', 'c1'),
            $this->build_feedback_node('c1_fb2', 'c1'),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids);
        $this->assertEquals(['c1'], $pathids[0],
            'Path should end at c1 when all children are feedback');
    }

    /**
     * childCondition is not an array (e.g. null or empty string).
     * Expected: path ends at the current node.
     */
    public function test_child_condition_not_array() {
        $nodes = [
            [
                'id' => 'c1',
                'type' => 'condition',
                'data' => ['label' => 'timed', 'value' => ['start' => null, 'end' => null]],
                'parentCondition' => ['starting_condition'],
                'childCondition' => null,
            ],
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);
        $pathids = $this->extract_path_ids($paths);

        $this->assertCount(1, $pathids);
        $this->assertEquals(['c1'], $pathids[0]);
    }

    /**
     * Verify that path node data is preserved (not just ids).
     * The returned path nodes must contain the full data structure
     * so that check_node_restrictions() can read data.label and data.value.
     */
    public function test_path_nodes_contain_full_data() {
        $nodes = [
            $this->build_parent_courses_node('c1', ['starting_condition'], ['c2']),
            $this->build_timed_node('c2', ['c1'], []),
        ];

        $paths = $this->call_method('get_restriction_paths', [$nodes, $this->build_nodemap($nodes)]);

        $this->assertCount(1, $paths);
        $this->assertCount(2, $paths[0]);

        // First node: parent_courses.
        $this->assertEquals('parent_courses', $paths[0][0]['data']['label']);
        $this->assertArrayHasKey('value', $paths[0][0]['data']);

        // Second node: timed.
        $this->assertEquals('timed', $paths[0][1]['data']['label']);
        $this->assertArrayHasKey('value', $paths[0][1]['data']);
        $this->assertEquals('2026-06-15T09:00', $paths[0][1]['data']['value']['start']);
    }
}
