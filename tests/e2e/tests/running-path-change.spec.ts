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
 * running-path-change - chain M of the E2E plan (section 15): an author
 * changes a learning path that learners are already on.
 *
 *   M1  node 2 had no access condition, its learner is in T02; the author
 *       adds "Manual access" through the criteria editor -> the system
 *       re-evaluates, the learner loses access (enrolment suspended).
 *   M2  the author removes that condition again -> the learner, locked out
 *       a moment ago, gets access back.
 *
 * Fixture "E2E Laufende Änderung" with its own learner and host course. The
 * learner completes T01 first, so the route stands at 1 of 3.
 * Checked in every state, as the plan asks:
 *   - node 2's state in the learner's path view,
 *   - the message on node 2 (its information card: the access criteria),
 *   - the progress on the route - unchanged by both edits, which concern
 *     access, not completion,
 *   - the enrolment in T02 as the participants page shows it: suspended
 *     after M1, active again after M2 (suspend, not delete),
 *   - real access to T02.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  completeCourse,
  drainTaskQueue,
  expectCourseClosedAfterTasks,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  nodeStatus,
  runCompletion,
} from '../support/fixtures';

const PATH = 'E2E Laufende Änderung';
const ACTIVITY = 'E2E M running';
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/**
 * As the author: open node 2's access criteria in the editor.
 *
 * @param page The page.
 */
async function openNode2Criteria(page: Page): Promise<void> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await page.goto('/local/adele/index.php');
  const card = app(page).locator('[data-testid^="learningpath-row-"]')
    .filter({ has: page.locator('h5', { hasText: PATH }) });
  await card.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
  const node2 = app(page).locator('[data-testid="learningpath-node-dndnode_2"]');
  await expect(node2).toBeVisible({ timeout: 30_000 });
  await node2.getByRole('button', { name: /^(Edit access criteria|Zugangskriterien bearbeiten):/ }).click();
  await expect(page.locator('[data-draggable="Manual access"]')).toBeVisible({ timeout: 30_000 });
}

/**
 * Save the criteria canvas, then the path; back on the overview means saved.
 *
 * @param page The page.
 */
async function saveCriteriaAndPath(page: Page): Promise<void> {
  await page.getByRole('button', { name: /^(Save|Speichern)$/ }).click();
  await expect(app(page).locator('[data-testid="learningpath-node-dndnode_2"]')).toBeVisible({ timeout: 30_000 });
  await app(page).locator('[data-testid="learningpath-save"]').click();
  await expect(app(page).locator('[data-testid="learningpath-create"]')).toBeVisible({ timeout: 30_000 });
  drainTaskQueue();
}

/**
 * Node 2 as the learner sees it, after the system processed the change.
 *
 * @param page The page.
 * @param state Expected data-status.
 * @param why What it means.
 */
async function expectLearnerNode2(page: Page, state: string, why: string): Promise<void> {
  await loginAs(page, fixture('ADELE_FIXTURE_M_LEARNER'), fixturePassword());
  await expect
    .poll(async () => {
      drainTaskQueue();
      return nodeStatus(page, fixture('ADELE_FIXTURE_M_HOST'), ACTIVITY, 'dndnode_2');
    }, { message: why, timeout: 90_000, intervals: [2_000, 5_000] })
    .toBe(state);
}

/**
 * What the learner sees of node 2: its state, the information card's text
 * and the route progress in the activity's list - one string, polled.
 *
 * @param page The page, logged in as the learner.
 */
async function learnerView(page: Page): Promise<string> {
  drainTaskQueue();
  const status = await nodeStatus(page, fixture('ADELE_FIXTURE_M_HOST'), ACTIVITY, 'dndnode_2');
  const wrapper = page.locator('.vue-flow__node[data-id="dndnode_2"]');
  await wrapper.locator('.information').first().click();
  const info = ((await wrapper.locator('.additional-card').innerText()) || '').replace(/\s+/g, ' ').trim();
  const row = page.locator('tr').filter({ hasText: 'Running change' }).first();
  const route = ((await row.locator('td').nth(3).innerText()) || '').trim();
  return `${status} | ${route} | ${info}`;
}

