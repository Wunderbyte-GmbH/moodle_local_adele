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
 * editor-combinations - chain E2 of the E2E plan (section 7): logical
 * combinations built by real drag-and-drop.
 *
 *   A ODER B: a course dropped on the "or" zone joins the node as an
 *             alternative - one node, two courses, finishing either is enough.
 *   A UND B:  a course dropped on the "and" zone becomes a parallel node that
 *             shares the successors, and every shared successor names it in
 *             its predecessor criterion (#584). The number of predecessors to
 *             complete stays at the editor's default of 1 - "any one of them",
 *             as the criterion editor sets it everywhere else.
 *
 * Asserted on what comes back from the server after reopening: number of
 * nodes, the courses in them, and the edges between them.
 *
 * Note on the interface: the "and" zone is labelled "Alternative node", the
 * "or" zone "Add to node". With #584 the label fits the behaviour: the
 * parallel node is a further predecessor, and one of them is enough by
 * default. Only the internal name "and" does not fit; nobody sees it.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import { saveLearningPath, waitForEditorLoaded } from '../support/fixtures';
import { VIEWPORT, dropCourseAt, dropFirstCourse, storedPredecessorCriterion } from '../support/editor';

const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');
const cardTitled = (page: Page, title: string): Locator =>
  app(page).locator('[data-testid^="learningpath-row-"]').filter({ has: page.locator('h5', { hasText: title }) });

/**
 * Start a new path in the editor.
 *
 * @param page The page.
 * @param title The title.
 */
async function newPath(page: Page, title: string): Promise<void> {
  await page.goto('/local/adele/index.php');
  await app(page).locator('[data-testid="learningpath-create"]').click();
  await waitForEditorLoaded(page);
  await app(page).locator('[data-testid="learningpath-title"]').fill(title);
  await app(page).locator('[data-testid="learningpath-description"]').fill('E2E-Kette E2, logische Kombination.');
}

/**
 * Save, leave and reopen the path.
 *
 * @param page The page.
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
  await expect(app(page)).toBeVisible({ timeout: 30_000 });
  const card = cardTitled(page, title);
  // Also called from finally after a failure: a path that never got saved
  // has nothing to remove.
  if (!(await card.count())) {
    return;
  }
  await card.getByRole('button', { name: /^(Delete|Löschen):/ }).click();
  await card.locator('.deletealert .btn-danger').click();
  await expect(card).toHaveCount(0, { timeout: 30_000 });
}

/** Edge ids, "<target><source>", sorted. */
const edgeIds = (page: Page): Promise<string[]> =>
  page.locator('.vue-flow__edge').evaluateAll((els) => els.map((e) => e.getAttribute('data-id') || '').sort());

test.describe('ADELE-E2E-E2 — logical combinations by drag and drop', () => {
  test.use({ viewport: VIEWPORT });

  test.beforeEach(async ({ page }) => {
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);
  });

  test('A ODER B: the second course joins the node as an alternative', { tag: ['@story:E2'] }, async ({ page }) => {
    const title = `E2 ODER ${Date.now()}`;
    await newPath(page, title);
    try {
      await dropFirstCourse(page, 'T01');
      await dropCourseAt(page, 'T02', 'dndnode_1', 'or');
      await saveAndReopen(page, title);

      const nodes = app(page).locator('[data-testid^="learningpath-node-"]');
      await expect(nodes, 'an alternative is part of the node, not a node of its own').toHaveCount(1);
      await expect(nodes.first()).toContainText('Testkurs 01');
      await expect(nodes.first()).toContainText('Testkurs 02');
      expect(await edgeIds(page), 'a single node has no edges').toEqual([]);
    } finally {
      await removePath(page, title);
    }
  });

  test('A UND B: a parallel node shares the successor', { tag: ['@story:E2'] }, async ({ page }) => {
    const title = `E2 UND ${Date.now()}`;
    await newPath(page, title);
    try {
      // T01 -> T03 first, so the successor exists when T02 joins in parallel.
      await dropFirstCourse(page, 'T01');
      await dropCourseAt(page, 'T03', 'dndnode_1', 'child');
      await dropCourseAt(page, 'T02', 'dndnode_1', 'and');
      await saveAndReopen(page, title);

      await expect(app(page).locator('[data-testid^="learningpath-node-"]')).toHaveCount(3);
      await expect(app(page).locator('[data-testid="learningpath-node-dndnode_3"]')).toContainText('Testkurs 02');
      // T03 (dndnode_2) hangs from BOTH T01 (dndnode_1) and T02 (dndnode_3).
      expect(await edgeIds(page), 'the successor must be reached from both parallel nodes')
        .toEqual(['dndnode_2dndnode_1', 'dndnode_2dndnode_3']);

      // #584: the stored criterion, not the one the criterion editor would
      // reconstruct on opening.
      const criterion = storedPredecessorCriterion(title, 'dndnode_2');
      expect(criterion, 'the successor must keep a predecessor criterion').not.toBeNull();
      expect([...(criterion?.courses_id || [])].sort(), 'both parallel nodes must count as predecessors')
        .toEqual(['dndnode_1', 'dndnode_3']);
      expect(Number(criterion?.min_courses), 'the default: any one of them').toBe(1);
    } finally {
      await removePath(page, title);
    }
  });

});
