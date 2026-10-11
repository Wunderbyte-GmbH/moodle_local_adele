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
 * zz-time-conditions - chain T of the E2E plan (section 9), T1 to T3.
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
  drainTaskQueue,
  expectCourseClosed,
  expectCourseClosedAfterTasks,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  hasServerClock,
  resetServerTime,
  setServerTime,
} from '../support/fixtures';

/** The scheduling buffer of local_adele's re-evaluation, plus a margin. */
const AFTER_BUFFER = 120 + 10;

test.describe('ADELE-E2E-T — a fixed time window opens and closes the entry course', () => {
  test.skip(!hasServerClock(), 'needs the server test clock (libfaketime): ADELE_FAKETIME_LIB and ADELE_FAKETIME_FILE');

  test.afterEach(() => {
    // Whatever happened, the next chain starts on the real clock.
    resetServerTime();
  });

  // ORDER MATTERS. Moving the server clock forward makes every task that is
  // due by then run - for ALL learners, not just this chain's. T1/T2 jump to
  // 2030, which would also fire the closing task of T3's one-day period and
  // leave that learner closed with nothing to reopen them once the clock is
  // back. So the shorter jump (T3, one day) runs first. For the same reason
  // this file has to run after every chain that does not move the clock -
  // the "zz-" prefix sorts it last in the suite, whatever files are added.
  test('T3: a relative period - open from the assignment, closed one day later', async ({ page }) => {
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

  test('T1/T2: closed before, open inside, closed after the window', async ({ page }) => {
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
});
