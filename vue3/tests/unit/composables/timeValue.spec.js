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
 * timeValue.spec module (issue #581).
 *
 * The browser converts between what the datetime-local input shows (local
 * time) and what is stored (Unix seconds). These tests pin the time zone of
 * the test process, because the whole point of the conversion is that the
 * stored value does NOT depend on it.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { toEpochSeconds, toDatetimeLocal, toDate } from '../../../composables/timeValue.js';
import { mount } from '@vue/test-utils';
import TimedDates from '../../../components/restriction/conditions/timed_dates.vue';

// 2030-01-15 10:00:00 UTC.
const START = 1894701600;

describe('timeValue (#581)', () => {
  it('passes stored seconds through', () => {
    expect(toEpochSeconds(START)).toBe(START);
    expect(toEpochSeconds(String(START))).toBe(START);
  });

  it('refuses what is not a time', () => {
    for (const value of [null, undefined, '', 'not a date', '15.01.2030 10:00', {}, NaN]) {
      expect(toEpochSeconds(value)).toBeNull();
    }
  });

  it('round-trips through the datetime-local form without losing the instant', () => {
    const shown = toDatetimeLocal(START);
    expect(shown).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
    expect(toEpochSeconds(shown)).toBe(START);
  });

  it('shows the instant in the time zone of the browser', () => {
    // The test process runs in the zone set by Jest's environment; whatever
    // it is, the hours shown must be the local hours of that same instant.
    const local = new Date(START * 1000);
    expect(toDatetimeLocal(START).slice(11)).toBe(
      `${String(local.getHours()).padStart(2, '0')}:${String(local.getMinutes()).padStart(2, '0')}`
    );
  });

  it('turns stored seconds into a Date, not into 1970', () => {
    expect(toDate(START).getTime()).toBe(START * 1000);
    expect(toDate(null)).toBeNull();
  });
});

describe('timed_dates.vue stores seconds (#581)', () => {
  const restriction = { node_id: 'condition_1', description: 'Start; End' };

  it('emits Unix seconds when a date is entered', async () => {
    const wrapper = mount(TimedDates, {
      props: { modelValue: { start: null, end: null }, restriction },
      global: { stubs: { TimeWarning: true } },
    });
    const input = wrapper.find('input[name="restriction-condition_1-start"]');
    await input.setValue(toDatetimeLocal(START));
    const emitted = wrapper.emitted('update:modelValue');
    expect(emitted[emitted.length - 1][0].start).toBe(START);
  });

  it('shows a stored timestamp in the input, and a legacy value as it was', async () => {
    const wrapper = mount(TimedDates, {
      props: { modelValue: { start: START, end: '2030-01-15T12:00' }, restriction },
      global: { stubs: { TimeWarning: true } },
    });
    // The component takes the model over in onMounted; one render later the
    // inputs carry it.
    await wrapper.vm.$nextTick();
    expect(wrapper.find('input[name="restriction-condition_1-start"]').element.value)
      .toBe(toDatetimeLocal(START));
    expect(wrapper.find('input[name="restriction-condition_1-end"]').element.value)
      .toBe('2030-01-15T12:00');
  });
});
