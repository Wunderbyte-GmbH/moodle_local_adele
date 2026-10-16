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
 * add Auto Restrictions.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import addAutoRestrictions from '../../../../composables/conditions/addAutoRestrictions';

describe('addAutoRestrictions', () => {
  let newNode, oldNode, store;

  beforeEach(() => {
    // Mock the newNode and oldNode structures
    newNode = { node_id: 'new_node', restriction: undefined };
    oldNode = { node_id: 'old_node', restriction: undefined };

    // Mock the store object
    store = {
      state: {
        strings: {
          course_description_condition_parent_node_completed: 'Parent node completion condition',
          course_restricition_before_condition_parent_node_completed: 'Restriction before condition',
          course_name_condition_parent_node_completed: 'Parent Node Condition',
          composables_feedback_node: 'Feedback Node',
        },
      },
    };
  });


  it('should add restriction to newNode when relation is child', () => {
    const result = addAutoRestrictions(newNode, oldNode, 'child', store);

    // Check that the restriction has been added to newNode
    expect(result.restriction).toBeDefined();
    expect(result.restriction.nodes).toHaveLength(2); // Two nodes: condition_1 and condition_1_feedback
    expect(result.restriction.nodes[0].data.label).toBe('parent_courses');
    expect(result.restriction.nodes[1].label).toBe(store.state.strings.composables_feedback_node);
  });

  it('should add restriction to oldNode when relation is parent and oldNode has no restriction', () => {
    const result = addAutoRestrictions(newNode, oldNode, 'parent', store);

    // Check that the restriction has been added to oldNode
    expect(result.restriction).toBeDefined();
    expect(result.restriction.nodes).toHaveLength(2); // Two nodes: condition_1 and condition_1_feedback
    expect(result.restriction.nodes[0].data.label).toBe('parent_courses');
    expect(result.restriction.nodes[1].label).toBe(store.state.strings.composables_feedback_node);
  });

  it('should not modify oldNode when relation is parent and oldNode has a restriction', () => {
    // Simulate oldNode having a restriction already
    oldNode.restriction = { nodes: [{ id: 'condition_1', data: {} }] };

    const result = addAutoRestrictions(newNode, oldNode, 'parent', store);

    // Since oldNode already has a restriction, it should be returned unmodified
    expect(result).toBe(oldNode);
    expect(result.restriction.nodes).toHaveLength(1); // Should not be expanded
  });

  // #584: a node dropped on the side zone runs in parallel and shares the
  // successors; each shared successor must name it as a predecessor, with
  // min_courses left as it is (default 1, as in the criterion editor).
  describe('relation "and" (#584)', () => {
    const successorWith = (ids, min = 1) => ({
      id: 'dndnode_2',
      parentCourse: ['dndnode_1', 'dndnode_3'],
      restriction: { nodes: [
        { id: 'condition_1', data: { label: 'parent_courses', value: { courses_id: ids, min_courses: min } } },
        { id: 'condition_1_feedback', type: 'feedback', data: { childCondition: 'condition_1' } },
      ], edges: [] },
    });
    const parallel = { id: 'dndnode_3' };
    const criterionOf = (node) => node.restriction.nodes.find((n) => n.data.label === 'parent_courses').data.value;

    it('adds the parallel node to the successor criterion', () => {
      const result = addAutoRestrictions(parallel, successorWith(['dndnode_1']), 'and', store);
      expect(criterionOf(result).courses_id).toEqual(['dndnode_1', 'dndnode_3']);
    });

    it('leaves min_courses as it is', () => {
      expect(criterionOf(addAutoRestrictions(parallel, successorWith(['dndnode_1']), 'and', store)).min_courses).toBe(1);
      expect(criterionOf(addAutoRestrictions(parallel, successorWith(['dndnode_1'], 2), 'and', store)).min_courses).toBe(2);
    });

    it('does not add the same predecessor twice', () => {
      const result = addAutoRestrictions(parallel, successorWith(['dndnode_1', 'dndnode_3']), 'and', store);
      expect(criterionOf(result).courses_id).toEqual(['dndnode_1', 'dndnode_3']);
    });

    it('creates a criterion naming all predecessors when there is none', () => {
      const bare = { id: 'dndnode_2', parentCourse: ['dndnode_1', 'dndnode_3'], restriction: undefined };
      const result = addAutoRestrictions(parallel, bare, 'and', store);
      expect(criterionOf(result).courses_id).toEqual(['dndnode_1', 'dndnode_3']);
      expect(criterionOf(result).min_courses).toBe(1);
    });
  });

  // A course placed "before" a node that already has a predecessor (keyboard
  // dialog, #595): the node's criterion must name the new predecessor too.
  describe('relation "parent" with a predecessor already there', () => {
    const nodeWith = (restriction) => ({
      id: 'dndnode_2',
      parentCourse: ['dndnode_1', 'dndnode_3'],
      restriction,
    });
    const criterion = (ids, min = 1) => ({ nodes: [
      { id: 'condition_1', data: { label: 'parent_courses', value: { courses_id: ids, min_courses: min } } },
      { id: 'condition_1_feedback', type: 'feedback', data: { childCondition: 'condition_1' } },
    ], edges: [] });
    const before = { id: 'dndnode_3' };
    const criterionOf = (node) => node.restriction.nodes.find((n) => n.data.label === 'parent_courses').data.value;

    it.each([
      ['1 of [T01]', ['dndnode_1'], 1, ['dndnode_1', 'dndnode_3'], 1],
      ['2 of [T01, T05]', ['dndnode_1', 'dndnode_5'], 2, ['dndnode_1', 'dndnode_5', 'dndnode_3'], 2],
      ['already named', ['dndnode_1', 'dndnode_3'], 1, ['dndnode_1', 'dndnode_3'], 1],
    ])('criterion %s: names the new predecessor, keeps min_courses', (_, ids, min, expectedIds, expectedMin) => {
      const result = addAutoRestrictions(before, nodeWith(criterion(ids, min)), 'parent', store);
      expect(criterionOf(result).courses_id).toEqual(expectedIds);
      expect(criterionOf(result).min_courses).toBe(expectedMin);
    });

    it('creates a criterion naming all predecessors when there is none', () => {
      const result = addAutoRestrictions(before, nodeWith(undefined), 'parent', store);
      expect(criterionOf(result).courses_id).toEqual(['dndnode_1', 'dndnode_3']);
      expect(criterionOf(result).min_courses).toBe(1);
    });

    it('leaves criteria written by hand without a predecessor condition alone', () => {
      const manual = { nodes: [{ id: 'condition_1', data: { label: 'manual' } }], edges: [] };
      const result = addAutoRestrictions(before, nodeWith(manual), 'parent', store);
      expect(result.restriction.nodes.map((n) => n.data.label)).toEqual(['manual']);
    });
  });

  it('should return null for a relation it does not handle', () => {
    expect(addAutoRestrictions(newNode, oldNode, 'or', store)).toBe(null);
  });

});