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
 * zz-time-feedback - chain F of the E2E plan (section 10), the part that
 * moves the server clock: what a learner READS on a node with a condition on
 * time (F1, F2), and on a node with two conditions (F3).
 *
 *   F1/F2  a fixed window: before, inside, after; open at one end; and the
 *          same window for a learner in another time zone
 *   F3     window AND predecessor: both missing, only the window missing,
 *          only the predecessor missing, both met, window over
 *          window OR predecessor: both missing, one met
 *
 * The EFFECT of these conditions is chain T (zz-time-conditions); this file
 * reads the texts. It has windows of its own - 2032 and 2033, later than
 * every window of chain T - and runs after it ("zz-time-f" sorts behind
 * "zz-time-c"): moving the clock runs every task due by then, for every
 * learner, so two chains sharing a window would consume each other's
 * re-evaluations. Within the file: everything on the real clock first, then
 * 2032, then 2033.
 *
 * Dates: every learner of this file has an explicit time zone (UTC; one of
 * them Asia/Tokyo), so the dates read here do not depend on the site's zone.
 *
 * Requires the test clock (ADELE_FAKETIME_LIB, ADELE_FAKETIME_FILE); without
 * it the chain is skipped, loudly. Texts measured first (session 006,
 * part 39); known defects at the end, see support/feedback.ts.
 *
 * Not covered: nested combinations (they need the editor's brackets, E2).
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect } from '@playwright/test';
import { fixture, hasServerClock, resetServerTime, setServerTime } from '../support/fixtures';
import {
  COLOUR,
  EN,
  expectNoRawPlaceholders,
  knownDefect,
  learnerCompletes,
  learnerReads,
} from '../support/feedback';

/** The scheduling buffer of local_adele's re-evaluation, plus a margin. */
const AFTER_BUFFER = 120 + 10;
const boundary = (window: number, edge: 'START' | 'END'): number => Number(fixture(`ADELE_FIXTURE_F_WINDOW${window}_${edge}`));
const INFO_COMPLETION = 'Completion criteria: • Complete this course.';
const PREDECESSOR = 'Completion of the predecessor „Testkurs 01“';

// FIRST in this file: they read the fixtures on the real clock, in front of
// their windows, and move nothing. After the chain below every learner is
// behind the window, and these tests would pass without having seen anything.
test.describe('ADELE-E2E-F — known defects, time (skipped; ADELE_RUN_KNOWN_DEFECTS=1 runs them)', () => {
  knownDefect('fixed window, before it opens: nobody is told they "have access until" its end', async ({ page }) => {
    // Measured: "... from 15/01/32, 10:00. From subscription you have access
    // until 15/01/32, 12:00." - the sentence of the condition "period from
    // subscription", on a fixed window, while the node is locked.
    const node = await learnerReads(page, 'TIME', 'dndnode_1');
    expect(node.status).toBe('not_accessible');
    expect(node.feedback).not.toContain('From subscription');
    expect(node.feedback).not.toContain('you have access');
  });

  knownDefect('a window open at one end does not read "Missing date"', async ({ page }) => {
    // Measured: "15/01/32, 10:00 to Missing date" and "Missing date to
    // 15/01/32, 12:00" on the information card.
    for (const key of ['TIME_START', 'TIME_END']) {
      const node = await learnerReads(page, key, 'dndnode_1');
      expect(node.info, key).not.toContain('Missing date');
    }
  });
});

