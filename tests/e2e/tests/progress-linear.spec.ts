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
 * progress-linear - chain L1 of the E2E plan (section 11): progress on a
 * linear path with four required steps, at 0/4, 1/4, 2/4, 3/4 and 4/4.
 *
 * Fixture "E2E Linear vier Schritte": T01 -> T02 -> T03 -> T04, each node
 * completing on its course. The learner really completes one course after
 * the other. In every state, as the learner sees it in the activity's list:
 *   - the progress message: completed steps on the route, "k/4",
 *   - the concrete percentage the progress bar reports (aria-valuenow),
 *   - the bar's label ("No progress", then "25%" ...),
 *   - the visible bar: its width,
 * plus the state of the node just completed and of the next one.
 *
 * L2 (alternative routes do not lower the percentage, 100 % is reachable on
 * the valid route) is chain A (alternative-routes.spec.ts).
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { loginAs } from '../support/env';
import {
  completeCourse,
  drainTaskQueue,
  expectCourseClosed,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  nodeStatus,
  reseedFixtures,
  runCompletion,
} from '../support/fixtures';

const ACTIVITY = 'E2E L linear';

/**
 * The learner's progress as shown in the activity's list.
 *
 * @param page The page, logged in as the learner.
 */
async function progress(page: Page): Promise<string> {
  drainTaskQueue();
  await page.goto(`/course/view.php?id=${fixture('ADELE_FIXTURE_L1_HOST')}`);
  await page.getByRole('main').getByRole('link', { name: ACTIVITY }).click();
  const row = page.locator('tr').filter({ hasText: 'Linear four' }).first();
  await expect(row).toBeVisible({ timeout: 60_000 });
  const route = ((await row.locator('td').nth(3).innerText()) || '').trim();
  const bar = row.getByRole('progressbar');
  const value = (await bar.getAttribute('aria-valuenow')) || '';
  const width = ((await bar.getAttribute('style')) || '').match(/width:\s*([\d.]+)%/)?.[1] || '';
  const label = ((await row.locator('.progress-label').innerText()) || '').trim();
  return `${route} | ${value} | ${width} | ${label}`;
}

/**
 * Expect the progress for k of 4 completed steps.
 *
 * @param page The page.
 * @param k Completed steps.
 */
async function expectProgress(page: Page, k: number): Promise<void> {
  const percent = String(k * 25);
  // The real string (lang/en: nodes_items_no_progress), not a test double's.
  const label = k === 0 ? 'No progress' : `${percent}%`;
  await expect
    .poll(async () => {
      runCompletion();
      return progress(page);
    }, { message: `${k} of 4 steps completed`, timeout: 90_000, intervals: [2_000, 5_000] })
    .toBe(`${k}/4 | ${percent} | ${percent} | ${label}`);
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-L1 — progress on a linear path, step by step', () => {
  test('0/4 -> 1/4 -> 2/4 -> 3/4 -> 4/4: message, percentage, label, bar and real access', { tag: ['@story:L1'] }, async ({ page }) => {
    await loginAs(page, fixture('ADELE_FIXTURE_L1_LEARNER'), fixturePassword());

    await test.step('0/4: nothing completed yet; T01 open, T02 closed', async () => {
      await expectProgress(page, 0);
      await expectCourseOpenAfterTasks(page, fixtureCourse('T01'), 'the first step is open from the start');
      await expectCourseClosed(page, fixtureCourse('T02'), 'the second step waits for the first');
    });

    const courses = ['T01', 'T02', 'T03', 'T04'];
    for (let k = 1; k <= 4; k++) {
      await test.step(`${k}/4: the learner completes ${courses[k - 1]}`, async () => {
        await completeCourse(page, fixtureCourse(courses[k - 1]));
        await expectProgress(page, k);
        expect(await nodeStatus(page, fixture('ADELE_FIXTURE_L1_HOST'), ACTIVITY, `dndnode_${k}`),
          `${courses[k - 1]} has been completed`).toBe('completed');
        if (k < 4) {
          expect(await nodeStatus(page, fixture('ADELE_FIXTURE_L1_HOST'), ACTIVITY, `dndnode_${k + 1}`),
            `the next step opens after ${courses[k - 1]}`).toBe('accessible');
          // And really: the next course opens, the one after it does not yet.
          await expectCourseOpenAfterTasks(page, fixtureCourse(courses[k]), `${courses[k]} opens after ${courses[k - 1]}`);
          if (k < 3) {
            await expectCourseClosed(page, fixtureCourse(courses[k + 1]), `${courses[k + 1]} still waits for ${courses[k]}`);
          }
        }
      });
    }
  });
});
