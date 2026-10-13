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
 * alternative-routes - chain A of the E2E plan (section 14).
 *
 *               ┌─ T02 ─┐
 *     T01 ──────┤       ├──── T04      T04 needs one of T02 / T03
 *               └─ T03 ─┘
 *
 * Variants: only T02, only T03, both, neither. Checked: whether T04 opens
 * (path view and real access), that the alternative NOT taken is not
 * treated as required (the route completes to 100 % without it), and the
 * progress as the learner sees it in the activity's list: completed nodes on
 * the best route, and the percentage the progress bar reports to assistive
 * technology.
 *
 * Progress is measured on the best ROUTE (learning_paths::getnodeprogress):
 * both routes here have three nodes, so 1/3, 2/3 and 3/3 are 33.33, 66.67
 * and 100 %.
 *
 * Feedback texts belong to chain F; here only that T04 names the
 * alternatives while it is locked. Every state is read once more in a new
 * session: the route counted must not depend on the session.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { loginAs } from '../support/env';
import {
  completeCourse,
  expectCourseClosed,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  nodeStatus,
  nodeTexts,
  reseedFixtures,
  runCompletion,
} from '../support/fixtures';

/**
 * The learner's own row in the activity's list: completed nodes on the best
 * route ("2/3") and the progress bar's value.
 *
 * @param page The page, logged in as the learner.
 * @param variant T02, T03, BOTH or NONE.
 */
async function progressOf(page: Page, variant: string): Promise<{ route: string; percent: string }> {
  await page.goto(`/course/view.php?id=${fixture(`ADELE_FIXTURE_A_${variant}_HOST`)}`);
  await page.getByRole('main').getByRole('link', { name: `E2E A ${variant.toLowerCase()}` }).click();
  const lastname = fixture(`ADELE_FIXTURE_A_${variant}_LEARNER_NAME`).split(' ').slice(1).join(' ');
  const row = page.locator('tr').filter({ hasText: lastname }).first();
  await expect(row, 'the learner must find their own row in the list').toBeVisible({ timeout: 60_000 });
  const route = ((await row.locator('td').nth(3).innerText()) || '').trim();
  const percent = (await row.getByRole('progressbar').getAttribute('aria-valuenow')) || '';
  return { route, percent };
}

/**
 * Wait until the learner's progress shows the expected values.
 *
 * @param page The page.
 * @param variant The variant.
 * @param route Expected "completed/total" on the best route.
 * @param percent Expected percentage.
 * @param why What it means.
 */
async function expectProgress(page: Page, variant: string, route: string, percent: string, why: string): Promise<void> {
  await expect
    .poll(async () => {
      runCompletion();
      const p = await progressOf(page, variant);
      return `${p.route} ${p.percent}`;
    }, { message: why, timeout: 90_000, intervals: [2_000, 5_000] })
    .toBe(`${route} ${percent}`);
}

/**
 * Wait until node 4 shows the expected state in the learner's path view.
 *
 * @param page The page.
 * @param variant The variant.
 * @param state Expected data-status.
 * @param why What it means.
 */
async function expectT04(page: Page, variant: string, state: string, why: string): Promise<void> {
  await expect
    .poll(async () => {
      runCompletion();
      return nodeStatus(page, fixture(`ADELE_FIXTURE_A_${variant}_HOST`), `E2E A ${variant.toLowerCase()}`, 'dndnode_4');
    }, { message: why, timeout: 90_000, intervals: [2_000, 5_000] })
    .toBe(state);
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-A — alternative routes', () => {
  for (const [variant, alternatives] of [['T02', ['T02']], ['T03', ['T03']], ['BOTH', ['T02', 'T03']]] as const) {
    test(`${variant.toLowerCase()}: T04 opens, and the route completes without the other alternative`, { tag: ['@story:A', '@story:L2'] }, async ({ page }) => {
      await loginAs(page, fixture(`ADELE_FIXTURE_A_${variant}_LEARNER`), fixturePassword());

      await test.step(`the learner completes T01 and ${alternatives.join(' and ')}`, async () => {
        for (const course of ['T01', ...alternatives]) {
          await completeCourse(page, fixtureCourse(course));
        }
      });

      await test.step('T04 is accessible and open; progress 2 of 3', async () => {
        await expectT04(page, variant, 'accessible', 'one alternative is enough for T04');
        await expectCourseOpenAfterTasks(page, fixtureCourse('T04'), 'one alternative is enough for T04');
        await expectProgress(page, variant, '2/3', '66.67', 'two of three nodes on the best route');
      });

      await test.step('after T04: the route is complete, 100 %', async () => {
        await completeCourse(page, fixtureCourse('T04'));
        await expectProgress(
          page,
          variant,
          '3/3',
          '100',
          'the alternative not taken must not count as required'
        );
      });

      if (alternatives.length === 1) {
        await test.step('the alternative not taken stays what it was: not completed', async () => {
          const other = alternatives[0] === 'T02' ? 'dndnode_3' : 'dndnode_2';
          expect(await nodeStatus(page, fixture(`ADELE_FIXTURE_A_${variant}_HOST`), `E2E A ${variant.toLowerCase()}`, other),
            'nothing was done there, and the route does not pretend otherwise').not.toBe('completed');
        });
      }

      await test.step('a new session: the route chosen is still the one counted', async () => {
        await loginAs(page, fixture(`ADELE_FIXTURE_A_${variant}_LEARNER`), fixturePassword());
        await expectProgress(page, variant, '3/3', '100', 'recomputed for a new session, the same route');
        await expectCourseOpenAfterTasks(page, fixtureCourse('T04'), 'T04 stays open');
      });
    });
  }

  test('none: without an alternative T04 stays locked; progress 1 of 3', { tag: ['@story:A', '@story:L2'] }, async ({ page }) => {
    await loginAs(page, fixture('ADELE_FIXTURE_A_NONE_LEARNER'), fixturePassword());
    await completeCourse(page, fixtureCourse('T01'));
    await expectT04(page, 'NONE', 'not_accessible', 'neither alternative is completed');
    await expectCourseClosed(page, fixtureCourse('T04'), 'neither alternative is completed');
    await expectProgress(page, 'NONE', '1/3', '33.33', 'one of three nodes on the best route');
    const feedback = (await nodeTexts(page, fixture('ADELE_FIXTURE_A_NONE_HOST'), 'E2E A none', 'dndnode_4')).feedback;
    expect(feedback, 'T04 names the alternatives that would open it').toContain('Testkurs 02');
    expect(feedback).toContain('Testkurs 03');

    // And in a new session nothing has changed by itself.
    await loginAs(page, fixture('ADELE_FIXTURE_A_NONE_LEARNER'), fixturePassword());
    await expectT04(page, 'NONE', 'not_accessible', 'still neither alternative');
    await expectCourseClosed(page, fixtureCourse('T04'), 'still neither alternative');
  });
});
