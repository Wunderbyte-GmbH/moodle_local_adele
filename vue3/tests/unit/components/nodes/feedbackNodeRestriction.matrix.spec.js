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
 * feedbackNodeRestriction.vue - the feedback of an ACCESS criterion as a
 * matrix (Jest issue, D3): standard text or the author's, a chain of one to
 * three conditions, hidden ones, empty information, the joining.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import feedbackNodeRestriction from '../../../../components/nodes/feedbackNodeRestriction.vue';
import { makeStore } from '../../fixtures/storeFactory';

let mockGraph = {};
jest.mock('@vue-flow/core', () => ({
  useVueFlow: () => ({ findNode: (id) => mockGraph[id] }),
  Handle: { name: 'Handle', render: () => null },
  Position: { Bottom: 'bottom', Top: 'top', Left: 'left', Right: 'right' },
}));

const AND = ' and ';

/** A chain c1 -> c2 -> ... of access conditions. */
function chain(conditions) {
  mockGraph = {};
  conditions.forEach((condition, index) => {
    const id = `c${index + 1}`;
    mockGraph[id] = {
      id,
      childCondition: [`${id}_feedback`, ...(index + 1 < conditions.length ? [`c${index + 2}`] : [])],
      data: {
        visibility: condition.visible !== false,
        description_before: `${condition.name} before`,
        information: condition.info ?? `${condition.name} info`,
      },
    };
  });
}

/** Mount the feedback node of c1 and re-render once. */
async function rendered(data = {}) {
  const wrapper = mount(feedbackNodeRestriction, {
    props: {
      data: { childCondition: 'c1', color: '#000', visibility: true, feedback_before_checkmark: true, feedback_before: '', ...data },
      learningpath: { json: { tree: { nodes: [] } } },
      visibility: true,
    },
    global: { plugins: [makeStore({ strings: { course_condition_concatination_and: AND } })] },
  });
  await wrapper.setProps({ visibility: false });
  const emitted = wrapper.emitted('updateFeedback');
  return { wrapper, last: emitted[emitted.length - 1][0] };
}

describe('feedbackNodeRestriction - chains', () => {
  it.each([
    ['one', [{ name: 'A' }], 'A before', 'A info'],
    ['two', [{ name: 'A' }, { name: 'B' }], `A before${AND}B before`, `A info${AND}B info`],
    ['three', [{ name: 'A' }, { name: 'B' }, { name: 'C' }], `A before${AND}B before${AND}C before`, `A info${AND}B info${AND}C info`],
    // As implemented: the information names hidden conditions as well (unlike
    // the completion feedback node, feedbackNode.vue); only the feedback text
    // leaves them out.
    ['hidden in the middle', [{ name: 'A' }, { name: 'B', visible: false }, { name: 'C' }], `A before${AND}C before`, `A info${AND}B info${AND}C info`],
    ['hidden first', [{ name: 'A', visible: false }, { name: 'B' }], 'B before', `A info${AND}B info`],
  ])('%s', async (_label, conditions, feedback, information) => {
    chain(conditions);
    const { last } = await rendered();
    expect(last.feedback_before).toBe(feedback);
    expect(last.information).toBe(information);
    expect(last.feedback_before.startsWith(AND) || last.feedback_before.endsWith(AND)).toBe(false);
  });

  it('an empty information of the first condition leaves no leading joiner', async () => {
    chain([{ name: 'A', info: '' }, { name: 'B' }]);
    const { last } = await rendered();
    expect(last.information).toBe('B info');
  });
});

describe('feedbackNodeRestriction - standard or the author\'s text', () => {
  it('the author\'s text survives re-renders, also of a chain', async () => {
    chain([{ name: 'A' }, { name: 'B' }]);
    const { wrapper, last } = await rendered({ feedback_before_checkmark: false, feedback_before: 'Erst T01 abschließen' });
    expect(last.feedback_before).toBe('Erst T01 abschließen');
    await wrapper.setProps({ visibility: true });
    const emitted = wrapper.emitted('updateFeedback');
    expect(emitted[emitted.length - 1][0].feedback_before).toBe('Erst T01 abschließen');
  });

  it('ticking "use default" again replaces the author\'s text with the standard one', async () => {
    chain([{ name: 'A' }]);
    const { wrapper } = await rendered({ feedback_before_checkmark: false, feedback_before: 'Eigen' });
    await wrapper.find('input[type="checkbox"]').setValue(true);
    const emitted = wrapper.emitted('updateFeedback');
    expect(emitted[emitted.length - 1][0].feedback_before).toBe('A before');
  });
});