/**
 * The learner's enrolment in T02 as an admin sees it on the participants
 * page ("Active", "Suspended", or not listed).
 *
 * @param page The page.
 */
async function enrolmentInT02(page: Page): Promise<string> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await page.goto(`/user/index.php?id=${fixtureCourse('T02')}&perpage=5000`);
  const row = page.locator('table#participants tbody tr').filter({ hasText: 'Running change' });
  if (!(await row.count())) {
    return 'not listed';
  }
  const text = (await row.first().innerText()).replace(/\s+/g, ' ');
  return /Suspended/.test(text) ? 'Suspended' : /Active/.test(text) ? 'Active' : text;
}

test.describe('ADELE-E2E-M — changing a path learners are already on', () => {
  test.use({ viewport: { width: 1920, height: 1400 } });

  test('M1 adding a condition takes access away, M2 removing it gives it back', async ({ page }) => {
    page.on('dialog', (dialog) => dialog.accept());

    await test.step('before: node 2 freely accessible, the learner in T02, route 1 of 3', async () => {
      await loginAs(page, fixture('ADELE_FIXTURE_M_LEARNER'), fixturePassword());
      await completeCourse(page, fixtureCourse('T01'));
      runCompletion();
      await expectLearnerNode2(page, 'accessible', 'node 2 has no access condition yet');
      await expect.poll(() => learnerView(page), { timeout: 90_000 })
        .toMatch(/^accessible \| 1\/3 \| .*Freely accessible/);
      await expectCourseOpenAfterTasks(page, fixtureCourse('T02'), 'node 2 has no access condition yet');
      expect(await enrolmentInT02(page)).toBe('Active');
    });

    await test.step('M1: the author adds "Manual access" to node 2 and saves', async () => {
      await openNode2Criteria(page);
      // An empty criteria canvas takes the first condition wherever it is
      // dropped (RestrictionFlow.onDrop) - the canvas exists before the drag.
      await page.locator('[data-draggable="Manual access"]').dragTo(page.locator('.vue-flow__pane').first());
      await expect(page.locator('.vue-flow__node').filter({ hasText: /Manual access|Manuelle/i }).first(),
        'the dropped condition must appear on the criteria canvas').toBeVisible({ timeout: 30_000 });
      await saveCriteriaAndPath(page);
    });

    await test.step('M1: the learner loses node 2 and the course; progress unchanged; enrolment suspended', async () => {
      await expectLearnerNode2(page, 'not_accessible', 'nobody has released node 2 manually');
      const view = await learnerView(page);
      expect(view, 'route progress is about completion, not access').toMatch(/^not_accessible \| 1\/3 \| /);
      expect(view, 'the message must name the new condition').toMatch(/manual/i);
      expect(view, 'the message must no longer claim free access').not.toMatch(/Freely accessible/);
      await expectCourseClosedAfterTasks(page, fixtureCourse('T02'), 'the added condition must take access away');
      expect(await enrolmentInT02(page), 'suspend, not delete').toBe('Suspended');
    });

    await test.step('M2: the author removes the condition again and saves', async () => {
      await openNode2Criteria(page);
      const condition = page.locator('.vue-flow__node').filter({ hasText: /Manual access|Manuelle/i });
      await expect(condition.first()).toBeVisible({ timeout: 30_000 });
      await condition.first().locator('button:has(.fa-trash)').click();
      await expect(condition).toHaveCount(0);
      await saveCriteriaAndPath(page);
    });

    await test.step('M2: the learner gets node 2 and the course back; enrolment active again', async () => {
      await expectLearnerNode2(page, 'accessible', 'the condition has been removed');
      await expect.poll(() => learnerView(page), { timeout: 60_000 })
        .toMatch(/^accessible \| 1\/3 \| .*Freely accessible/);
      await expectCourseOpenAfterTasks(page, fixtureCourse('T02'), 'without the condition the course must open again');
      expect(await enrolmentInT02(page), 'the suspended enrolment must be reactivated').toBe('Active');
    });
  });
});
