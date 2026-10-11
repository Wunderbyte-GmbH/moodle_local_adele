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
 * Shared by the two files of chain F (plan section 10): feedback-texts and
 * zz-time-feedback.
 *
 * Every fixture of the chain is one learner in a host course of their own:
 * key LINEAR -> login ADELE_FIXTURE_F_LINEAR_LEARNER, course
 * ADELE_FIXTURE_F_LINEAR_HOST, activity "E2E F linear", last name
 * "Feedback linear" (seed_fixtures.php, "F: feedback texts").
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { Page, expect, test } from '@playwright/test';
import { env, loginAs } from './env';
import {
  NodeTexts,
  completeCourse,
  drainTaskQueue,
  fixture,
  fixtureCourse,
  fixturePassword,
  nodeTexts,
  runCompletion,
} from './fixtures';

/** The sentences the feedback area is built from (English). */
export const EN = {
  unlocked: 'This course/stack is unlocked.',
  locked: 'You do not have access to this course/stack. It will be unlocked when:',
  gone: 'This course/stack can no longer be unlocked by you.',
  todo: 'To complete this course/stack, you need to:',
  done: 'This course/stack is considered completed because you:',
  completeCourse: 'successfully complete this course.',
  completedCourse: 'successfully completed this course.',
};

/** The same in German, as far as chain F reads them. */
export const DE = {
  unlocked: 'Dieser Kurs/Stapel ist freigeschaltet.',
  locked: 'Sie haben keinen Zugang zu diesem Kurs/Stapel. Eine Freischaltung erfolgt, wenn:',
};

/** The speech bubble's colour for a completed node and for one that can no longer be unlocked. */
export const COLOUR = { completed: 'rgb(89, 153, 60)', gone: 'rgb(204, 0, 0)' };

/**
 * Known defects: tests that state the behaviour a learner should get and
 * fail today. Skipped (fixme) in a normal run; ADELE_RUN_KNOWN_DEFECTS=1 runs
 * them, to see them fail - or pass, once the defect is fixed.
 */
export const knownDefect = process.env.ADELE_RUN_KNOWN_DEFECTS ? test : test.fixme;

const label = (key: string): string => key.toLowerCase().replace(/_/g, ' ');

/** Whether this fixture was seeded (the German ones need the language pack). */
export const seeded = (key: string): boolean => Boolean(process.env[`ADELE_FIXTURE_F_${key}_LEARNER`]);

/**
 * What the fixture's learner reads on a node. With an expected state, the
 * task queue is run until the node shows it - bounded; a change of state is
 * the work of tasks, not of the click that queued them.
 *
 * @param page The page.
 * @param key The fixture, e.g. "LINEAR".
 * @param nodeid The node.
 * @param state The data-status to wait for, if a change is expected.
 */
export async function learnerReads(page: Page, key: string, nodeid: string, state?: string): Promise<NodeTexts> {
  await loginAs(page, fixture(`ADELE_FIXTURE_F_${key}_LEARNER`), fixturePassword());
  const host = fixture(`ADELE_FIXTURE_F_${key}_HOST`);
  const activity = `E2E F ${label(key)}`;
  drainTaskQueue();
  if (!state) {
    return nodeTexts(page, host, activity, nodeid);
  }
  let texts: NodeTexts = { status: '', info: '', feedback: '', colour: '' };
  await expect
    .poll(async () => {
      drainTaskQueue();
      texts = await nodeTexts(page, host, activity, nodeid);
      return texts.status;
    }, { message: `${key}: ${nodeid} must become "${state}"`, timeout: 90_000, intervals: [2_000, 4_000] })
    .toBe(state);
  return texts;
}

/**
 * The fixture's learner completes a course, and the system acts on it.
 *
 * @param page The page.
 * @param key The fixture.
 * @param shortname The course, e.g. "T01".
 */
export async function learnerCompletes(page: Page, key: string, shortname: string): Promise<void> {
  await loginAs(page, fixture(`ADELE_FIXTURE_F_${key}_LEARNER`), fixturePassword());
  await completeCourse(page, fixtureCourse(shortname));
  runCompletion();
}

/**
 * As a teacher: open the learner's path, set one of the per-learner boxes on
 * a node ("Unlock node manually", "Finish node manually") and save.
 *
 * @param page The page.
 * @param key The fixture.
 * @param nodeid The node.
 * @param box The box's label.
 * @param checked Whether it is to be ticked.
 */
export async function teacherSets(page: Page, key: string, nodeid: string, box: string, checked: boolean): Promise<void> {
  await loginAs(page, env.adminUser, env.adminPassword);
  await page.goto(`/course/view.php?id=${fixture(`ADELE_FIXTURE_F_${key}_HOST`)}`);
  await page.getByRole('main').getByRole('link', { name: `E2E F ${label(key)}` }).click();
  await page.locator('tr').filter({ hasText: `Feedback ${label(key)}` }).first().getByRole('link').first().click();
  const node = page.locator(`[data-testid="learningpath-node-${nodeid}"]`);
  await expect(node).toBeVisible({ timeout: 60_000 });
  const checkbox = node.getByLabel(box);
  await expect(checkbox, `the teacher must be offered "${box}" on ${nodeid}`).toBeVisible();
  if ((await checkbox.isChecked()) !== checked) {
    await checkbox.click();
  }
  await expect(checkbox).toBeChecked({ checked });
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByText('Learning path saved')).toBeVisible({ timeout: 30_000 });
  drainTaskQueue();
}

/**
 * No text a learner reads may show a placeholder that was never filled in
 * ({item}, {node_name}, {start_date}, {end_date} ... - plan F1).
 *
 * @param texts What the learner reads.
 * @param where For the failure message.
 */
export function expectNoRawPlaceholders(texts: NodeTexts, where: string): void {
  expect(texts.info, `${where}: information card`).not.toMatch(/\{[^}]*\}/);
  expect(texts.feedback, `${where}: feedback area`).not.toMatch(/\{[^}]*\}/);
}
