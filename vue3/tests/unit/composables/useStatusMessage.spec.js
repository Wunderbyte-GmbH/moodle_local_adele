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
 * use Status Message.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { computed, ref } from 'vue';
import { useStatusMessage } from '../../../composables/useStatusMessage';

// Helper: wrap a plain completion.feedback object in the shape the composable
// expects and return the resolved statusMessage.
const resolve = (feedback) => {
  const data = computed(() => ({ completion: { feedback } }));
  const { statusMessage } = useStatusMessage(data);
  return statusMessage.value;
};

describe('useStatusMessage', () => {
  it('returns "0" when no completion buckets are defined', () => {
    const status = resolve({
      status_restriction: 'before',
      completion: {
        after: null,
        after_all: null,
        before: null,
        inbetween: null,
      },
    });
    expect(status).toBe('0');
  });

  it('returns "a1" for a "before" restriction with pending before/inbetween completions', () => {
    const status = resolve({
      status_restriction: 'before',
      completion: { after: [], before: ['x'], inbetween: [] },
    });
    expect(status).toBe('a1');
  });

  it('returns "a2" for an "inbetween" restriction with no after completions', () => {
    const status = resolve({
      status_restriction: 'inbetween',
      completion: { after: [], before: ['x'], inbetween: [] },
    });
    expect(status).toBe('a2');
  });

  it('returns "b" for "inbetween" restriction with after + after_all completions', () => {
    const status = resolve({
      status_restriction: 'inbetween',
      completion: { after: ['done'], after_all: { a: 1 } },
    });
    expect(status).toBe('b');
  });

  it('returns "c" for "inbetween" restriction with after but empty after_all', () => {
    const status = resolve({
      status_restriction: 'inbetween',
      completion: { after: ['done'], after_all: {} },
    });
    expect(status).toBe('c');
  });

  it('returns "d" for "after" restriction with a non-empty after_all', () => {
    const status = resolve({
      status_restriction: 'after',
      completion: { after: ['done'], after_all: ['x'] },
    });
    expect(status).toBe('d');
  });

  it('returns "e" for "after" restriction with after but empty after_all array', () => {
    const status = resolve({
      status_restriction: 'after',
      completion: { after: ['done'], after_all: [] },
    });
    expect(status).toBe('e');
  });

  it('returns "f" for "after" restriction with no after completions', () => {
    const status = resolve({
      status_restriction: 'after',
      completion: { after: [], after_all: null },
    });
    expect(status).toBe('f');
  });

  it('returns "" for an unrecognised restriction status', () => {
    const status = resolve({
      status_restriction: 'something-else',
      completion: { after: ['x'], before: ['y'] },
    });
    expect(status).toBe('');
  });

  it('does not crash when completion.feedback is missing entirely', () => {
    const data = computed(() => ({ completion: {} }));
    const { statusMessage } = useStatusMessage(data);
    // completion buckets are all undefined -> "Status 0" branch.
    expect(statusMessage.value).toBe('0');
  });
});

// The documented table of status codes (Jest issue, area A1), row by row -
// with the input, the restriction state and the expectation in the name, so
// a red row says which one.
describe('useStatusMessage - the status table', () => {
  const rows = [
    // restriction, before, inbetween, after, after_all, expected
    ['before', null, null, null, null, '0'],
    ['before', ['x'], [], [], null, 'a1'],
    ['before', [], ['x'], [], null, 'a1'],
    ['before', ['x'], [], null, null, 'a1'],
    ['inbetween', ['x'], [], [], null, 'a2'],
    ['inbetween', [], ['x'], [], null, 'a2'],
    ['inbetween', null, null, ['done'], { dndnode_2: 'next' }, 'b'],
    ['inbetween', null, null, ['done'], {}, 'c'],
    ['inbetween', null, null, ['done'], [], 'c'],
    ['after', null, null, ['done'], ['next'], 'd'],
    ['after', null, null, ['done'], { dndnode_2: 'next' }, 'd'],
    ['after', null, null, ['done'], [], 'e'],
    ['after', [], null, [], null, 'f'],
    ['after', ['x'], null, null, [], 'f'],
  ];
  it.each(rows)(
    'restriction=%s before=%j inbetween=%j after=%j after_all=%j -> "%s"',
    (restriction, before, inbetween, after, afterall, expected) => {
      expect(resolve({
        status_restriction: restriction,
        completion: { before, inbetween, after, after_all: afterall },
      })).toBe(expected);
    }
  );
});

describe('useStatusMessage - partial and missing data', () => {
  it.each([
    ['data itself is null', null],
    ['data itself is undefined', undefined],
    ['no completion', {}],
    ['no feedback', { completion: {} }],
    ['feedback without completion buckets', { completion: { feedback: { status_restriction: 'after' } } }],
  ])('%s: "0", no exception', (_label, value) => {
    const data = computed(() => value);
    const { statusMessage } = useStatusMessage(data);
    expect(() => statusMessage.value).not.toThrow();
    expect(statusMessage.value).toBe('0');
  });

  it.each([
    ['an unknown restriction state', 'somewhere'],
    ['a missing restriction state', undefined],
  ])('%s with buckets set: "" (no code is guessed)', (_label, restriction) => {
    expect(resolve({ status_restriction: restriction, completion: { before: ['x'], after: [] } })).toBe('');
  });

  it('"inbetween" with buckets present but all empty: no code (undefined), no exception', () => {
    expect(resolve({ status_restriction: 'inbetween', completion: { before: [], inbetween: [], after: [] } })).toBeUndefined();
  });

  it('"before" with buckets present but all empty: no code (undefined), no exception', () => {
    // The composable finds no row for this case and returns nothing; pinned
    // as it is, not invented.
    expect(resolve({ status_restriction: 'before', completion: { before: [], inbetween: [], after: [] } })).toBeUndefined();
  });
});

describe('useStatusMessage - reactive', () => {
  it('follows the data through a whole life cycle: 0 -> a1 -> a2 -> c -> d', () => {
    const data = ref({ completion: { feedback: { status_restriction: 'before', completion: { before: null, inbetween: null, after: null, after_all: null } } } });
    const { statusMessage } = useStatusMessage(data);
    expect(statusMessage.value).toBe('0');
    data.value = { completion: { feedback: { status_restriction: 'before', completion: { before: ['x'], after: [] } } } };
    expect(statusMessage.value).toBe('a1');
    data.value.completion.feedback.status_restriction = 'inbetween';
    expect(statusMessage.value).toBe('a2');
    data.value.completion.feedback.completion = { after: ['done'], after_all: {} };
    expect(statusMessage.value).toBe('c');
    data.value.completion.feedback.status_restriction = 'after';
    data.value.completion.feedback.completion.after_all = ['next'];
    expect(statusMessage.value).toBe('d');
  });
});
