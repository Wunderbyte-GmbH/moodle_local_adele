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
 * access-predecessors - chain C1 of the E2E plan (section 8): access by
 * completed predecessors, in three variants.
 *
 *   1 of 1:  T01 -> T02          T02 opens once T01 is completed
 *   1 of 2:  T01, T02 -> T03     either predecessor opens T03
 *   2 of 2:  T01, T02 -> T03     one is not enough, both open T03
 *
 * Completion is real: the learner opens the course and marks its
 * completion activity as done; Moodle aggregates the course completion,
 * ADELE re-evaluates the path, enrol_adele enrols. Each state is checked
 * twice - as the learner's path view shows the node (data-status), and as
 * real access to the course.
 *
 * Each variant has a path, a learner and a host course of its own (seed),
 * so completing a course in one variant cannot open anything in another.
 *
 * The fourth variant of the plan, "predecessor + time", is T4 and lives
 * with the time chain.
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
  runCompletion,
} from '../support/fixtures';

/**
 * Wait until the learner's path view shows the node in the given state,
 * letting the system process completions in between.
 *
 * @param page The page, logged in as the learner.
 * @param variant SINGLE, ANY or ALL.
 * @param nodeid The node.
 * @param state The expected data-status.
 * @param why What the state means, for the failure message.
 */
async function expectNodeState(page: Page, variant: string, nodeid: string, state: string, why: string): Promise<void> {
  const host = fixture(`ADELE_FIXTURE_C1_${variant}_HOST`);
  await expect
    .poll(async () => {
      runCompletion();
      return nodeStatus(page, host, `E2E C1 ${variant.toLowerCase()}`, nodeid);
    }, { message: why, timeout: 90_000, intervals: [2_000, 5_000] })
    .toBe(state);
}

test.describe('ADELE-E2E-C1 — access by completed predecessors', () => {
  test('1 of 1: the successor opens once its predecessor is completed', async ({ page }) => {
    await loginAs(page, fixture('ADELE_FIXTURE_C1_SINGLE_LEARNER'), fixturePassword());

    await test.step('before: T02 is shown locked and the course is closed', async () => {
      await expectNodeState(page, 'SINGLE', 'dndnode_2', 'not_accessible', 'T02 must wait for T01');
      await expectCourseClosed(page, fixtureCourse('T02'), 'T01 is not completed yet');
    });

    await test.step('the learner completes T01', async () => {
      await completeCourse(page, fixtureCourse('T01'));
    });

    await test.step('after: T01 completed, T02 accessible and open', async () => {
      await expectNodeState(page, 'SINGLE', 'dndnode_1', 'completed', 'T01 has been completed');
      await expectNodeState(page, 'SINGLE', 'dndnode_2', 'accessible', 'its only predecessor is completed');
      await expectCourseOpenAfterTasks(page, fixtureCourse('T02'), 'T02 must open after T01');
    });
  });

  test('1 of 2: either predecessor opens the successor', async ({ page }) => {
    await loginAs(page, fixture('ADELE_FIXTURE_C1_ANY_LEARNER'), fixturePassword());

    await test.step('before: T03 is locked', async () => {
      await expectNodeState(page, 'ANY', 'dndnode_3', 'not_accessible', 'no predecessor is completed yet');
      await expectCourseClosed(page, fixtureCourse('T03'), 'no predecessor is completed yet');
    });

    await test.step('the learner completes T02 only', async () => {
      await completeCourse(page, fixtureCourse('T02'));
    });

    await test.step('after: T03 accessible and open', async () => {
      await expectNodeState(page, 'ANY', 'dndnode_3', 'accessible', 'one of two predecessors is enough');
      await expectCourseOpenAfterTasks(page, fixtureCourse('T03'), 'one of two predecessors is enough');
    });
  });

  test('2 of 2: one predecessor is not enough, both are', async ({ page }) => {
    await loginAs(page, fixture('ADELE_FIXTURE_C1_ALL_LEARNER'), fixturePassword());

    await test.step('the learner completes T01', async () => {
      await completeCourse(page, fixtureCourse('T01'));
    });

    await test.step('one of two: T03 stays locked', async () => {
      await expectNodeState(page, 'ALL', 'dndnode_1', 'completed', 'T01 has been completed');
      await expectNodeState(page, 'ALL', 'dndnode_3', 'not_accessible', 'both predecessors are required');
      await expectCourseClosed(page, fixtureCourse('T03'), 'both predecessors are required');
    });

    await test.step('the learner completes T02 as well', async () => {
      await completeCourse(page, fixtureCourse('T02'));
    });

    await test.step('two of two: T03 accessible and open', async () => {
      await expectNodeState(page, 'ALL', 'dndnode_3', 'accessible', 'both predecessors are completed');
      await expectCourseOpenAfterTasks(page, fixtureCourse('T03'), 'both predecessors are completed');
    });
  });
});
