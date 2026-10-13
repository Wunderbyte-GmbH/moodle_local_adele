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
 * diagnosis-catquiz - chain D of the E2E plan (section 13): a CAT result
 * decides the route, at the threshold x and either side of it.
 *
 * Fixture "E2E Diagnose": node 1 (T01) completes when the person ability on
 * the fixture scale reaches x = 2.5 ("ability >= threshold"); node 2 (T02)
 * follows node 1. Three learners get one finished CAT attempt each, with
 * ability x - 0.01, x and x + 0.01, as a signal (support/catquiz_signal.php:
 * real adaptivequiz attempt, catquiz result row, completion event).
 *
 * Checked per learner: node 1 in the path view, real access to T02, and the
 * progress on the route (0/3 or 1/3 = 33.33 %). Feedback texts: chain F.
 *
 * A CONTRACT test, labelled as such in its title (E2E issue, section 2, D):
 * the CAT result is written as a signal - a real adaptivequiz attempt row,
 * the catquiz result and the completion event - not produced by a learner
 * answering questions in the browser. What it proves is ADELE's side: from
 * the moment catquiz reports an ability, the route, the access and the
 * progress follow the threshold. A browser journey through a real CAT test
 * is not part of this file.
 *
 * Needs the catquiz ecosystem (Moodle 4.5 line); skipped loudly without it -
 * and a skip makes the story coverage check fail, so a run without catquiz
 * is never a complete run.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { loginAs } from '../support/env';
import {
  catquizSignal,
  drainTaskQueue,
  expectCourseClosed,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  hasCatquiz,
  nodeStatus,
  reseedFixtures,
} from '../support/fixtures';

/**
 * Node 1's state and the learner's route progress, as the learner sees them.
 *
 * @param page The page, logged in as the learner.
 * @param key BELOW, AT or ABOVE.
 */
async function learnerView(page: Page, key: string): Promise<string> {
  drainTaskQueue();
  const host = fixture(`ADELE_FIXTURE_D_${key}_HOST`);
  const activity = `E2E D ${key.toLowerCase()}`;
  const status = await nodeStatus(page, host, activity, 'dndnode_1');
  const lastname = fixture(`ADELE_FIXTURE_D_${key}_LEARNER_NAME`).split(' ').slice(1).join(' ');
  const row = page.locator('tr').filter({ hasText: lastname }).first();
  await expect(row).toBeVisible({ timeout: 60_000 });
  const route = ((await row.locator('td').nth(3).innerText()) || '').trim();
  const percent = (await row.getByRole('progressbar').getAttribute('aria-valuenow')) || '';
  return `${status} ${route} ${percent}`;
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-D — a CAT result decides the route at the threshold [contract test: injected CAT signal, no CAT journey]', () => {
  test.skip(!hasCatquiz(), 'needs the catquiz ecosystem (seed creates chain D only where it is installed)');

  const x = (): number => Number(fixture('ADELE_FIXTURE_D_THRESHOLD'));
  const cases: Array<[string, number, boolean]> = [
    ['BELOW', -0.01, false],
    ['AT', 0, true],
    ['ABOVE', 0.01, true],
  ];

  for (const [key, delta, passes] of cases) {
    test(`ability x${delta === 0 ? '' : delta > 0 ? ' + ε' : ' - ε'}: ${passes ? 'next route opens' : 'stays on the diagnosis node'}`, { tag: ['@story:D'] }, async ({ page }) => {
      const learner = fixture(`ADELE_FIXTURE_D_${key}_LEARNER`);
      await loginAs(page, learner, fixturePassword());

      await test.step('before the test: node 1 open, T02 closed, no progress', async () => {
        await expect.poll(() => learnerView(page, key), { timeout: 60_000 }).toMatch(/^accessible 0\/3/);
        await expectCourseClosed(page, fixtureCourse('T02'), 'nothing has been diagnosed yet');
      });

      await test.step(`the CAT result arrives: ability ${(x() + delta).toFixed(2)} against x = ${x()}`, async () => {
        catquizSignal(learner, Number((x() + delta).toFixed(2)));
      });

      if (passes) {
        await test.step('node 1 completed, T02 open, route 1 of 3', async () => {
          await expect.poll(() => learnerView(page, key), { timeout: 90_000, message: 'the threshold is met' })
            .toBe('completed 1/3 33.33');
          await expectCourseOpenAfterTasks(page, fixtureCourse('T02'), 'the diagnosis opens the next node');
        });
      } else {
        await test.step('node 1 stays open, T02 closed, no progress', async () => {
          await expect.poll(() => learnerView(page, key), { timeout: 30_000, message: 'just below the threshold' })
            .toMatch(/^accessible 0\/3/);
          await expectCourseClosed(page, fixtureCourse('T02'), 'just below the threshold');
        });
      }
    });
  }
});
