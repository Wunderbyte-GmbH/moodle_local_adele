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
 * editor-brackets - chain E2 of the E2E plan (section 7), the bracketed
 * combinations: (A AND B) OR C and A AND (B OR C), each on both levels the
 * editor offers, plus adding and removing a condition and throwing changes
 * away (decision 2026-10-08: "jeweils beide Varianten als Testvarianten,
 * sowohl Knoten als auch Bedingung").
 *
 * Level "condition" - the access criteria of one node (T02, after T01):
 *   A = "According to predecessor nodes" (the editor puts it there on its own),
 *   B = "Manual access", C = "Certain node completed" (here: T01).
 *   Columns side by side are OR, conditions one under the other AND - so
 *   (A AND B) OR C is written directly, A AND (B OR C) multiplied out as
 *   (A AND B) OR (C AND A).
 *
 * Level "node" - the nodes of the path and the predecessor criterion of the
 * node after them (X = T04):
 *   A AND (B OR C): T01 in parallel with a node holding T02 or T03 (a
 *     stack), X requiring 2 of its 2 predecessors.
 *   (A AND B) OR C: T01 -> T02 -> X, and T03 placed before X with the
 *     keyboard dialog (the mouse offers no second predecessor zone, #595);
 *     X requiring 1 of its 2 predecessors. "A AND B" is the sequence here.
 *
 * Asserted on what the server stored, read back through Moodle's CLI. What
 * learners get out of these combinations is chain F3's business.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import { fixture, saveLearningPath, waitForEditorLoaded } from '../support/fixtures';
import { VIEWPORT, dropCourseAt, dropFirstCourse, storedPredecessorCriterion } from '../support/editor';
import {
  chooseNode, closeCriteriaDiscarding, conditions, deleteCondition, dropCondition, insertWithKeyboard,
  openAccessCriteria, requirePredecessors, saveCriteria, storedConditionColumns, storedNodes,
} from '../support/conditions';

const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');
const cardTitled = (page: Page, title: string): Locator =>
  app(page).locator('[data-testid^="learningpath-row-"]').filter({ has: page.locator('h5', { hasText: title }) });

/**
 * Start a new path with T01 -> T02, titled so it can be saved.
 *
 * @param page The page.
 * @param title The title.
 */
async function chainOfTwo(page: Page, title: string): Promise<void> {
  await page.goto('/local/adele/index.php');
  await app(page).locator('[data-testid="learningpath-create"]').click();
  await waitForEditorLoaded(page);
  await app(page).locator('[data-testid="learningpath-title"]').fill(title);
  await app(page).locator('[data-testid="learningpath-description"]').fill('E2E-Kette E2, Klammerung.');
  await dropFirstCourse(page, 'T01');
  await dropCourseAt(page, 'T02', 'dndnode_1', 'child');
}

/**
 * Save the path, leave the editor and open the path again.
 *
 * @param page The page showing the path editor.
 * @param title The title.
 */
async function saveAndReopen(page: Page, title: string): Promise<void> {
  await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
  await expect(app(page).locator('[data-testid="learningpath-create"]')).toBeVisible({ timeout: 30_000 });
  await page.goto('/local/adele/index.php');
  const card = cardTitled(page, title);
  await expect(card).toHaveCount(1, { timeout: 30_000 });
  await card.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
  await expect(app(page).locator('[data-testid^="learningpath-node-"]').first()).toBeVisible({ timeout: 30_000 });
}

/**
 * Remove the path again.
 *
 * @param page The page.
 * @param title The title.
 */
async function removePath(page: Page, title: string): Promise<void> {
  await page.goto('/local/adele/index.php');
  const card = cardTitled(page, title);
  if (await card.count() === 0) {
    return;
  }
  await card.getByRole('button', { name: /^(Delete|Löschen):/ }).click();
  await card.locator('.deletealert .btn-danger').click();
  await expect(card).toHaveCount(0, { timeout: 30_000 });
}

test.describe('ADELE-E2E-E2 — bracketed combinations', () => {
  test.use({ viewport: VIEWPORT });
  test.beforeEach(async ({ page }) => {
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);
  });

  test('condition level: (A AND B) OR C', async ({ page }) => {
    const title = `E2 Bedingung (A und B) oder C ${Date.now()}`;
    await chainOfTwo(page, title);
    try {
      await openAccessCriteria(page, 'dndnode_2');
      await expect(conditions(page), 'A is there from the start').toHaveCount(1);
      await dropCondition(page, 'Manual access', 'condition_1', 'source_and');
      await dropCondition(page, 'Certain node completed', 'condition_1', 'source_or');
      await chooseNode(page, 'condition_3', 'Testkurs 01');
      await saveCriteria(page);
      expect(storedConditionColumns(title, 'dndnode_2'), 'two columns: A and B, or C')
        .toEqual([['parent_courses', 'manual'], ['specific_course']]);
    } finally {
      await removePath(page, title);
    }
  });

  test('condition level: A AND (B OR C), multiplied out', async ({ page }) => {
    const title = `E2 Bedingung A und (B oder C) ${Date.now()}`;
    await chainOfTwo(page, title);
    try {
      await openAccessCriteria(page, 'dndnode_2');
      await dropCondition(page, 'Manual access', 'condition_1', 'source_and');
      await dropCondition(page, 'Certain node completed', 'condition_1', 'source_or');
      await chooseNode(page, 'condition_3', 'Testkurs 01');
      await dropCondition(page, 'According to predecessor nodes', 'condition_3', 'source_and');
      await saveCriteria(page);
      expect(storedConditionColumns(title, 'dndnode_2'), '(A and B) or (C and A)')
        .toEqual([['parent_courses', 'manual'], ['specific_course', 'parent_courses']]);
    } finally {
      await removePath(page, title);
    }
  });

  test('condition level: add, remove, and throw changes away', async ({ page }) => {
    const title = `E2 Bedingung ändern ${Date.now()}`;
    await chainOfTwo(page, title);
    try {
      await test.step('added and removed again before saving', async () => {
        await openAccessCriteria(page, 'dndnode_2');
        await dropCondition(page, 'Manual access', 'condition_1', 'source_and');
        await deleteCondition(page, 'condition_2');
        await saveCriteria(page);
        expect(storedConditionColumns(title, 'dndnode_2')).toEqual([['parent_courses']]);
      });
      await test.step('added, and the change thrown away', async () => {
        await openAccessCriteria(page, 'dndnode_2');
        await dropCondition(page, 'Manual access', 'condition_1', 'source_and');
        await closeCriteriaDiscarding(page);
        expect(storedConditionColumns(title, 'dndnode_2'), 'nothing reached the server').toEqual([['parent_courses']]);
        await openAccessCriteria(page, 'dndnode_2');
        await expect(conditions(page), 'and nothing is left in the editor').toHaveCount(1);
      });
      await test.step('added and saved, removed and saved', async () => {
        await dropCondition(page, 'Manual access', 'condition_1', 'source_and');
        await saveCriteria(page);
        expect(storedConditionColumns(title, 'dndnode_2')).toEqual([['parent_courses', 'manual']]);
        await openAccessCriteria(page, 'dndnode_2');
        await deleteCondition(page, 'condition_2');
        await saveCriteria(page);
        expect(storedConditionColumns(title, 'dndnode_2')).toEqual([['parent_courses']]);
      });
    } finally {
      await removePath(page, title);
    }
  });

  test('node level: A AND (B OR C)', async ({ page }) => {
    test.setTimeout(300_000);
    const title = `E2 Knoten A und (B oder C) ${Date.now()}`;
    await page.goto('/local/adele/index.php');
    await app(page).locator('[data-testid="learningpath-create"]').click();
    await waitForEditorLoaded(page);
    await app(page).locator('[data-testid="learningpath-title"]').fill(title);
    await app(page).locator('[data-testid="learningpath-description"]').fill('E2E-Kette E2, Klammerung.');
    try {
      await test.step('A = T01 and a node holding T02 or T03, both before X = T04', async () => {
        await dropFirstCourse(page, 'T01');
        await dropCourseAt(page, 'T04', 'dndnode_1', 'child');
        await dropCourseAt(page, 'T02', 'dndnode_1', 'and');
        await dropCourseAt(page, 'T03', 'dndnode_3', 'or');
      });
      await test.step('saved and reopened', async () => {
        await saveAndReopen(page, title);
      });
      await test.step('X requires both of its predecessors', async () => {
        await openAccessCriteria(page, 'dndnode_2');
        await requirePredecessors(page, 'condition_1', 2);
        await saveCriteria(page);
      });
      await test.step('stored: the stack holds two courses, X needs 2 of [A, stack]', async () => {
        const nodes = storedNodes(title);
        expect(nodes.dndnode_3.courses, 'B or C: one node, two courses').toBe(2);
        expect(nodes.dndnode_2.parents.sort(), 'X follows A and the stack').toEqual(['dndnode_1', 'dndnode_3']);
        const criterion = storedPredecessorCriterion(title, 'dndnode_2');
        expect([...(criterion?.courses_id || [])].sort()).toEqual(['dndnode_1', 'dndnode_3']);
        expect(Number(criterion?.min_courses), 'A AND (B OR C): both predecessors').toBe(2);
      });
    } finally {
      await removePath(page, title);
    }
  });

  test('node level: (A AND B) OR C', async ({ page }) => {
    test.setTimeout(300_000);
    const title = `E2 Knoten (A und B) oder C ${Date.now()}`;
    await page.goto('/local/adele/index.php');
    await app(page).locator('[data-testid="learningpath-create"]').click();
    await waitForEditorLoaded(page);
    await app(page).locator('[data-testid="learningpath-title"]').fill(title);
    await app(page).locator('[data-testid="learningpath-description"]').fill('E2E-Kette E2, Klammerung.');
    try {
      await test.step('A = T01 -> B = T02 -> X = T04, and C = T03 before X (keyboard)', async () => {
        await dropFirstCourse(page, 'T01');
        await dropCourseAt(page, 'T02', 'dndnode_1', 'child');
        await dropCourseAt(page, 'T04', 'dndnode_2', 'child');
        await insertWithKeyboard(page, fixture('ADELE_FIXTURE_COURSE_T03'), 'Testkurs 04', 'dropzone_parent');
      });
      await test.step('saved and reopened; X asks for 1 of its 2 predecessors', async () => {
        await saveAndReopen(page, title);
        await openAccessCriteria(page, 'dndnode_3');
        await requirePredecessors(page, 'condition_1', 1);
        await saveCriteria(page);
      });
      await test.step('stored: A -> B -> X, C -> X, X needs 1 of [B, C]', async () => {
        const nodes = storedNodes(title);
        expect(nodes.dndnode_2.parents, 'B follows A').toEqual(['dndnode_1']);
        expect(nodes.dndnode_4.parents, 'C starts the path').toEqual(['starting_node']);
        expect(nodes.dndnode_3.parents.sort(), 'X follows B and C').toEqual(['dndnode_2', 'dndnode_4']);
        const criterion = storedPredecessorCriterion(title, 'dndnode_3');
        expect([...(criterion?.courses_id || [])].sort()).toEqual(['dndnode_2', 'dndnode_4']);
        expect(Number(criterion?.min_courses), '(A AND B) OR C: one of the two').toBe(1);
      });
    } finally {
      await removePath(page, title);
    }
  });

  // Was a known defect: a predecessor placed "before" a node did not enter
  // that node's predecessor criterion (the gap #584 closed for the parallel
  // zone). Fixed with the E2E issue, addAutoRestrictions 'parent'.
  test('a predecessor placed before a node counts in its criterion without opening the criteria', async ({ page }) => {
    test.setTimeout(300_000);
    const title = `E2 Vorgänger davor ${Date.now()}`;
    await chainOfTwo(page, title);
    try {
      await insertWithKeyboard(page, fixture('ADELE_FIXTURE_COURSE_T03'), 'Testkurs 02', 'dropzone_parent');
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
      expect(storedNodes(title).dndnode_2.parents.sort(), 'T02 follows T01 and T03').toEqual(['dndnode_1', 'dndnode_3']);
      const criterion = storedPredecessorCriterion(title, 'dndnode_2');
      expect([...(criterion?.courses_id || [])].sort(), 'the criterion must name both predecessors')
        .toEqual(['dndnode_1', 'dndnode_3']);
    } finally {
      await removePath(page, title);
    }
  });

  // Was a known defect: before saving, the predecessor condition counted the
  // predecessors as last loaded ("1 / 1" while the page header listed two).
  // Fixed with the E2E issue: parent_courses.vue reads the path being edited.
  test('the predecessor condition counts the predecessors drawn, saved or not', async ({ page }) => {
    test.setTimeout(300_000);
    const title = `E2 Vorgänger zählen ${Date.now()}`;
    await chainOfTwo(page, title);
    try {
      await dropCourseAt(page, 'T03', 'dndnode_1', 'and');
      await openAccessCriteria(page, 'dndnode_2');
      await requirePredecessors(page, 'condition_1', 2);
    } finally {
      await removePath(page, title);
    }
  });

  // Was a known defect: placed "before" a node that already had a
  // predecessor, the new node was drawn exactly on top of it. Fixed with the
  // E2E issue: it goes next to the existing predecessors, in their row.
  test('a node placed before another with the keyboard does not cover it', async ({ page }) => {
    test.setTimeout(300_000);
    const title = `E2 Vorgänger verdeckt ${Date.now()}`;
    await chainOfTwo(page, title);
    await insertWithKeyboard(page, fixture('ADELE_FIXTURE_COURSE_T03'), 'Testkurs 02', 'dropzone_parent');
    const target = await page.locator('.vue-flow__node[data-id="dndnode_2"]').boundingBox();
    const added = await page.locator('.vue-flow__node[data-id="dndnode_3"]').boundingBox();
    expect(target && added).toBeTruthy();
    const overlap = !(added!.x + added!.width <= target!.x || target!.x + target!.width <= added!.x
      || added!.y + added!.height <= target!.y || target!.y + target!.height <= added!.y);
    expect(overlap, 'the new predecessor must not lie on top of the node it was placed before').toBe(false);
  });
});
