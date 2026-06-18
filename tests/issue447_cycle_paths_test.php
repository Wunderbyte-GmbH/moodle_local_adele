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
 * Regression test for GitHub issue #447:
 *   A learning path with a loop (A->B->C->B) made findpaths() recurse forever
 *   (no visited-node guard), exhausting memory / stack and breaking the views.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\node_completion::get_possible_paths
 */
final class issue447_cycle_paths_test extends advanced_testcase {
    /**
     * Build node objects for A->B->C with a back-edge C->B (the loop) and an
     * exit edge C->D leading to a terminal node D.
     *
     * @return array
     */
    private function cyclic_nodes(): array {
        $mk = function (string $id, array $parents, array $children) {
            return (object) ['id' => $id, 'parentCourse' => $parents, 'childCourse' => $children];
        };
        return [
            $mk('A', ['starting_node'], ['B']),
            $mk('B', ['A', 'C'], ['C']),
            $mk('C', ['B'], ['B', 'D']), // C loops back to B and also exits to D.
            $mk('D', ['C'], []),
        ];
    }

    /**
     * get_possible_paths must terminate on a looped graph and return the finite
     * acyclic path(s) to a terminal node (issue #447).
     *
     * @return void
     */
    public function test_looped_graph_paths_terminate(): void {
        $this->resetAfterTest(true);

        $paths = node_completion::get_possible_paths($this->cyclic_nodes());

        // Exactly one acyclic path reaches the terminal node D; the B->C->B loop
        // must not spawn infinite paths.
        $this->assertCount(1, $paths, 'A looped graph must yield a finite set of paths (#447).');
        $this->assertSame(['A', 'B', 'C', 'D'], $paths[0]);
    }

    /**
     * Control: a normal "diamond" DAG (A->B->D and A->C->D, no cycle) must still
     * enumerate BOTH paths. The cycle guard must only fire on a real back-edge,
     * not on legitimate re-convergence onto a shared descendant.
     *
     * @return void
     */
    public function test_diamond_dag_still_enumerates_both_paths(): void {
        $this->resetAfterTest(true);

        $mk = function (string $id, array $parents, array $children) {
            return (object) ['id' => $id, 'parentCourse' => $parents, 'childCourse' => $children];
        };
        $nodes = [
            $mk('A', ['starting_node'], ['B', 'C']),
            $mk('B', ['A'], ['D']),
            $mk('C', ['A'], ['D']),
            $mk('D', ['B', 'C'], []),
        ];

        $paths = node_completion::get_possible_paths($nodes);

        $this->assertCount(2, $paths, 'A diamond DAG must still yield both root->leaf paths.');
        $this->assertSame(['A', 'B', 'D'], $paths[0]);
        $this->assertSame(['A', 'C', 'D'], $paths[1]);
    }
}
