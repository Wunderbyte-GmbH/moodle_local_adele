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
 * timed_dates.vue - the start/end input of a time window (Jest issue, H5):
 * only start, only end, both, start before / equal to / after end, empty -
 * what the editor emits, and how the store's save check judges the same
 * values. Component and store must agree.
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
import timedDates from '../../../../../components/restriction/conditions/timed_dates.vue';
import { toDatetimeLocal, toEpochSeconds } from '../../../../../composables/timeValue';
import { invalidTimedConditionLabel } from '../../../../../store.js';

const START = '2030-01-15T10:00';
const END = '2030-01-15T12:00';

const mountDates = (modelValue = null) => mount(timedDates, {
  props: {
    modelValue,
    restriction: { node_id: 'condition_2', description: 'Start time;End time' },
  },
  global: { stubs: { TimeWarning: true } },
});

/** The last value the editor emitted. */
const emittedValue = (wrapper) => {
  const events = wrapper.emitted('update:modelValue') || [];
  return events.length ? events[events.length - 1][0] : undefined;
};

/** The store's save check for one timed condition with this value. */
const verdict = (value) => invalidTimedConditionLabel({
  tree: { nodes: [{ restriction: { nodes: [{ data: { label: 'timed', value } }] } }] },
});

describe('timed_dates.vue - what the editor emits', () => {
  it('shows the two descriptions it is given', async () => {
    const wrapper = mountDates();
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain('Start time');
    expect(wrapper.text()).toContain('End time');
  });

  it.each([
    ['only start', START, null],
    ['only end', null, END],
    ['start and end', START, END],
  ])('%s: typed values become Unix seconds', async (_label, start, end) => {
    const wrapper = mountDates();
    if (start) {
      await wrapper.find('#restriction-condition_2-start').setValue(start);
    }
    if (end) {
      await wrapper.find('#restriction-condition_2-end').setValue(end);
    }
    expect(emittedValue(wrapper)).toEqual({
      start: start ? toEpochSeconds(start) : null,
      end: end ? toEpochSeconds(end) : null,
    });
  });

  it('a field emptied again stores null, not 0', async () => {
    const wrapper = mountDates({ start: toEpochSeconds(START), end: null });
    await wrapper.find('#restriction-condition_2-start').setValue('');
    expect(emittedValue(wrapper).start).toBeNull();
  });

  it('a stored value is shown in the field, a legacy string as well', async () => {
    const wrapper = mountDates({ start: toEpochSeconds(START), end: END });
    await wrapper.vm.$nextTick();
    expect(wrapper.find('#restriction-condition_2-start').element.value).toBe(toDatetimeLocal(toEpochSeconds(START)));
    expect(wrapper.find('#restriction-condition_2-end').element.value).toBe(END);
  });
});

describe('timed_dates.vue and the store agree on what may be saved', () => {
  const s = toEpochSeconds(START);
  const e = toEpochSeconds(END);
  it.each([
    ['only start', { start: s, end: null }, null],
    ['only end', { start: null, end: e }, null],
    ['start before end', { start: s, end: e }, null],
    ['start equal to end', { start: s, end: s }, 'timed_order'],
    ['start after end', { start: e, end: s }, 'timed_order'],
    ['empty', { start: null, end: null }, 'timed'],
    ['empty strings', { start: '', end: '' }, 'timed'],
  ])('%s -> %s', async (_label, value, expected) => {
    // What the editor would emit for these inputs ...
    const wrapper = mountDates();
    if (value.start) {
      await wrapper.find('#restriction-condition_2-start').setValue(toDatetimeLocal(value.start));
    }
    if (value.end) {
      await wrapper.find('#restriction-condition_2-end').setValue(toDatetimeLocal(value.end));
    }
    const emitted = emittedValue(wrapper) || { start: null, end: null };
    // ... and the store's verdict on it, and on the value itself.
    expect(verdict(emitted)).toBe(expected);
    expect(verdict(value)).toBe(expected);
  });
});
