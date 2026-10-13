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
 * parent_node_completed.vue - "certain node completed" (Jest issue, H3): no
 * predecessor, one, several; what it lists, and the data it emits.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import parentNodeCompleted from '../../../../../components/restriction/conditions/parent_node_completed.vue';
import { makeStore } from '../../../fixtures/storeFactory';
import { makeLearningPath, makeTreeNode } from '../../../fixtures/learningPathFactory';

/**
 * Mount for node `current` of a path made of the given nodes.
 *
 * @param {object[]} nodes
 * @param {string} current
 */
const mountFor = (nodes, current) => mount(parentNodeCompleted, {
  props: {
    restriction: { node_id: 'condition_1', description: 'The node becomes accessible when ...' },
    learningpath: makeLearningPath(nodes),
  },
  global: {
    plugins: [makeStore({
      node: { node_id: current },
      strings: { restriction_parents_found: 'Parents found:', nodes_collection: 'Collection' },
    })],
  },
});

const node = (id, fullname, children = []) => makeTreeNode(id, { childCourse: children, data: { fullname } });

const emitted = (wrapper) => wrapper.emitted('update:modelValue')[0][0];

describe('parent_node_completed.vue', () => {
  it.each([
    ['no predecessor', [node('dndnode_1', 'Testkurs 01')], 'dndnode_1', [], []],
    ['one predecessor', [node('dndnode_1', 'Testkurs 01', ['dndnode_2']), node('dndnode_2', 'Testkurs 02')], 'dndnode_2',
      ['dndnode_1'], ['Testkurs 01']],
    ['two predecessors', [
      node('dndnode_1', 'Testkurs 01', ['dndnode_3']),
      node('dndnode_2', 'Testkurs 02', ['dndnode_3']),
      node('dndnode_3', 'Testkurs 03'),
    ], 'dndnode_3', ['dndnode_1', 'dndnode_2'], ['Testkurs 01', 'Testkurs 02']],
    ['a node that is not a predecessor is left out', [
      node('dndnode_1', 'Testkurs 01', ['dndnode_2']),
      node('dndnode_2', 'Testkurs 02', ['dndnode_3']),
      node('dndnode_3', 'Testkurs 03'),
    ], 'dndnode_3', ['dndnode_2'], ['Testkurs 02']],
  ])('%s', async (_label, nodes, current, ids, names) => {
    const wrapper = mountFor(nodes, current);
    // The list is filled when the component is mounted; the DOM follows a tick later.
    await wrapper.vm.$nextTick();
    expect(emitted(wrapper)).toEqual({ node_id: ids });
    const listed = wrapper.findAll('.fullname-container').map((item) => item.text());
    expect(listed).toEqual(names);
  });

  it('a predecessor without a name is shown as a collection', async () => {
    const wrapper = mountFor([node('dndnode_1', '', ['dndnode_2']), node('dndnode_2', 'Testkurs 02')], 'dndnode_2');
    await wrapper.vm.$nextTick();
    expect(wrapper.findAll('.fullname-container').map((item) => item.text())).toEqual(['Collection']);
  });

  it('shows the description it is given', () => {
    const wrapper = mountFor([node('dndnode_1', 'Testkurs 01')], 'dndnode_1');
    expect(wrapper.text()).toContain('The node becomes accessible when ...');
  });
});
