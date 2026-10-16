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
 * The structure the path editor builds, combination by combination (Jest
 * issue, area J1): A, A then B, A AND B before C, A OR B (a stack), and the
 * bracketed (A AND B) OR C and A AND (B OR C) - built with the editor's own
 * functions (addAutoRestrictions, linkParallelNode) in the order a person
 * drops the courses. Checked: nodes, parent/child references, the
 * predecessor criterion, no lost condition, no doubled reference.
 *
 * Jest checks the data structure; Playwright builds the same by drag and
 * drop (chains E1/E2).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import addAutoRestrictions from '../../../../composables/conditions/addAutoRestrictions';
import linkParallelNode from '../../../../composables/conditions/linkParallelNode';
import { makeTreeNode } from '../../fixtures/learningPathFactory';
import { keyStrings } from '../../fixtures/storeFactory';

const store = { state: { strings: keyStrings() } };

/** A small editor model: nodes by id, and the three drops a person can do. */
function graph() {
  const nodes = {};
  const find = (id) => nodes[id];
  const add = (id, overrides = {}) => {
    nodes[id] = makeTreeNode(id, overrides);
    return nodes[id];
  };
  return {
    nodes,
    first: (id) => add(id),
    // A successor: the new node hangs from `target`.
    child(id, target) {
      const node = add(id, { parentCourse: [target] });
      find(target).childCourse = [...find(target).childCourse, id];
      addAutoRestrictions(node, find(target), 'child', store);
    },
    // A parallel node: shares `target`'s predecessors and successors.
    and(id, target) {
      const t = find(target);
      const node = add(id, { parentCourse: [...t.parentCourse], childCourse: [...t.childCourse] });
      linkParallelNode(find, id, node.parentCourse, node.childCourse);
      node.childCourse.forEach((successor) => addAutoRestrictions(node, find(successor), 'and', store));
    },
    // A further course in the same node: a stack of alternatives.
    or(courseid, target) {
      find(target).data.course_node_id = [...find(target).data.course_node_id, courseid];
    },
  };
}

/** The predecessor criterion of a node: [courses_id, min_courses] or null. */
const criterion = (node) => {
  const c = (node.restriction?.nodes || []).find((n) => n?.data?.label === 'parent_courses');
  return c ? [c.data.value.courses_id, c.data.value.min_courses] : null;
};

/** References in both directions agree, and none is doubled. */
function expectConsistent(nodes) {
  for (const node of Object.values(nodes)) {
    expect(new Set(node.childCourse).size).toBe(node.childCourse.length);
    expect(new Set(node.parentCourse).size).toBe(node.parentCourse.length);
    for (const child of node.childCourse) {
      expect(nodes[child].parentCourse).toContain(node.id);
    }
    for (const parent of node.parentCourse.filter((p) => p !== 'starting_node')) {
      expect(nodes[parent].childCourse).toContain(node.id);
    }
  }
}

describe('the structures the path editor builds', () => {
  it('A: one node, open from the start, no criterion', () => {
    const g = graph();
    g.first('A');
    expect(g.nodes.A.parentCourse).toEqual(['starting_node']);
    expect(criterion(g.nodes.A)).toBeNull();
    expectConsistent(g.nodes);
  });

  it('A then B: B requires A', () => {
    const g = graph();
    g.first('A');
    g.child('B', 'A');
    expect(criterion(g.nodes.B)).toEqual([['A'], 1]);
    expectConsistent(g.nodes);
  });

  it('A AND B before C: C names both predecessors, one is enough until the author raises it', () => {
    const g = graph();
    g.first('A');
    g.child('C', 'A');
    g.and('B', 'A');
    expect(g.nodes.C.parentCourse).toEqual(['A', 'B']);
    expect(criterion(g.nodes.C)).toEqual([['A', 'B'], 1]);
    expect(g.nodes.B.parentCourse).toEqual(['starting_node']);
    expectConsistent(g.nodes);
  });

  it('A OR B: one node, two courses - no further node, no further criterion', () => {
    const g = graph();
    g.first('A');
    g.or(20, 'A');
    expect(Object.keys(g.nodes)).toEqual(['A']);
    expect(g.nodes.A.data.course_node_id).toEqual([10, 20]);
    expect(criterion(g.nodes.A)).toBeNull();
  });

  it('(A AND B) OR C before D: D names all three; the stack is one predecessor', () => {
    // T01 -> T02 -> T04 and T03 parallel to T02 (chain E2, node level).
    const g = graph();
    g.first('A');
    g.child('B', 'A');
    g.child('D', 'B');
    g.and('C', 'B');
    expect(g.nodes.D.parentCourse).toEqual(['B', 'C']);
    expect(criterion(g.nodes.D)).toEqual([['B', 'C'], 1]);
    expect(criterion(g.nodes.C)).toBeNull();
    expect(g.nodes.C.parentCourse).toEqual(['A']);
    expect(g.nodes.A.childCourse).toEqual(['B', 'C']);
    expectConsistent(g.nodes);
  });

  it('A AND (B OR C) before D: a parallel node and a stack', () => {
    const g = graph();
    g.first('BC');
    g.or(30, 'BC');
    g.child('D', 'BC');
    g.and('A', 'BC');
    expect(g.nodes.BC.data.course_node_id).toEqual([10, 30]);
    expect(g.nodes.D.parentCourse).toEqual(['BC', 'A']);
    expect(criterion(g.nodes.D)).toEqual([['BC', 'A'], 1]);
    expectConsistent(g.nodes);
  });

  it('dropping the same parallel node twice doubles nothing', () => {
    const g = graph();
    g.first('A');
    g.child('C', 'A');
    g.and('B', 'A');
    linkParallelNode((id) => g.nodes[id], 'B', ['starting_node'], ['C']);
    addAutoRestrictions(g.nodes.B, g.nodes.C, 'and', store);
    expect(g.nodes.C.parentCourse).toEqual(['A', 'B']);
    expect(criterion(g.nodes.C)).toEqual([['A', 'B'], 1]);
    expectConsistent(g.nodes);
  });
});
