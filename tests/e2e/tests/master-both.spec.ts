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
 * master-both - chain O4 of the E2E plan (section 12): both master
 * checkboxes on the same node, all four combinations.
 *
 * Fixture "E2E Master beide": linear T01 -> T02 -> T03, node 2 requires
 * node 1, the learner completes nothing. On node 2, in this order:
 *
 *   access  completion  node 1/2/3                     route
 *     0        0        open / locked / locked         0/3   normal logic
 *     0        1        open / COMPLETED / open        1/3   see below
 *     1        1        open / completed / open        1/3   both consistent
 *     1        0        open / accessible / locked     0/3   access, no completion
 *     0        0        open / locked / locked         0/3   back to normal
 *
 * The row "0 1" is what the plan leaves to the implementation. Measured
 * first, then pinned: a master completion on a LOCKED node completes it and
 * releases its successor, without any master access. Decided on 2026-10-07:
 * that is intended - a master completion always works, whatever the
 * prerequisites say.
 *
 * Checked in every combination: node states, route progress and the
 * percentage the progress bar reports (aria-valuenow) as the learner sees
 * them, and real access to T02 and T03.
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
  reseedFixtures,
  setMasterCheckbox,
} from '../support/fixtures';

const ACTIVITY = 'E2E O4 master access';
const LASTNAME = 'Master both';

/**
 * As a teacher: set one master checkbox on node 2.
 *
 * @param page The page.
 * @param box Which checkbox.
 * @param on Ticked or not.
 */
async function setOnNode2(page: Page, box: 'Master access checkbox' | 'Master completion checkbox', on: boolean): Promise<void> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await setMasterCheckbox(page, fixture('ADELE_FIXTURE_O4_HOST'), ACTIVITY, LASTNAME, ['dndnode_2'], box, on);
}

/**
 * The learner's view: three node states and the route progress.
 *
 * @param page The page, logged in as the learner.
 */
async function learnerView(page: Page): Promise<string> {
  drainTaskQueue();
  const host = fixture('ADELE_FIXTURE_O4_HOST');
  const states: string[] = [];
  for (const nodeid of ['dndnode_1', 'dndnode_2', 'dndnode_3']) {
    states.push(await nodeStatus(page, host, ACTIVITY, nodeid));
  }
  const row = page.locator('tr').filter({ hasText: LASTNAME }).first();
  const route = ((await row.locator('td').nth(3).innerText()) || '').trim();
  const percent = (await row.getByRole('progressbar').getAttribute('aria-valuenow')) || '';
  return `${states.join(' ')} | ${route} | ${percent}`;
}

/**
 * Expect a combination: the learner's view, then real access to T02 and T03.
 *
 * @param page The page.
 * @param expected "<node1> <node2> <node3> | <route> | <percent the bar reports>".
 * @param t02open Whether T02 must be open.
 * @param t03open Whether T03 must be open.
 * @param why What the combination means.
 */
async function expectCombination(page: Page, expected: string, t02open: boolean, t03open: boolean, why: string): Promise<void> {
  await loginAs(page, fixture('ADELE_FIXTURE_O4_LEARNER'), fixturePassword());
  await expect.poll(() => learnerView(page), { message: why, timeout: 90_000, intervals: [2_000, 5_000] }).toBe(expected);
  for (const [course, open] of [['T02', t02open], ['T03', t03open]] as const) {
    if (open) {
      await expectCourseOpenAfterTasks(page, fixtureCourse(course), `${why}: ${course} must be open`);
    } else {
      await expectCourseClosedAfterTasks(page, fixtureCourse(course), `${why}: ${course} must be closed`);
    }
  }
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-O4 — both master checkboxes, all four combinations', () => {
  test('0/0 -> 0/1 -> 1/1 -> 1/0 -> 0/0', { tag: ['@story:O4'] }, async ({ page }) => {
    await test.step('access 0, completion 0: normal logic', async () => {
      await expectCombination(page, 'accessible not_accessible not_accessible | 0/3 | 0', false, false,
        'nothing set');
    });

    await test.step('access 0, completion 1: node 2 completed, node 3 released - master completion needs no access', async () => {
      await setOnNode2(page, 'Master completion checkbox', true);
      await expectCombination(page, 'accessible completed accessible | 1/3 | 33.33', true, true,
        'master completion alone');
    });

    await test.step('access 1, completion 1: both effects, consistent', async () => {
      await setOnNode2(page, 'Master access checkbox', true);
      await expectCombination(page, 'accessible completed accessible | 1/3 | 33.33', true, true,
        'both checkboxes');
    });

    await test.step('access 1, completion 0: access only - no completion, node 3 locked again', async () => {
      await setOnNode2(page, 'Master completion checkbox', false);
      await expectCombination(page, 'accessible accessible not_accessible | 0/3 | 0', true, false,
        'master access alone must not complete');
    });

    await test.step('access 0, completion 0: back to normal logic', async () => {
      await setOnNode2(page, 'Master access checkbox', false);
      await expectCombination(page, 'accessible not_accessible not_accessible | 0/3 | 0', false, false,
        'nothing set any more');
    });
  });
});
