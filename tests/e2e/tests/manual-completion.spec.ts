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
 * manual-completion - chains L3 and O1 of the E2E plan (sections 11, 12):
 * a teacher completes a node manually and takes the decision back.
 *
 *     regular state  ->  completed manually  ->  completion withdrawn
 *
 * Fixture "E2E Manueller Abschluss": node 1 (T01) completes ONLY through
 * the teacher's tick "Finish node manually" in the learner's path view;
 * node 2 (T02) follows node 1. Checked in every state:
 *   - node 1's state and the message on its information card (L3, O1),
 *   - the percentage and total progress on the route (L3, O1),
 *   - the follow-up node: node 2's state and real access to T02 (L3, O1),
 *   - the enrolment in T02 on its participants page (O1).
 *
 * Not asserted here: the wording of node 1's information card and feedback -
 * that is chain F (feedback-texts.spec.ts).
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  drainTaskQueue,
  expectCourseClosedAfterTasks,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  nodeStatus,
} from '../support/fixtures';

const ACTIVITY = 'E2E L manual';
const LASTNAME = 'Manual completion';

/**
 * As a teacher: open the learner's path, set "Finish node manually" on node
 * 1 and save.
 *
 * @param page The page.
 * @param completed Whether node 1 is to be completed.
 */
async function setManualCompletion(page: Page, completed: boolean): Promise<void> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await page.goto(`/course/view.php?id=${fixture('ADELE_FIXTURE_L3_HOST')}`);
  await page.getByRole('main').getByRole('link', { name: ACTIVITY }).click();
  await page.locator('tr').filter({ hasText: LASTNAME }).first().getByRole('link').first().click();
  const node = page.locator('[data-testid="learningpath-node-dndnode_1"]');
  await expect(node).toBeVisible({ timeout: 60_000 });
  const box = node.getByLabel('Finish node manually');
  await expect(box, 'the teacher must be offered the manual completion on node 1').toBeVisible();
  if ((await box.isChecked()) !== completed) {
    await box.click();
  }
  await expect(box).toBeChecked({ checked: completed });
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByText('Learning path saved')).toBeVisible({ timeout: 30_000 });
  drainTaskQueue();
}

/**
 * What the learner sees: node 1's state and message, node 2's state, the
 * route progress. One string, to be polled.
 *
 * @param page The page, logged in as the learner.
 */
async function learnerView(page: Page): Promise<string> {
  drainTaskQueue();
  const host = fixture('ADELE_FIXTURE_L3_HOST');
  const node2 = await nodeStatus(page, host, ACTIVITY, 'dndnode_2');
  const node1 = await nodeStatus(page, host, ACTIVITY, 'dndnode_1');
  const wrapper = page.locator('.vue-flow__node[data-id="dndnode_1"]');
  // The card opens only on a click on the icon (NodeInformation.toggleCard;
  // focus merely raises its z-index - not reachable by keyboard, reported
  // separately). The canvas may clip the icon, so the click event is
  // dispatched to it directly: same Vue handler, no visible spot needed.
  await wrapper.locator('.information').first().dispatchEvent('click');
  await expect(wrapper.locator('.additional-card'), 'node 1 must offer its information card').toBeVisible({ timeout: 10_000 });
  const row = page.locator('tr').filter({ hasText: LASTNAME }).first();
  const route = ((await row.locator('td').nth(3).innerText()) || '').trim();
  const percent = (await row.getByRole('progressbar').getAttribute('aria-valuenow')) || '';
  return `${node1} | ${node2} | ${route} | ${percent}`;
}

/**
 * The learner's enrolment in T02, as the participants page shows it.
 *
 * @param page The page.
 */
async function enrolmentInT02(page: Page): Promise<string> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await page.goto(`/user/index.php?id=${fixtureCourse('T02')}&perpage=5000`);
  const row = page.locator('table#participants tbody tr').filter({ hasText: LASTNAME });
  if (!(await row.count())) {
    return 'not listed';
  }
  const text = (await row.first().innerText()).replace(/\s+/g, ' ');
  return /Suspended/.test(text) ? 'Suspended' : /Active/.test(text) ? 'Active' : text;
}

test.describe('ADELE-E2E-L3/O1 — manual completion, set and withdrawn', () => {

  test('regular -> completed manually -> withdrawn', async ({ page }) => {
    const learner = fixture('ADELE_FIXTURE_L3_LEARNER');

    await test.step('regular: node 1 open, node 2 locked, 0 of 3', async () => {
      await loginAs(page, learner, fixturePassword());
      await expect.poll(() => learnerView(page), { timeout: 60_000 })
        .toBe('accessible | not_accessible | 0/3 | 0');
      expect(await enrolmentInT02(page)).toBe('not listed');
    });

    await test.step('the teacher completes node 1 manually', async () => {
      await setManualCompletion(page, true);
    });

    await test.step('completed: node 1 completed, node 2 and T02 open, 1 of 3, enrolment active', async () => {
      await loginAs(page, learner, fixturePassword());
      await expect.poll(() => learnerView(page), { timeout: 90_000, intervals: [2_000, 5_000] })
        .toBe('completed | accessible | 1/3 | 33.33');
      await expectCourseOpenAfterTasks(page, fixtureCourse('T02'), 'the manual completion opens node 2');
      expect(await enrolmentInT02(page)).toBe('Active');
    });

    await test.step('the teacher withdraws the manual completion', async () => {
      await setManualCompletion(page, false);
    });

    await test.step('withdrawn: back to 0 of 3, node 2 locked, T02 closed, enrolment suspended', async () => {
      await loginAs(page, learner, fixturePassword());
      await expect.poll(() => learnerView(page), { timeout: 90_000, intervals: [2_000, 5_000] })
        .toBe('accessible | not_accessible | 0/3 | 0');
      await expectCourseClosedAfterTasks(page, fixtureCourse('T02'), 'without the completion node 2 must close');
      expect(await enrolmentInT02(page), 'suspend, not delete').toBe('Suspended');
    });
  });
});
