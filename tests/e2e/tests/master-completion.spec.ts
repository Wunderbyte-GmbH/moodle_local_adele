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
 * master-completion - chains L4 and O3 of the E2E plan (sections 11, 12):
 * the "Master completion checkbox", set and removed by a teacher.
 *
 * Fixture "E2E Master-Abschluss": a plain linear path T01 -> T02 -> T03,
 * each node completing on its course. The learner completes nothing, so the
 * master checkbox is the only way a node becomes completed.
 *
 *   regular                     node 1 open, 0/3
 *   master on node 1            node 1 completed, node 2 opens, 1/3
 *   master on all three nodes   whole path completed, 3/3 = 100 %
 *   master removed everywhere   back to 0/3, node 2 closed again
 *
 * Checked in every state: node states in the learner's path view (node
 * completion, follow-up releases), route progress and percentage (L4, O3),
 * real access to T02 and its enrolment (suspend, not delete).
 *
 * The completion of the embedding mod_adele activity ("learning path
 * finished") is chain Z's subject: final-completion.spec.ts, Z4.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  drainTaskQueue,
  enrolmentStatus,
  expectCourseClosedAfterTasks,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  nodeStatus,
  setMasterCheckbox,
} from '../support/fixtures';

const ACTIVITY = 'E2E L master';
const LASTNAME = 'Master completion';

/**
 * As a teacher: set the master completion of the given nodes and save.
 *
 * @param page The page.
 * @param nodes Node ids.
 * @param completed Whether their master completion is to be set.
 */
async function setMasterCompletion(page: Page, nodes: string[], completed: boolean): Promise<void> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await setMasterCheckbox(page, fixture('ADELE_FIXTURE_L4_HOST'), ACTIVITY, LASTNAME, nodes,
    'Master completion checkbox', completed);
}

/**
 * The learner's view: the three node states, route and percentage.
 *
 * @param page The page, logged in as the learner.
 */
async function learnerView(page: Page): Promise<string> {
  drainTaskQueue();
  const host = fixture('ADELE_FIXTURE_L4_HOST');
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
 * Wait until the learner sees the expected state.
 *
 * @param page The page.
 * @param expected "<node1> <node2> <node3> | <route> | <percent>".
 * @param why What it means.
 */
async function expectLearnerSees(page: Page, expected: string, why: string): Promise<void> {
  await loginAs(page, fixture('ADELE_FIXTURE_L4_LEARNER'), fixturePassword());
  await expect.poll(() => learnerView(page), { message: why, timeout: 90_000, intervals: [2_000, 5_000] })
    .toBe(expected);
}

/**
 * The learner's enrolment in T02.
 *
 * @param page The page.
 */
async function enrolmentInT02(page: Page): Promise<string> {
  await loginAs(page, env.adminUser, env.adminPassword);
  return enrolmentStatus(page, fixtureCourse('T02'), LASTNAME);
}

test.describe('ADELE-E2E-L4/O3 — master completion, set and removed', () => {
  test('regular -> node 1 -> whole path -> removed', async ({ page }) => {
    await test.step('regular: node 1 open, nothing completed', async () => {
      await expectLearnerSees(page, 'accessible not_accessible not_accessible | 0/3 | 0', 'nothing is completed');
      expect(await enrolmentInT02(page)).toBe('not listed');
    });

    await test.step('master completion on node 1: completed, node 2 opens, 1 of 3', async () => {
      await setMasterCompletion(page, ['dndnode_1'], true);
      await expectLearnerSees(page, 'completed accessible not_accessible | 1/3 | 33.33',
        'the master completion completes node 1 and releases node 2');
      await expectCourseOpenAfterTasks(page, fixtureCourse('T02'), 'node 2 has been released');
      expect(await enrolmentInT02(page)).toBe('Active');
    });

    await test.step('master completion on all three nodes: the whole path is completed', async () => {
      await setMasterCompletion(page, ['dndnode_2', 'dndnode_3'], true);
      await expectLearnerSees(page, 'completed completed completed | 3/3 | 100', 'every node is completed');
    });

    await test.step('master completion removed everywhere: back to the start', async () => {
      await setMasterCompletion(page, ['dndnode_1', 'dndnode_2', 'dndnode_3'], false);
      await expectLearnerSees(page, 'accessible not_accessible not_accessible | 0/3 | 0',
        'without the master completion nothing is completed');
      await expectCourseClosedAfterTasks(page, fixtureCourse('T02'), 'node 2 must close again');
      expect(await enrolmentInT02(page), 'suspend, not delete').toBe('Suspended');
    });
  });
});
