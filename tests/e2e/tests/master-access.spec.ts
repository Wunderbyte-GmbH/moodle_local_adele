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
 * master-access - chain O2 of the E2E plan (section 12): the "Master access
 * checkbox". Central assertion: master ACCESS never means COMPLETION.
 *
 * Fixture "E2E Master-Zugang": linear T01 -> T02 -> T03, node 2 requires
 * node 1. Variants 1-4 of the plan, in an order that shows each effect:
 *   1  condition false, master off   node 2 locked
 *   2  condition false, master on    node 2 open - but NOT completed, 0/3
 *      master off again              node 2 locked again, enrolment suspended
 *   3  master on, condition true     node 2 open; node 1 completed, node 2 not
 *   4  master off, condition true    node 2 stays open - on its own condition
 *
 * Fixture "E2E Master-Zugang Zeit", variant 5: node 1 is open only in the
 * 2030 window. The master access opens it now, without any clock jump;
 * switching it off closes it again.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  completeCourse,
  drainTaskQueue,
  enrolmentStatus,
  expectCourseClosedAfterTasks,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  nodeStatus,
  runCompletion,
  setMasterCheckbox,
} from '../support/fixtures';

/**
 * As a teacher: switch the master access of one node.
 *
 * @param page The page.
 * @param key O2 or O2T.
 * @param lastname The learner's last name.
 * @param nodeid The node.
 * @param on Whether the master access is to be ticked.
 */
async function setMasterAccess(page: Page, key: string, lastname: string, nodeid: string, on: boolean): Promise<void> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await setMasterCheckbox(page, fixture(`ADELE_FIXTURE_${key}_HOST`), `E2E ${key} master access`, lastname,
    [nodeid], 'Master access checkbox', on);
}

/**
 * The learner's view: the three node states, route and percentage.
 *
 * @param page The page, logged in as the learner.
 * @param key O2 or O2T.
 * @param lastname The learner's last name.
 */
async function learnerView(page: Page, key: string, lastname: string): Promise<string> {
  drainTaskQueue();
  const host = fixture(`ADELE_FIXTURE_${key}_HOST`);
  const activity = `E2E ${key} master access`;
  const states: string[] = [];
  for (const nodeid of ['dndnode_1', 'dndnode_2', 'dndnode_3']) {
    states.push(await nodeStatus(page, host, activity, nodeid));
  }
  const row = page.locator('tr').filter({ hasText: lastname }).first();
  const route = ((await row.locator('td').nth(3).innerText()) || '').trim();
  const percent = (await row.getByRole('progressbar').getAttribute('aria-valuenow')) || '';
  return `${states.join(' ')} | ${route} | ${percent}`;
}

/**
 * Wait until the learner sees the expected state.
 *
 * @param page The page.
 * @param key O2 or O2T.
 * @param lastname The learner's last name.
 * @param expected "<node1> <node2> <node3> | <route> | <percent>".
 * @param why What it means.
 */
async function expectLearnerSees(page: Page, key: string, lastname: string, expected: string, why: string): Promise<void> {
  await loginAs(page, fixture(`ADELE_FIXTURE_${key}_LEARNER`), fixturePassword());
  await expect.poll(() => learnerView(page, key, lastname), { message: why, timeout: 90_000, intervals: [2_000, 5_000] })
    .toBe(expected);
}

test.describe('ADELE-E2E-O2 — master access opens a node, it never completes it', () => {
  test('variants 1-4: condition false/true, master off/on', async ({ page }) => {
    const name = 'Master access';
    const t02 = fixtureCourse('T02');

    await test.step('1  condition false, master off: node 2 locked', async () => {
      await expectLearnerSees(page, 'O2', name, 'accessible not_accessible not_accessible | 0/3 | 0',
        'node 1 is not completed and nobody has opened node 2');
    });

    await test.step('2  condition false, master on: node 2 open, but not completed', async () => {
      await setMasterAccess(page, 'O2', name, 'dndnode_2', true);
      await expectLearnerSees(page, 'O2', name, 'accessible accessible not_accessible | 0/3 | 0',
        'master access opens node 2 - and completes nothing');
      await expectCourseOpenAfterTasks(page, t02, 'master access opens T02');
      await loginAs(page, env.adminUser, env.adminPassword);
      expect(await enrolmentStatus(page, t02, name)).toBe('Active');
    });

    await test.step('   master off again: node 2 locked, enrolment suspended', async () => {
      await setMasterAccess(page, 'O2', name, 'dndnode_2', false);
      await expectLearnerSees(page, 'O2', name, 'accessible not_accessible not_accessible | 0/3 | 0',
        'without master access the condition decides again');
      await expectCourseClosedAfterTasks(page, t02, 'master access off closes T02 again');
      await loginAs(page, env.adminUser, env.adminPassword);
      expect(await enrolmentStatus(page, t02, name), 'suspend, not delete').toBe('Suspended');
    });

    await test.step('3  master on, condition becomes true: node 2 open; node 1 completed, node 2 not', async () => {
      await setMasterAccess(page, 'O2', name, 'dndnode_2', true);
      await loginAs(page, fixture('ADELE_FIXTURE_O2_LEARNER'), fixturePassword());
      await completeCourse(page, fixtureCourse('T01'));
      runCompletion();
      await expectLearnerSees(page, 'O2', name, 'completed accessible not_accessible | 1/3 | 33.33',
        'completing T01 completes node 1 - node 2 stays merely accessible');
    });

    await test.step('4  master off, condition true: node 2 stays open on its own condition', async () => {
      await setMasterAccess(page, 'O2', name, 'dndnode_2', false);
      await expectLearnerSees(page, 'O2', name, 'completed accessible not_accessible | 1/3 | 33.33',
        'node 1 is completed, so node 2 is open without master access');
      await expectCourseOpenAfterTasks(page, t02, 'the condition itself opens T02 now');
    });
  });

  test('variant 5: master access overrides a time condition, and only while it is on', async ({ page }) => {
    const name = 'Master access time';
    const t01 = fixtureCourse('T01');

    await test.step('time condition false (window in 2030): node 1 locked', async () => {
      await expectLearnerSees(page, 'O2T', name, 'not_accessible not_accessible not_accessible | 0/3 | 0',
        'the window lies in the future');
    });

    await test.step('master access on: node 1 open now, without any clock jump, not completed', async () => {
      await setMasterAccess(page, 'O2T', name, 'dndnode_1', true);
      await expectLearnerSees(page, 'O2T', name, 'accessible not_accessible not_accessible | 0/3 | 0',
        'master access overrides the time condition');
      await expectCourseOpenAfterTasks(page, t01, 'master access opens T01');
    });

    await test.step('master access off: node 1 locked again', async () => {
      await setMasterAccess(page, 'O2T', name, 'dndnode_1', false);
      await expectLearnerSees(page, 'O2T', name, 'not_accessible not_accessible not_accessible | 0/3 | 0',
        'without master access the window decides again');
      await expectCourseClosedAfterTasks(page, t01, 'master access off closes T01 again');
    });
  });
});
