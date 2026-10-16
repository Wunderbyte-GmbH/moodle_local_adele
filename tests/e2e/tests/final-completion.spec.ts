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
 * final-completion - chain Z of the E2E plan (section 18): the path is
 * finished, and the activity that embeds it is completed with it.
 *
 * Four paths, each embedded in a course of its own with the completion rule
 * "learning path finished" (mod_adele):
 *
 *   Z1  regular: linear T01 -> T02 -> T03, the learner completes the courses
 *   Z2  alternative route: the diamond T01 -> T02 | T03 -> T04; the learner
 *       never visits T03 - 100 % and the completion are reached all the same
 *   Z3  manual completion: every node completes on the teacher's tick
 *   Z4  master completion: the learner completes nothing, the teacher's
 *       master checkbox does
 *
 * Checked before the last required step and after it: the node states and
 * the progress (route, percentage) in the learner's path view; the activity's
 * completion as the learner sees it on the course page ("To do" / "Done",
 * with the condition "Finish the learning path"); and the same completion in
 * the teacher's activity completion report. Z3 and Z4 also take the last
 * step back: the activity completion goes with it.
 *
 * The rule had no effect until mod_adele got its custom_completion class and
 * an observer of local_adele's user_path_updated (session protocol, part 31).
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  activityCompletion,
  completeCourse,
  completionReport,
  drainTaskQueue,
  fitPathView,
  fixture,
  fixtureCourse,
  fixturePassword,
  nodeStatus,
  reseedFixtures,
  runCompletion,
  saveLearningPath,
  setMasterCheckbox,
} from '../support/fixtures';
import { dropCourseAt, VIEWPORT } from '../support/editor';
import { app, openEditor, openOverview } from '../support/paths';

const TODO = 'To do | To do: Finish the learning path';
const DONE = 'Done | Done: Finish the learning path';

/** One of the four stories: its fixture key, its learner's last name, its nodes. */
interface Story {
  key: 'Z1' | 'Z2' | 'Z3' | 'Z4';
  lastname: string;
  nodes: string[];
}

const activityOf = (story: Story) => `E2E ${story.key} final`;
const hostOf = (story: Story) => fixture(`ADELE_FIXTURE_${story.key}_HOST`);

/**
 * The learner's path view: node states, route and percentage.
 *
 * @param page The page, logged in as the learner.
 * @param story The story.
 */
async function pathView(page: Page, story: Story): Promise<string> {
  drainTaskQueue();
  const states: string[] = [];
  for (const nodeid of story.nodes) {
    states.push(await nodeStatus(page, hostOf(story), activityOf(story), nodeid));
  }
  const row = page.locator('tr').filter({ hasText: story.lastname }).first();
  const route = ((await row.locator('td').nth(3).innerText()) || '').trim();
  const percent = (await row.getByRole('progressbar').getAttribute('aria-valuenow')) || '';
  return `${states.join(' ')} | ${route} | ${percent}`;
}

/**
 * Expect a state on all three levels: the path as the learner sees it, the
 * activity's completion on the learner's course page, and the teacher's
 * report.
 *
 * @param page The page.
 * @param story The story.
 * @param path "<node states> | <route> | <percent>".
 * @param completed Whether the activity must be completed.
 * @param why What the state means.
 */
async function expectState(page: Page, story: Story, path: string, completed: boolean, why: string): Promise<void> {
  await loginAs(page, fixture(`ADELE_FIXTURE_${story.key}_LEARNER`), fixturePassword());
  await expect.poll(() => pathView(page, story), { message: `${why}: the path`, timeout: 90_000, intervals: [2_000, 5_000] })
    .toBe(path);
  expect(await activityCompletion(page, hostOf(story), activityOf(story)), `${why}: the activity, for the learner`)
    .toBe(completed ? DONE : TODO);
  await loginAs(page, env.adminUser, env.adminPassword);
  expect(await completionReport(page, hostOf(story), activityOf(story), story.lastname), `${why}: the activity, in the report`)
    .toBe(completed ? 'Completed' : 'Not completed');
}

