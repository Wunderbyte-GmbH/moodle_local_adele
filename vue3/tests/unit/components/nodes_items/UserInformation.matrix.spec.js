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
 * UserInformation.vue and UserFeedbackBlock.vue - what the learner reads in
 * the speech bubble (Jest issue, area E): the restriction list with its
 * fallback, the completion texts per state, OR between columns, and no raw
 * placeholders taken for finished text.
 *
 * The texts come from the server; Jest checks only which of them the
 * component shows, in which order and with which joiner.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import UserInformation from '../../../../components/nodes_items/UserInformation.vue';
import UserFeedbackBlock from '../../../../components/nodes_items/UserFeedbackBlock.vue';
import { makeStore } from '../../fixtures/storeFactory';
import { makeNode } from '../../fixtures/nodeFactory';

const OR = 'or';

/**
 * Mount with the feedback given and open the bubble.
 *
 * @param {object} feedback Overrides of completion.feedback.
 */
async function bubble(feedback) {
  const wrapper = mount(UserInformation, {
    props: { data: makeNode({ feedback }) },
    global: { plugins: [makeStore({ strings: { course_condition_concatination_or: OR } })] },
  });
  await wrapper.find('button.toggle-button').trigger('click');
  return wrapper;
}

/** The texts of the lists in the bubble, list by list. */
const lists = (wrapper) => wrapper.findAll('ul.feedback-list').map(
  (list) => list.findAll('li.feedback-item').map((item) => item.find('span').text())
).filter((list) => list.length > 0);

describe('UserInformation - the locked list: before_active, else before_valid', () => {
  it.each([
    ['both empty', {}, {}, []],
    ['only before_active', { c1: 'A' }, {}, ['A.']],
    ['only before_valid', {}, { c1: 'A' }, ['A.']],
    ['both: before_active wins', { c1: 'A' }, { c1: 'B' }, ['A.']],
    ['before_active with empty texts only: before_valid', { c1: '' }, { c1: 'B' }, ['B.']],
  ])('%s', async (_label, active, valid, expected) => {
    const wrapper = await bubble({
      status_restriction: 'before',
      status_completion: '',
      completion: null,
      restriction: { before_active: active, before_valid: valid },
    });
    const shown = lists(wrapper);
    expect(shown.length ? shown[0] : []).toEqual(expected);
  });
});

describe('UserInformation - OR between columns', () => {
  it('several open columns are joined by "or", the last item closes with a full stop', async () => {
    const wrapper = await bubble({
      status_restriction: 'before',
      status_completion: '',
      completion: null,
      restriction: { before_active: { c1: 'complete A and B', c3: 'be released manually' }, before_valid: {} },
    });
    const items = wrapper.findAll('ul.feedback-list')[0].findAll('li.feedback-item');
    expect(items.map((item) => item.find('span').text())).toEqual(['Complete A and B', 'be released manually.']);
    expect(items[0].find('.or-text').text()).toBe(OR);
    expect(items[1].find('.or-text').exists()).toBe(false);
  });

  it('once one column holds (inbetween), the open columns are no longer named as missing', async () => {
    const wrapper = await bubble({
      status_restriction: 'inbetween',
      status_completion: '',
      completion: null,
      restriction: {
        before_active: { c1: 'complete A and B' },
        before_valid: { c1: 'complete A and B' },
        inbetween: { c3: 'you were released manually' },
      },
    });
    // Case-blind: the first text of a list starts with a capital letter
    // (#596), and an absence must not pass just because of that.
    const text = wrapper.text().toLowerCase();
    expect(text).toContain('you were released manually');
    expect(text).not.toContain('complete a and b');
  });
});

describe('UserInformation - the completion texts per state', () => {
  it.each([
    ['before', { before: ['complete this course'] }, ['Complete this course.']],
    ['inbetween', { inbetween: ['finish the quiz', 'or wait to be released'] }, ['Finish the quiz', 'or wait to be released.']],
    ['after', { after: ['completed this course'], after_all: [] }, ['Completed this course.']],
  ])('%s: the texts of that state, nothing else', async (state, completion, expected) => {
    const wrapper = await bubble({
      status_restriction: 'inbetween',
      status_completion: state,
      completion: { before: ['BEFORE'], inbetween: ['INBETWEEN'], after: ['AFTER'], after_all: [], ...completion },
      restriction: { inbetween: {} },
    });
    expect(lists(wrapper).flat()).toEqual(expected);
    expect(wrapper.text()).toContain(`node_access_completion_${state}`);
  });

  it('after, with after_all: the further texts follow under their own heading', async () => {
    const wrapper = await bubble({
      status_restriction: 'inbetween',
      status_completion: 'after',
      completion: { after: ['completed T01'], after_all: { dndnode_2: 'T02 is open now' } },
      restriction: { inbetween: {} },
    });
    expect(lists(wrapper)).toEqual([['Completed T01.'], ['T02 is open now.']]);
    expect(wrapper.text()).toContain('node_access_completion_after_all');
  });

  it('no completion data at all: no list, no exception', async () => {
    const wrapper = await bubble({
      status_restriction: 'inbetween',
      status_completion: '',
      completion: null,
      restriction: { inbetween: {} },
    });
    expect(lists(wrapper)).toEqual([]);
  });
});

describe('UserFeedbackBlock - texts as they arrive', () => {
  const block = (data, title = 'restriction_before') => mount(UserFeedbackBlock, {
    props: { data, title },
    global: { plugins: [makeStore({ strings: { course_condition_concatination_or: OR } })] },
  });

  it('a text that already ends in a list keeps its end', () => {
    const wrapper = block(['<ul><li>x</li></ul>']);
    expect(wrapper.find('li.feedback-item span').html()).toContain('<ul><li>x</li></ul>');
    expect(wrapper.find('li.feedback-item span').text().endsWith('.')).toBe(false);
  });

  it('an empty first text shows nothing', () => {
    expect(block(['']).find('ul').exists()).toBe(false);
  });

  it('no raw placeholder of the templates is presented as finished text - as delivered, as implemented', () => {
    // The placeholders are replaced on the server (relation_update); the
    // component shows what it gets. This row PINS that: a raw placeholder
    // that reaches the browser is visible as such - the server-side tests
    // (PHPUnit) and chain F (Playwright, expectNoRawPlaceholders) are where
    // its absence is proven.
    const wrapper = block(['complete {node_name}']);
    expect(wrapper.text()).toContain('{node_name}');
  });

  it('the first text starts with a capital letter (#596)', () => {
    // capitalize() used to compute the capitalised text for the first item
    // and throw it away (no return, no assignment).
    const wrapper = block(['complete this course']);
    expect(wrapper.find('li.feedback-item span').text()).toBe('Complete this course.');
  });
});