test.describe('ADELE-E2E-F — what a learner reads on a node with a condition on time', () => {
  test.skip(!hasServerClock(), 'needs the server test clock (libfaketime): ADELE_FAKETIME_LIB and ADELE_FAKETIME_FILE');

  test.afterEach(() => {
    resetServerTime();
  });

  test('F1/F2 a fixed window, 2032-01-15 10:00 to 12:00 UTC: before, inside, after', async ({ page }) => {
    await test.step('before: the window is named, with date and time - in the learner\'s own zone', async () => {
      const node = await learnerReads(page, 'TIME', 'dndnode_1');
      expect(node.status).toBe('not_accessible');
      expect(node.info).toBe(`Access criteria: - 15/01/32, 10:00 to 15/01/32, 12:00 ${INFO_COMPLETION}`);
      expect(node.feedback).toContain(`${EN.locked} from 15/01/32, 10:00.`);
      expectNoRawPlaceholders(node, 'before');

      const tokyo = await learnerReads(page, 'TIME_TOKYO', 'dndnode_1');
      expect(tokyo.info, 'UTC+9').toBe(`Access criteria: - 15/01/32, 19:00 to 15/01/32, 21:00 ${INFO_COMPLETION}`);
      expect(tokyo.feedback).toContain(`${EN.locked} from 15/01/32, 19:00.`);

      const openend = await learnerReads(page, 'TIME_START', 'dndnode_1');
      expect(openend.status).toBe('not_accessible');
      expect(openend.feedback, 'a window without an end').toBe(
        `${EN.locked} from 15/01/32, 10:00. ${EN.todo} ${EN.completeCourse}`
      );

      const openstart = await learnerReads(page, 'TIME_END', 'dndnode_1');
      expect(openstart.status, 'a window without a start is open until its end').toBe('accessible');
      expect(openstart.feedback).toBe(
        `${EN.unlocked} You have access to this node until 15/01/32, 12:00. ${EN.todo} ${EN.completeCourse}`
      );
    });

    await test.step('inside: unlocked, and until when', async () => {
      setServerTime(boundary(3, 'START') + AFTER_BUFFER);
      const node = await learnerReads(page, 'TIME', 'dndnode_1', 'accessible');
      expect(node.feedback).toBe(
        `${EN.unlocked} You have access to this node until 15/01/32, 12:00. ${EN.todo} ${EN.completeCourse}`
      );
      expectNoRawPlaceholders(node, 'inside');

      const tokyo = await learnerReads(page, 'TIME_TOKYO', 'dndnode_1', 'accessible');
      expect(tokyo.feedback, 'the end, in the learner\'s own zone').toBe(
        `${EN.unlocked} You have access to this node until 15/01/32, 21:00. ${EN.todo} ${EN.completeCourse}`
      );
      expect(tokyo.info, 'card and feedback must name the same hours').toBe(
        `Access criteria: - 15/01/32, 19:00 to 15/01/32, 21:00 ${INFO_COMPLETION}`
      );

      const openend = await learnerReads(page, 'TIME_START', 'dndnode_1', 'accessible');
      expect(openend.feedback, 'no end, so no "until"').toBe(`${EN.unlocked} ${EN.todo} ${EN.completeCourse}`);
    });

    await test.step('after: the node can no longer be unlocked, and the bubble shows it', async () => {
      setServerTime(boundary(3, 'END') + AFTER_BUFFER);
      const node = await learnerReads(page, 'TIME', 'dndnode_1', 'not_accessible');
      expect(node.feedback).toBe(`${EN.gone} ${EN.todo} ${EN.completeCourse}`);
      expect(node.colour).toBe(COLOUR.gone);
      expectNoRawPlaceholders(node, 'after');

      const openstart = await learnerReads(page, 'TIME_END', 'dndnode_1', 'not_accessible');
      expect(openstart.feedback).toBe(`${EN.gone} ${EN.todo} ${EN.completeCourse}`);

      const openend = await learnerReads(page, 'TIME_START', 'dndnode_1');
      expect(openend.status, 'without an end the node stays open').toBe('accessible');
    });
  });

  test('F3 window AND / OR predecessor: the text names what is still missing', async ({ page }) => {
    // Node 2 of two paths, window 2033-01-15 10:00 to 12:00 UTC. AND has two
    // learners: one completes Testkurs 01 before the window, one only in it.
    const window = '15/01/33, 10:00 to 15/01/33, 12:00';

    await test.step('both missing: AND names both, OR offers either', async () => {
      for (const key of ['AND', 'AND_LATE']) {
        const node = await learnerReads(page, key, 'dndnode_2');
        expect(node.status).toBe('not_accessible');
        expect(node.info).toBe(`Access criteria: - ${PREDECESSOR} and ${window} ${INFO_COMPLETION}`);
        expect(node.feedback).toContain(`${EN.locked} ${PREDECESSOR} and from 15/01/33, 10:00.`);
        expectNoRawPlaceholders(node, `${key}, both missing`);
      }
      const either = await learnerReads(page, 'OR', 'dndnode_2');
      expect(either.status).toBe('not_accessible');
      expect(either.info).toBe(`Access criteria: - ${PREDECESSOR} - ${window} ${INFO_COMPLETION}`);
      expect(either.feedback).toBe(
        `${EN.locked} ${PREDECESSOR} or from 15/01/33, 10:00. ${EN.todo} ${EN.completeCourse}`
      );
      expectNoRawPlaceholders(either, 'OR, both missing');
    });

    await test.step('predecessor completed, before the window: AND names only the window, OR is unlocked', async () => {
      await learnerCompletes(page, 'AND', 'T01');
      const node = await learnerReads(page, 'AND', 'dndnode_2');
      expect(node.status).toBe('not_accessible');
      expect(node.feedback).toContain(`${EN.locked} from 15/01/33, 10:00.`);
      expect(node.feedback, 'what is met is no longer asked for').not.toContain('predecessor');

      await learnerCompletes(page, 'OR', 'T01');
      const either = await learnerReads(page, 'OR', 'dndnode_2', 'accessible');
      expect(either.feedback).toBe(`${EN.unlocked} ${EN.todo} ${EN.completeCourse}`);
    });

    await test.step('inside the window: AND unlocked for one, the other is asked for the predecessor only', async () => {
      setServerTime(boundary(4, 'START') + AFTER_BUFFER);
      const met = await learnerReads(page, 'AND', 'dndnode_2', 'accessible');
      expect(met.feedback).toBe(
        `${EN.unlocked} You have access to this node until 15/01/33, 12:00. ${EN.todo} ${EN.completeCourse}`
      );

      const late = await learnerReads(page, 'AND_LATE', 'dndnode_2');
      expect(late.status).toBe('not_accessible');
      expect(late.feedback).toContain(`${EN.locked} ${PREDECESSOR}.`);
      expect(late.feedback, 'the window is open and no longer asked for').not.toContain('from 15/01/33');

      await learnerCompletes(page, 'AND_LATE', 'T01');
      const now = await learnerReads(page, 'AND_LATE', 'dndnode_2', 'accessible');
      expect(now.feedback).toBe(
        `${EN.unlocked} You have access to this node until 15/01/33, 12:00. ${EN.todo} ${EN.completeCourse}`
      );
    });

    await test.step('after the window: AND can no longer be unlocked, OR stays open through its predecessor', async () => {
      setServerTime(boundary(4, 'END') + AFTER_BUFFER);
      const over = await learnerReads(page, 'AND', 'dndnode_2', 'not_accessible');
      expect(over.feedback).toBe(`${EN.gone} ${EN.todo} ${EN.completeCourse}`);
      expect(over.colour).toBe(COLOUR.gone);
      const either = await learnerReads(page, 'OR', 'dndnode_2');
      expect(either.status).toBe('accessible');
      expect(either.feedback).toBe(`${EN.unlocked} ${EN.todo} ${EN.completeCourse}`);
    });
  });
});