/**
 * As the learner: complete courses, one after the other.
 *
 * @param page The page.
 * @param story The story.
 * @param courses Short names, e.g. ["T01", "T02"].
 */
async function completeCourses(page: Page, story: Story, courses: string[]): Promise<void> {
  await loginAs(page, fixture(`ADELE_FIXTURE_${story.key}_LEARNER`), fixturePassword());
  for (const course of courses) {
    drainTaskQueue();
    await completeCourse(page, fixtureCourse(course));
    runCompletion();
  }
}

/**
 * As a teacher: set "Finish node manually" on the given nodes and save.
 *
 * @param page The page.
 * @param story The story.
 * @param nodes Node ids.
 * @param completed Whether the nodes are to be completed.
 */
async function setManualCompletion(page: Page, story: Story, nodes: string[], completed: boolean): Promise<void> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await page.goto(`/course/view.php?id=${hostOf(story)}`);
  await page.getByRole('main').getByRole('link', { name: activityOf(story) }).click();
  await page.locator('tr').filter({ hasText: story.lastname }).first().getByRole('link').first().click();
  await expect(page.locator(`[data-testid="learningpath-node-${nodes[0]}"]`)).toBeVisible({ timeout: 60_000 });
  await fitPathView(page);
  for (const nodeid of nodes) {
    const node = page.locator(`[data-testid="learningpath-node-${nodeid}"]`);
    await expect(node).toBeVisible({ timeout: 60_000 });
    const box = node.getByLabel('Finish node manually');
    await expect(box, `the teacher must be offered the manual completion on ${nodeid}`).toBeVisible();
    // The label leads to the completion box, not to the release box (#598).
    await expect(box).toHaveAttribute('id', /_manual_completion$/);
    if ((await box.isChecked()) !== completed) {
      // A real click: a clipped or covered control makes the test red.
      await box.click({ timeout: 15_000 });
    }
    await expect(box).toBeChecked({ checked: completed });
  }
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByText('Learning path saved')).toBeVisible({ timeout: 30_000 });
  drainTaskQueue();
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-Z — the path is finished, the activity is completed', () => {
  // Tall enough for the editor's canvas (Z1 extends its path by drag and drop).
  test.use({ viewport: VIEWPORT });

  test('Z1: regular completion', { tag: ['@story:Z1', '@withdrawal'] }, async ({ page }) => {
    const story: Story = { key: 'Z1', lastname: 'Final regular', nodes: ['dndnode_1', 'dndnode_2', 'dndnode_3'] };

    await test.step('at the start: nothing completed', async () => {
      await expectState(page, story, 'accessible not_accessible not_accessible | 0/3 | 0', false, 'start');
    });

    await test.step('before the last required step: below 100 %, path open, activity not completed', async () => {
      await completeCourses(page, story, ['T01', 'T02']);
      await expectState(page, story, 'completed completed accessible | 2/3 | 66.67', false, 'two of three');
    });

    await test.step('after the last step: 100 %, path finished, activity completed', async () => {
      await completeCourses(page, story, ['T03']);
      await expectState(page, story, 'completed completed completed | 3/3 | 100', true, 'three of three');
    });

    const longer: Story = { ...story, nodes: [...story.nodes, 'dndnode_4'] };
    await test.step('the author adds T04 behind T03: recomputed, the path is not finished any more', async () => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await openOverview(page);
      const row = app(page).locator('[data-testid^="learningpath-row-"]')
        .filter({ has: page.locator('h5', { hasText: /^\s*E2E Abschluss regulär\s*$/ }) });
      await expect(row).toHaveCount(1, { timeout: 30_000 });
      const lpid = ((await row.getAttribute('data-testid')) || '').replace('learningpath-row-', '');
      await openEditor(page, lpid);
      await dropCourseAt(page, 'T04', 'dndnode_3', 'child');
      await expect(app(page).locator('[data-testid="learningpath-node-dndnode_4"]')).toContainText('Testkurs 04');
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
      await expectState(page, longer, 'completed completed completed accessible | 3/4 | 75', false, 'a fourth step was added');
    });

    await test.step('T04 completed as well: 100 % and completed again', async () => {
      await completeCourses(page, longer, ['T04']);
      await expectState(page, longer, 'completed completed completed completed | 4/4 | 100', true, 'four of four');
    });
  });

  test('Z2: alternative route - the branch not taken does not stand in the way', { tag: ['@story:Z2'] }, async ({ page }) => {
    const story: Story = {
      key: 'Z2',
      lastname: 'Final route',
      nodes: ['dndnode_1', 'dndnode_2', 'dndnode_3', 'dndnode_4'],
    };

    await test.step('at the start: nothing completed, the activity not either', async () => {
      await expectState(page, story, 'accessible not_accessible not_accessible not_accessible | 0/3 | 0', false, 'start');
    });

    await test.step('T01 and T02 completed, T03 never visited: T04 is open, the path is not finished', async () => {
      await completeCourses(page, story, ['T01', 'T02']);
      await expectState(page, story, 'completed completed accessible accessible | 2/3 | 66.67', false, 'before T04');
    });

    await test.step('T04 completed: 100 % and completion, with T03 still untouched', async () => {
      await completeCourses(page, story, ['T04']);
      await expectState(page, story, 'completed completed accessible completed | 3/3 | 100', true, 'route T01-T02-T04');
    });
  });

  test('Z3: manual completion', { tag: ['@story:Z3', '@withdrawal'] }, async ({ page }) => {
    const story: Story = { key: 'Z3', lastname: 'Final manual', nodes: ['dndnode_1', 'dndnode_2', 'dndnode_3'] };

    await test.step('at the start: nothing completed', async () => {
      await expectState(page, story, 'accessible not_accessible not_accessible | 0/3 | 0', false, 'start');
    });

    await test.step('two nodes completed manually: not finished', async () => {
      await setManualCompletion(page, story, ['dndnode_1', 'dndnode_2'], true);
      await expectState(page, story, 'completed completed accessible | 2/3 | 66.67', false, 'two manual completions');
    });

    await test.step('the third: 100 %, path finished, activity completed', async () => {
      await setManualCompletion(page, story, ['dndnode_3'], true);
      await expectState(page, story, 'completed completed completed | 3/3 | 100', true, 'three manual completions');
    });

    await test.step('the third taken back: the activity completion goes with it', async () => {
      await setManualCompletion(page, story, ['dndnode_3'], false);
      await expectState(page, story, 'completed completed accessible | 2/3 | 66.67', false, 'manual completion withdrawn');
    });
  });

  test('Z4: master completion', { tag: ['@story:Z4', '@withdrawal'] }, async ({ page }) => {
    const story: Story = { key: 'Z4', lastname: 'Final master', nodes: ['dndnode_1', 'dndnode_2', 'dndnode_3'] };
    const master = async (nodes: string[], on: boolean) => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await setMasterCheckbox(page, hostOf(story), activityOf(story), story.lastname, nodes,
        'Master completion checkbox', on);
    };

    await test.step('at the start: nothing completed', async () => {
      await expectState(page, story, 'accessible not_accessible not_accessible | 0/3 | 0', false, 'start');
    });

    await test.step('master completion on two nodes: not finished', async () => {
      await master(['dndnode_1', 'dndnode_2'], true);
      await expectState(page, story, 'completed completed accessible | 2/3 | 66.67', false, 'two master completions');
    });

    await test.step('on the third: 100 %, path finished, activity completed', async () => {
      await master(['dndnode_3'], true);
      await expectState(page, story, 'completed completed completed | 3/3 | 100', true, 'three master completions');
    });

    await test.step('removed from the third: the activity completion goes with it', async () => {
      await master(['dndnode_3'], false);
      await expectState(page, story, 'completed completed accessible | 2/3 | 66.67', false, 'master completion removed');
    });
  });
});
