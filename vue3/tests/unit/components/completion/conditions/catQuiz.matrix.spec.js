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
 * catQuiz.vue - a CAT result as completion (Jest issue, I4): no catquiz,
 * choosing a test, the parent-scale mode, clearing, a stored value, and
 * the threshold of a scale set by the person.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount, flushPromises } from '@vue/test-utils';
import catQuiz from '../../../../../components/completion/conditions/catQuiz.vue';
import { makeStore } from '../../../fixtures/storeFactory';

const SCALES = () => ({ parent: { id: 1, name: 'Skala', scale: '', attempts: '' }, sub: [{ id: 2, name: 'Teil', scale: '', attempts: '' }] });

/**
 * The editor with the given catquiz answers.
 *
 * @param {object[]} tests What fetchCatquizTests returns.
 * @param {object} value The stored condition value, if any.
 */
async function editor(tests, value) {
  const calls = [];
  const store = makeStore({}, {
    actions: {
      fetchCatquizTests: () => (calls.push(['tests']), Promise.resolve(tests)),
      fetchCatquizScales: (_ctx, args) => (calls.push(['scales', args]), Promise.resolve(SCALES())),
      fetchCatquizParentScales: () => (calls.push(['parentscales']), Promise.resolve([{ id: 1, name: 'Skala' }])),
      fetchCatquizParentScale: (_ctx, args) => (calls.push(['parentscale', args]), Promise.resolve(SCALES())),
    },
  });
  const wrapper = mount(catQuiz, {
    props: { modelValue: value || null, completion: { node_id: 'dndnode_1', description: 'CAT', value } },
    global: { plugins: [store], stubs: { DropdownInput: { name: 'DropdownInput', template: '<div />', emits: ['update:value'] } } },
  });
  await flushPromises();
  const choose = async (test) => {
    wrapper.findComponent({ name: 'DropdownInput' }).vm.$emit('update:value', test);
    await flushPromises();
  };
  const last = () => {
    const events = wrapper.emitted('update:modelValue') || [];
    return events.length ? events[events.length - 1][0] : undefined;
  };
  return { wrapper, calls, choose, last };
}

describe('catQuiz.vue', () => {
  it('without catquiz tests (plugin missing or no test): a notice, nothing to choose', async () => {
    const e = await editor([]);
    expect(e.wrapper.text()).toContain('no_catquiz_class');
    expect(e.wrapper.findComponent({ name: 'DropdownInput' }).exists()).toBe(false);
  });

  it('choosing a test fetches its scales and stores test, course, component and scales', async () => {
    const e = await editor([{ id: 5, name: 'CAT 5' }]);
    expect(e.wrapper.find('.adele-condition-warning').exists()).toBe(true);
    await e.choose({ id: 5, courseid: 9, componentid: 33 });
    expect(e.calls).toContainEqual(['scales', { testid: 5 }]);
    expect(e.last()).toEqual({ testid: 5, testid_courseid: 9, componentid: 33, scales: SCALES() });
    expect(e.wrapper.find('.adele-condition-warning').exists()).toBe(false);
  });

  it('an entry with the id 0 counts as no choice (as implemented)', async () => {
    // `test.id || null` turns the id 0 into null, so the component's
    // parent-scale branch (`selectedTest == 0`) cannot be reached by a
    // choice; the server offers no test with the id 0 either (catquiz.php
    // lists real test records). Pinned, not judged.
    const e = await editor([{ id: 0, name: 'any' }]);
    await e.choose({ id: 0, courseid: 9, componentid: 33 });
    expect(e.last()).toBeUndefined();
    expect(e.wrapper.find('.adele-condition-warning').exists()).toBe(true);
  });

  it('clearing the choice clears the stored value', async () => {
    const e = await editor([{ id: 5, name: 'CAT 5' }]);
    await e.choose({ id: 5, courseid: 9, componentid: 33 });
    await e.choose(null);
    expect(e.last()).toEqual({});
  });

  it('a stored value is restored: test, scales, parent scale', async () => {
    const value = { testid: 5, testid_courseid: 9, componentid: 33, scales: SCALES(), parentscales: 1 };
    const e = await editor([{ id: 5, name: 'CAT 5' }], value);
    expect(e.calls).toContainEqual(['parentscales']);
    expect(e.wrapper.find('select').element.value).toBe('1');
    expect(e.wrapper.text()).toContain('Skala');
  });

  it('the threshold of the parent scale is set by the person and stored', async () => {
    const value = { testid: 5, testid_courseid: 9, componentid: 33, scales: SCALES() };
    const e = await editor([{ id: 5, name: 'CAT 5' }], value);
    await e.wrapper.find('.item-container').trigger('click');
    await e.wrapper.find('#completion-dndnode_1-scale-value').setValue('2.5');
    await e.wrapper.find('#completion-dndnode_1-attempts').setValue('1');
    await e.wrapper.findAll('button').filter((b) => b.text() === 'conditions_set_values')[0].trigger('click');
    await flushPromises();
    expect(e.last().scales.parent).toMatchObject({ scale: '2.5', attempts: '1' });
  });
});
