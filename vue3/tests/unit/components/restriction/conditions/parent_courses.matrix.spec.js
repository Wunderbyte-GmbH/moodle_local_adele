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
 * parent_courses.vue - "n of m predecessors" (Jest issue, H2): 1 of 1,
 * 1 of 2, 2 of 2; what is offered, what is emitted, the boundaries.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import parentCourses from '../../../../../components/restriction/conditions/parent_courses.vue';
import { makeStore } from '../../../fixtures/storeFactory';
import { makeLearningPath, makeTreeNode } from '../../../fixtures/learningPathFactory';

/**
 * Node `target` with the given predecessors, and the condition's stored value.
 *
 * @param {string[]} parents
 * @param {number|undefined} stored min_courses already stored
 */
async function editor(parents, stored) {
  const nodes = [...parents.map((id) => makeTreeNode(id, { childCourse: ['target'] })), makeTreeNode('target', { parentCourse: parents })];
  const wrapper = mount(parentCourses, {
    props: {
      restriction: { node_id: 'condition_1', description: 'n of m', value: stored ? { min_courses: stored } : undefined },
      learningpath: makeLearningPath(nodes),
    },
    global: { plugins: [makeStore({ node: { node_id: 'target' }, strings: { restriction_select_number: 'Number' } })] },
  });
  await wrapper.vm.$nextTick();
  const events = () => wrapper.emitted('update:modelValue');
  return { wrapper, events, select: () => wrapper.find('#restriction-condition_1-min') };
}

describe('parent_courses.vue - n of m', () => {
  it.each([
    ['1 of 1', ['dndnode_1'], undefined, 1, [1]],
    ['1 of 2', ['dndnode_1', 'dndnode_2'], undefined, 1, [1, 2]],
    ['2 of 2', ['dndnode_1', 'dndnode_2'], 2, 2, [1, 2]],
    ['2 of 3', ['dndnode_1', 'dndnode_2', 'dndnode_3'], 2, 2, [1, 2, 3]],
  ])('%s: offers %j, emits min %s and the predecessors', async (_label, parents, stored, min, offered) => {
    const e = await editor(parents, stored);
    const options = e.select().findAll('option').filter((o) => o.attributes('disabled') === undefined).map((o) => Number(o.element.value));
    expect(options).toEqual(offered);
    expect(e.events()[0][0]).toEqual({ min_courses: min, courses_id: parents });
    expect(e.wrapper.text()).toContain(`/ ${parents.length}`);
  });

  it('a change of the number is emitted', async () => {
    const e = await editor(['dndnode_1', 'dndnode_2']);
    await e.select().setValue('2');
    const emitted = e.events();
    expect(emitted[emitted.length - 1][0].min_courses).toBe(2);
  });

  it('a starting node offers no number and emits no predecessors', async () => {
    const wrapper = mount(parentCourses, {
      props: {
        restriction: { node_id: 'condition_1', description: 'n of m' },
        learningpath: makeLearningPath([makeTreeNode('target')]),
      },
      global: { plugins: [makeStore({ node: { node_id: 'target' } })] },
    });
    await wrapper.vm.$nextTick();
    expect(wrapper.find('select').exists()).toBe(false);
    expect(wrapper.emitted('update:modelValue')[0][0]).toEqual({ min_courses: 1, courses_id: [] });
  });
});
