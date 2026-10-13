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
 * feedbackNode.vue - the feedback of a COMPLETION criterion (Jest issue,
 * area D1/D2): before / inbetween / after, standard text or the author's,
 * visible or hidden conditions, and how the texts of a chain of conditions
 * are joined.
 *
 * The conditions of the chain are what useVueFlow().findNode returns; they
 * are given here as a small mockGraph, so the joining runs on real data shapes.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import feedbackNode from '../../../../components/nodes/feedbackNode.vue';
import { makeStore } from '../../fixtures/storeFactory';

let mockGraph = {};
jest.mock('@vue-flow/core', () => ({
  useVueFlow: () => ({ findNode: (id) => mockGraph[id] }),
  Handle: { name: 'Handle', render: () => null },
  Position: { Bottom: 'bottom', Top: 'top', Left: 'left', Right: 'right' },
}));

const AND = ' and ';

/**
 * A chain of conditions c1 -> c2 -> ..., each with its texts per action.
 *
 * @param {object[]} conditions e.g. [{ name: 'A' }, { name: 'B', visible: false }]
 */
function chain(conditions) {
  mockGraph = {};
  conditions.forEach((condition, index) => {
    const id = `c${index + 1}`;
    const next = index + 1 < conditions.length ? [`c${index + 2}`] : [];
    mockGraph[id] = {
      id,
      childCondition: [`${id}_feedback`, ...next],
      data: {
        visibility: condition.visible !== false,
        priority: condition.priority ?? index + 1,
        description_before: `${condition.name} before`,
        description_inbetween: `${condition.name} inbetween`,
        description_after: `${condition.name} after`,
        information: `${condition.name} info`,
      },
    };
  });
}

/**
 * Mount the feedback node of c1.
 *
 * @param {object} data Overrides of the feedback node's data.
 */
function mountFeedback(data = {}) {
  return mount(feedbackNode, {
    props: {
      data: {
        childCondition: 'c1',
        color: '#4a90d9',
        visibility: true,
        feedback_before_checkmark: true,
        feedback_inbetween_checkmark: true,
        feedback_after_checkmark: true,
        feedback_before: '',
        feedback_inbetween: '',
        feedback_after: '',
        ...data,
      },
      learningpath: { json: { tree: { nodes: [] } } },
      visibility: true,
    },
    global: { plugins: [makeStore({ strings: { course_condition_concatination_and: AND } })] },
  });
}

/** The last payload the node emitted. */
const lastEmitted = (wrapper) => {
  const emitted = wrapper.emitted('updateFeedback') || [];
  return emitted.length ? emitted[emitted.length - 1][0] : null;
};

describe('feedbackNode.vue - standard text per action', () => {
  it.each(['before', 'inbetween', 'after'])('%s: the condition\'s standard text, when "use default" is ticked', async (action) => {
    chain([{ name: 'A' }]);
    const wrapper = mountFeedback();
    await wrapper.setProps({ visibility: false });
    expect(lastEmitted(wrapper)[`feedback_${action}`]).toBe(`A ${action}`);
  });

  it.each(['before', 'inbetween', 'after'])('%s: the author\'s own text stays, when "use default" is not ticked', async (action) => {
    chain([{ name: 'A' }]);
    const wrapper = mountFeedback({ [`feedback_${action}_checkmark`]: false, [`feedback_${action}`]: 'Eigener Text' });
    await wrapper.setProps({ visibility: false });
    await wrapper.setProps({ visibility: true });
    expect(lastEmitted(wrapper)[`feedback_${action}`]).toBe('Eigener Text');
    expect(wrapper.find(`#feedback_${action}_textarea_c1`).element.value).toBe('Eigener Text');
  });
});

describe('feedbackNode.vue - a chain of conditions (AND)', () => {
  it.each([
    ['one condition', [{ name: 'A' }], 'A before'],
    ['two visible', [{ name: 'A' }, { name: 'B' }], `A before${AND}B before`],
    ['three visible', [{ name: 'A' }, { name: 'B' }, { name: 'C' }], `A before${AND}B before${AND}C before`],
    ['hidden one in the middle', [{ name: 'A' }, { name: 'B', visible: false }, { name: 'C' }], `A before${AND}C before`],
    ['hidden first', [{ name: 'A', visible: false }, { name: 'B' }], 'B before'],
    ['hidden last', [{ name: 'A' }, { name: 'B', visible: false }], 'A before'],
  ])('%s', async (_label, conditions, expected) => {
    chain(conditions);
    const wrapper = mountFeedback();
    await wrapper.setProps({ visibility: false });
    const text = lastEmitted(wrapper).feedback_before;
    expect(text).toBe(expected);
    // No doubled, leading or trailing joiner.
    expect(text.includes(`${AND}${AND}`)).toBe(false);
    expect(text.startsWith(AND)).toBe(false);
    expect(text.endsWith(AND)).toBe(false);
  });

  it('the priority of the chain is the smallest of its visible conditions', async () => {
    chain([{ name: 'A', priority: 5 }, { name: 'B', priority: 2 }, { name: 'C', priority: 1, visible: false }]);
    const wrapper = mountFeedback();
    await wrapper.setProps({ visibility: false });
    expect(lastEmitted(wrapper).feedback_priority).toBe(2);
  });

  it('information is joined for every condition, the hidden ones not included', async () => {
    chain([{ name: 'A' }, { name: 'B', visible: false }, { name: 'C' }]);
    const wrapper = mountFeedback();
    await wrapper.setProps({ visibility: false });
    expect(lastEmitted(wrapper).information).toBe(`A info${AND}C info`);
  });
});

describe('feedbackNode.vue - visibility of the feedback node', () => {
  it('the eye toggles the node, and the fields are disabled while it is hidden', async () => {
    chain([{ name: 'A' }]);
    const wrapper = mountFeedback({ feedback_before_checkmark: false });
    await wrapper.vm.$nextTick();
    const field = () => wrapper.find('#feedback_before_textarea_c1');
    expect(field().attributes('disabled')).toBeUndefined();
    await wrapper.find('button').trigger('click');
    expect(wrapper.find('i.fa-eye-slash').exists()).toBe(true);
    expect(field().attributes('disabled')).toBeDefined();
    await wrapper.find('button').trigger('click');
    expect(wrapper.find('i.fa-eye').exists()).toBe(true);
    expect(field().attributes('disabled')).toBeUndefined();
  });

  it('a ticked "use default" locks the field, unticking it opens it for the author', async () => {
    chain([{ name: 'A' }]);
    const wrapper = mountFeedback();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('#feedback_after_textarea_c1').attributes('disabled')).toBeDefined();
    await wrapper.find('#feedback_after_c1').setValue(false);
    expect(wrapper.find('#feedback_after_textarea_c1').attributes('disabled')).toBeUndefined();
  });
});
