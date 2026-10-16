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
 * time-conditions - chain T of the E2E plan (section 9), T1 to T5.
 *
 *     before the window   -> the entry course stays closed
 *     inside the window   -> it opens
 *     after the window    -> it closes again
 *
 * The fixture path "E2E Zeitfenster" has an entry node whose only access
 * criterion is a fixed window, 2030-01-15 10:00 to 12:00 UTC. The server's
 * clock is moved there with libfaketime - preloaded into the PHP web server
 * and the CLI task runner only, steered through one file (support/fixtures:
 * setServerTime). The browser, the database and this runner keep the real
 * time. Nothing waits for a real minute.
 *
 * Precision: the evaluation itself is exact to the second and half-open
 * (start <= now < end, #581) - that is proven in PHPUnit. Through the whole
 * system the change is applied by a re-evaluation task that local_adele
 * schedules at each boundary PLUS a buffer of two minutes
 * (adhoc_task_helper::RUNTIME_BUFFER). So the browser chain checks one minute
 * before the start, and two minutes plus ten seconds after start and end -
 * points at which the outcome is unambiguous.
 *
 * Requires the test clock (ADELE_FAKETIME_LIB, ADELE_FAKETIME_FILE); without
 * it the chain is skipped, loudly, rather than passing on the real date.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test } from '@playwright/test';
import { loginAs } from '../support/env';
import {
  completeCourse,
  drainTaskQueue,
  expectCourseClosed,
  expectCourseClosedAfterTasks,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  hasServerClock,
  reseedFixtures,
  resetServerTime,
  runCompletion,
  setServerTime,
} from '../support/fixtures';

/** The scheduling buffer of local_adele's re-evaluation, plus a margin. */
const AFTER_BUFFER = 120 + 10;

// Every TEST starts from the seeded state, on the real clock: moving the
// server clock runs every task due by then, for every learner, so a test
// that jumped to 2030 must not leave its consequences to the next one
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeEach(() => reseedFixtures());

test.describe('ADELE-E2E-T — a fixed time window opens and closes the entry course', () => {
  test.skip(!hasServerClock(), 'needs the server test clock (libfaketime): ADELE_FAKETIME_LIB and ADELE_FAKETIME_FILE');

  test.afterEach(() => {
    // Whatever happened, the next chain starts on the real clock.
    resetServerTime();
  });

  // No order between these tests or relative to other files: each starts
  // from a fresh seed on the real clock (beforeEach above), and the next
  // file starts from one as well. Formerly the file had to sort last
  // ("zz-") and its tests had to run shortest jump first.
  test('T3: a relative period - open from the assignment, closed one day later', { tag: ['@story:T3'] }, async ({ page }) => {
    // Fixture "E2E Zeitdauer": the entry node is open for one day counted
    // from the learner's assignment to the path.
    const learner = fixture('ADELE_FIXTURE_DURATION_LEARNER');
    const from = Number(fixture('ADELE_FIXTURE_DURATION_FROM'));
    const entry = fixtureCourse('T01');

    await test.step('right after the assignment, the course is open', async () => {
      await loginAs(page, learner, fixturePassword());
      await expectCourseOpenAfterTasks(page, entry, 'the period has just begun');
    });

    await test.step('one day later, the course is closed', async () => {
      setServerTime(from + 86400 + AFTER_BUFFER);
      await loginAs(page, learner, fixturePassword());
      await expectCourseClosedAfterTasks(page, entry, 'the period of one day is over');
    });
  });

  test('T1/T2: closed before, open inside, closed after the window', { tag: ['@story:T1', '@story:T2'] }, async ({ page }) => {
    const learner = fixture('ADELE_FIXTURE_TIME_LEARNER');
    const start = Number(fixture('ADELE_FIXTURE_TIMEWINDOW_START'));
    const end = Number(fixture('ADELE_FIXTURE_TIMEWINDOW_END'));
    const entry = fixtureCourse('T01');

    await test.step('T1: one minute before the start, the course is closed', async () => {
      setServerTime(start - 60);
      drainTaskQueue();
      await loginAs(page, learner, fixturePassword());
      await expectCourseClosed(page, entry, 'the window has not opened yet');
    });

    await test.step('T1: after the start, the course opens', async () => {
      setServerTime(start + AFTER_BUFFER);
      await loginAs(page, learner, fixturePassword());
      await expectCourseOpenAfterTasks(page, entry, 'the window is open, so the entry course must be');
    });

    await test.step('T2: after the end, the course closes again', async () => {
      setServerTime(end + AFTER_BUFFER);
      await loginAs(page, learner, fixturePassword());
      await expectCourseClosedAfterTasks(page, entry, 'the window has closed, so must the course');
    });
  });

  test('T4/T5: the window AND / OR a completed predecessor', { tag: ['@story:T4', '@story:T5'] }, async ({ page }) => {
    // Node 2 (T02) of two fixture paths: "E2E Zeit UND Vorgänger" and
    // "E2E Zeit ODER Vorgänger". Two learners each - "first" completes T01
    // before the window, "late" only inside it - so every row of the plan's
    // truth tables is walked. ALL steps on the real clock come first, then
    // one jump: a jump runs every task due by then, for every learner. Their
    // window (2031) lies after T1/T2's (2030), so neither chain can consume
    // the other's re-evaluation tasks - which is why this test runs last.
    const t02 = fixtureCourse('T02');
    const who = (key: string): string => fixture(`ADELE_FIXTURE_T_${key}`);

    await test.step('neither: everybody locked out of T02', async () => {
      drainTaskQueue();
      for (const key of ['AND_FIRST', 'AND_LATE', 'OR_FIRST', 'OR_LATE']) {
        await loginAs(page, who(key), fixturePassword());
        await expectCourseClosed(page, t02, `${key}: no predecessor, no window`);
      }
    });

    await test.step('predecessor only: AND stays locked, OR opens', async () => {
      for (const key of ['AND_FIRST', 'OR_FIRST']) {
        await loginAs(page, who(key), fixturePassword());
        await completeCourse(page, fixtureCourse('T01'));
      }
      runCompletion();
      await loginAs(page, who('OR_FIRST'), fixturePassword());
      await expectCourseOpenAfterTasks(page, t02, 'OR: the completed predecessor is enough');
      await loginAs(page, who('AND_FIRST'), fixturePassword());
      await expectCourseClosed(page, t02, 'AND: the window is still missing');
    });

    await test.step('window only: AND stays locked, OR opens', async () => {
      setServerTime(Number(fixture('ADELE_FIXTURE_TIMEWINDOW2_START')) + AFTER_BUFFER);
      await loginAs(page, who('OR_LATE'), fixturePassword());
      await expectCourseOpenAfterTasks(page, t02, 'OR: the open window is enough');
      await loginAs(page, who('AND_LATE'), fixturePassword());
      await expectCourseClosed(page, t02, 'AND: the predecessor is still missing');
    });

    await test.step('both: AND opens', async () => {
      await loginAs(page, who('AND_FIRST'), fixturePassword());
      await expectCourseOpenAfterTasks(page, t02, 'AND: predecessor completed and window open');
      await loginAs(page, who('AND_LATE'), fixturePassword());
      await completeCourse(page, fixtureCourse('T01'));
      runCompletion();
      await expectCourseOpenAfterTasks(page, t02, 'AND: completed inside the window');
    });
  });
});
