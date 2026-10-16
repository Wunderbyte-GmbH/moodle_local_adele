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
 * modQuiz.vue - the grade threshold of a quiz completion (Jest issue, I3):
 * 0 %, just below a threshold, the threshold, just above, 100 %, and values
 * outside 0..100. Jest checks the editor's input and what it stores; whether
 * a quiz attempt meets it is PHPUnit's and Playwright's.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount, flushPromises } from '@vue/test-utils';
import modQuiz from '../../../../../components/completion/conditions/modQuiz.vue';
import { makeStore } from '../../../fixtures/storeFactory';

/**
 * The editor for a stored quiz condition.
 *
 * @param {object} value The stored value: { quizid, grade }.
 */
async function editor(value) {
  const store = makeStore({}, { actions: { fetchModQuizzes: () => Promise.resolve([{ id: '7', name: 'Quiz 7' }]) } });
  const wrapper = mount(modQuiz, {
    props: { modelValue: value, completion: { description: 'Quiz', value } },
    global: { plugins: [store], stubs: { DropdownInput: true } },
  });
  await flushPromises();
  return wrapper;
}

const lastEmitted = (wrapper) => {
  const events = wrapper.emitted('update:modelValue') || [];
  return events.length ? events[events.length - 1][0] : undefined;
};

describe('modQuiz.vue - the threshold', () => {
  it.each([
    ['0 %', '0', 0],
    ['just below 50', '49', 49],
    ['exactly 50', '50', 50],
    ['just above 50', '51', 51],
    ['100 %', '100', 100],
  ])('%s: typed %s, stored %s', async (_label, typed, stored) => {
    const wrapper = await editor({ quizid: '7', grade: 30 });
    await wrapper.find('#grade').setValue(typed);
    await flushPromises();
    expect(lastEmitted(wrapper)).toEqual({ quizid: '7', grade: stored });
  });

  it.each([
    ['above 100 is clamped to 100', '150', 100],
    ['below 0 is clamped to 0', '-5', 0],
  ])('%s (#499)', async (_label, typed, stored) => {
    const wrapper = await editor({ quizid: '7', grade: 30 });
    await wrapper.find('#grade').setValue(typed);
    await flushPromises();
    expect(lastEmitted(wrapper)).toEqual({ quizid: '7', grade: stored });
    expect(Number(wrapper.find('#grade').element.value)).toBe(stored);
  });

  it('without a quiz there is no threshold field, but a warning', async () => {
    const wrapper = await editor({});
    expect(wrapper.find('#grade').exists()).toBe(false);
    expect(wrapper.find('.adele-condition-warning').exists()).toBe(true);
  });

  it('a stored threshold is shown again', async () => {
    const wrapper = await editor({ quizid: '7', grade: 75 });
    expect(Number(wrapper.find('#grade').element.value)).toBe(75);
  });
});
