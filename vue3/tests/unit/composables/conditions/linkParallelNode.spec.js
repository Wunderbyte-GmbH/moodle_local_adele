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
 * linkParallelNode.spec module.
 *
 * @package    local_adele
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import linkParallelNode from '../../../../composables/conditions/linkParallelNode';

describe('linkParallelNode', () => {
  const graph = () => {
    const nodes = {
      dndnode_1: { id: 'dndnode_1', parentCourse: ['starting_node'], childCourse: ['dndnode_2'] },
      dndnode_2: { id: 'dndnode_2', parentCourse: ['dndnode_1'], childCourse: ['dndnode_4'] },
      dndnode_4: { id: 'dndnode_4', parentCourse: ['dndnode_2'], childCourse: [] },
    };
    return { nodes, find: (id) => nodes[id] };
  };

  it('a node parallel to a middle node: its successor names it, its predecessor leads to it', () => {
    const { nodes, find } = graph();
    linkParallelNode(find, 'dndnode_3', ['dndnode_1'], ['dndnode_4']);
    expect(nodes.dndnode_4.parentCourse).toEqual(['dndnode_2', 'dndnode_3']);
    expect(nodes.dndnode_1.childCourse).toEqual(['dndnode_2', 'dndnode_3']);
    expect(nodes.dndnode_2.parentCourse).toEqual(['dndnode_1']);
  });

  it('a node parallel to the first node: the starting marker is no node to update', () => {
    const { nodes, find } = graph();
    linkParallelNode(find, 'dndnode_3', ['starting_node'], ['dndnode_2']);
    expect(nodes.dndnode_2.parentCourse).toEqual(['dndnode_1', 'dndnode_3']);
  });

  it('does not name the same node twice', () => {
    const { nodes, find } = graph();
    linkParallelNode(find, 'dndnode_3', ['dndnode_1'], ['dndnode_4']);
    linkParallelNode(find, 'dndnode_3', ['dndnode_1'], ['dndnode_4']);
    expect(nodes.dndnode_4.parentCourse).toEqual(['dndnode_2', 'dndnode_3']);
  });

  it('copes with nodes it cannot find and with missing lists', () => {
    const nodes = { dndnode_2: { id: 'dndnode_2' } };
    linkParallelNode((id) => nodes[id], 'dndnode_3', ['dndnode_9'], ['dndnode_2', 'dndnode_8']);
    expect(nodes.dndnode_2.parentCourse).toEqual(['dndnode_3']);
  });
});
