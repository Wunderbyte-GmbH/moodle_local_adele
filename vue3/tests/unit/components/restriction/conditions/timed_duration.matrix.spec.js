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
 * timed_duration.vue - the editing period as a matrix (Jest issue, H6):
 * every start mode x every unit x amounts empty / 0 / 1 / 2 / large. What
 * the editor keeps, and the store's save check on the same values - "0"
 * as a select value (days, from the path) is a real choice, not "unset".
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// core/* are Moodle AMD modules with no file under test (see store.spec.js).
jest.mock('core/ajax', () => ({ __esModule: true, default: { call: jest.fn(() => [Promise.resolve({})]) } }), { virtual: true });
jest.mock('core/localstorage', () => ({ __esModule: true, default: { get: jest.fn(), set: jest.fn() } }), { virtual: true });
jest.mock('core/notification', () => ({ __esModule: true, default: { alert: jest.fn() } }), { virtual: true });

import { mount } from '@vue/test-utils';
import timedDuration from '../../../../../components/restriction/conditions/timed_duration.vue';
import { invalidTimedConditionLabel } from '../../../../../store.js';
import { makeStore } from '../../../fixtures/storeFactory';

const mountDuration = () => mount(timedDuration, {
  props: { modelValue: null, restriction: { node_id: 'condition_1' } },
  global: { plugins: [makeStore()], stubs: { TimeWarning: true } },
});

/** The last value emitted. */
const last = (wrapper) => {
  const events = wrapper.emitted('update:modelValue') || [];
  return events.length ? events[events.length - 1][0] : null;
};

const verdict = (value) => invalidTimedConditionLabel({
  tree: { nodes: [{ restriction: { nodes: [{ data: { label: 'timed_duration', value } }] } }] },
});

const rows = [];
for (const option of ['0', '1']) {
  for (const unit of ['0', '1', '2']) {
    for (const [amount, kept, valid] of [['', '', false], ['0', '', false], ['1', '1', true], ['2', '2', true], ['365', '365', true]]) {
      rows.push({ option, unit, amount, kept, valid });
    }
  }
}

describe('timed_duration.vue - start mode x unit x amount', () => {
  it.each(rows)('start=$option unit=$unit amount="$amount" -> kept "$kept", saveable $valid', async (c) => {
    const wrapper = mountDuration();
    await wrapper.find('#restriction-condition_1-option').setValue(c.option);
    await wrapper.find('#restriction-condition_1-format').setValue(c.unit);
    const amount = wrapper.find('#restriction-condition_1-duration');
    await amount.setValue(c.amount);
    await amount.trigger('change');
    await wrapper.vm.$nextTick();
    const value = last(wrapper);
    expect(value.selectedOption).toBe(c.option);
    expect(value.durationValue).toBe(c.unit);
    expect(value.selectedDuration ?? '').toBe(c.kept);
    expect(verdict(value)).toBe(c.valid ? null : 'timed_duration');
  });

  it('nothing chosen yet: not saveable', () => {
    expect(verdict({ selectedOption: null, durationValue: null, selectedDuration: null })).toBe('timed_duration');
  });

  it.each([
    ['start mode missing', { selectedOption: null, durationValue: '0', selectedDuration: '1' }],
    ['unit missing', { selectedOption: '0', durationValue: null, selectedDuration: '1' }],
    ['amount missing', { selectedOption: '0', durationValue: '0', selectedDuration: null }],
  ])('%s: not saveable', (_label, value) => {
    expect(verdict(value)).toBe('timed_duration');
  });

  it('"0" in both selects is a real choice: days, from the path', () => {
    expect(verdict({ selectedOption: '0', durationValue: '0', selectedDuration: '1' })).toBeNull();
    expect(verdict({ selectedOption: 0, durationValue: 0, selectedDuration: 1 })).toBeNull();
  });
});
