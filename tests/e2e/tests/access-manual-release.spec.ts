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
 * access-manual-release - chain C3 of the E2E plan (section 8).
 *
 *     not released  ->  released  ->  release withdrawn
 *
 * Fixture "E2E Manuelle Freigabe": node 2 (T02) has a single access
 * condition, "manual". A teacher opens the learner's own path from the
 * activity's user list, ticks "Unlock node manually" on that node and saves;
 * later unticks it and saves again. After each step: the node's state in
 * the learner's path view, and real access to the course.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  drainTaskQueue,
  expectCourseClosed,
  expectCourseClosedAfterTasks,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  nodeStatus,
  reseedFixtures,
} from '../support/fixtures';

const ACTIVITY = 'E2E C3 manual';

/**
 * As a teacher: open the learner's path and set the manual release of node 2.
 *
 * @param page The page.
 * @param released Whether node 2 is to be released.
 */
async function setRelease(page: Page, released: boolean): Promise<void> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await page.goto(`/course/view.php?id=${fixture('ADELE_FIXTURE_C3_HOST')}`);
  await page.getByRole('main').getByRole('link', { name: ACTIVITY }).click();
  // The user list links each learner by id; the row carries the name.
  const row = page.locator('tr').filter({ hasText: fixture('ADELE_FIXTURE_C3_LEARNER_NAME').split(' ').pop() || '' });
  await row.first().getByRole('link').first().click();
  const node = page.locator('[data-testid="learningpath-node-dndnode_2"]');
  await expect(node).toBeVisible({ timeout: 60_000 });
  const box = node.getByLabel('Unlock node manually');
  await expect(box, 'the teacher must be offered the manual release on node 2').toBeVisible();
  if ((await box.isChecked()) !== released) {
    await box.click();
  }
  await expect(box).toBeChecked({ checked: released });
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByText('Learning path saved')).toBeVisible({ timeout: 30_000 });
  drainTaskQueue();
}

/**
 * The state of node 2 as the learner sees it, after the system had a chance
 * to process the change.
 *
 * @param page The page.
 * @param state The expected data-status.
 * @param why What it means.
 */
async function expectLearnerSees(page: Page, state: string, why: string): Promise<void> {
  await loginAs(page, fixture('ADELE_FIXTURE_C3_LEARNER'), fixturePassword());
  await expect
    .poll(async () => {
      drainTaskQueue();
      return nodeStatus(page, fixture('ADELE_FIXTURE_C3_HOST'), ACTIVITY, 'dndnode_2');
    }, { message: why, timeout: 90_000, intervals: [2_000, 5_000] })
    .toBe(state);
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-C3 — a teacher releases a node manually, and withdraws it', () => {
  test('not released -> released -> release withdrawn', { tag: ['@story:C3', '@withdrawal'] }, async ({ page }) => {
    await test.step('not released: T02 locked and closed', async () => {
      await expectLearnerSees(page, 'not_accessible', 'nobody has released node 2 yet');
      await expectCourseClosed(page, fixtureCourse('T02'), 'nobody has released node 2 yet');
    });

    await test.step('the teacher releases node 2', async () => {
      await setRelease(page, true);
    });

    await test.step('released: T02 accessible and open', async () => {
      await expectLearnerSees(page, 'accessible', 'the teacher has released node 2');
      await expectCourseOpenAfterTasks(page, fixtureCourse('T02'), 'the release must open the course');
    });

    await test.step('the teacher withdraws the release', async () => {
      await setRelease(page, false);
    });

    await test.step('withdrawn: T02 locked and closed again', async () => {
      await expectLearnerSees(page, 'not_accessible', 'the release has been withdrawn');
      await expectCourseClosedAfterTasks(page, fixtureCourse('T02'), 'without the release the course must close');
    });
  });
});
