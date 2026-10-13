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
 * editor-drag-and-drop - the first part of chain E1 of the E2E plan
 * (section 7): a path is built through the graphical editor, with a real
 * drag, and what was built is still there after leaving and reopening.
 *
 * Real HTML5 drag-and-drop, not the keyboard route added for #575 B5: the
 * user story is about the graphical editor, and the keyboard path would not
 * exercise the drop handling at all.
 *
 * Covered here: steps 1, 2, 11, 12 and 13 of the plan - create, drop the
 * first course, chain further courses onto existing nodes, save, leave,
 * reopen - and that the drop zones are offered at the node the pointer is
 * at (#580).
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import { fixture, fixturePassword, saveLearningPath, waitForEditorLoaded } from '../support/fixtures';
import {
  VIEWPORT, abandonDrag, dragOntoZone, dragOverNode, dropCourseAt, dropFirstCourse, startDrag, zonesAround,
} from '../support/editor';

/** The Vue application's mount point. */
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/** A course entry in the editor's sidebar, by its course id. */
const sidebarCourse = (page: Page, shortname: string): Locator =>
  page.locator(`[data-draggable="${fixture('ADELE_FIXTURE_COURSE_' + shortname)}"]`);

/** The overview card of a path, found by its title. */
const cardTitled = (page: Page, title: string): Locator =>
  app(page).locator('[data-testid^="learningpath-row-"]')
    .filter({ has: page.locator('h5', { hasText: title }) });

test.describe('ADELE-E2E-E1 — a path is built in the graphical editor', () => {
  // Tall enough for the whole canvas: a scroll in the middle of a drag moves
  // every target the pointer is aiming at.
  test.use({ viewport: VIEWPORT });

  test('the reference path T01 -> T02 -> T03 is built by drag and drop alone', { tag: ['@story:E1'] }, async ({ page }) => {
    const title = `E1 Referenzpfad ${Date.now()}`;
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);

    await test.step('a manager starts a new path', async () => {
      await page.goto('/local/adele/index.php');
      await app(page).locator('[data-testid="learningpath-create"]').click();
      await waitForEditorLoaded(page);
      await app(page).locator('[data-testid="learningpath-title"]').fill(title);
      await app(page).locator('[data-testid="learningpath-description"]')
        .fill('Referenzpfad der E2E-Kette E1, ausschließlich per Drag&Drop.');
    });

    await test.step('T01 onto the empty canvas, then T02 and T03 each as successor', async () => {
      await dropFirstCourse(page, 'T01');
      await dropCourseAt(page, 'T02', 'dndnode_1', 'child');
      await dropCourseAt(page, 'T03', 'dndnode_2', 'child');
    });

    await test.step('saved, left and reopened, the path is unchanged', async () => {
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
      await expect(app(page).locator('[data-testid="learningpath-create"]')).toBeVisible({ timeout: 30_000 });

      await page.goto('/local/adele/index.php');
      const card = cardTitled(page, title);
      await expect(card).toHaveCount(1, { timeout: 30_000 });
      await card.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();

      const nodes = app(page).locator('[data-testid^="learningpath-node-"]');
      await expect(nodes).toHaveCount(3, { timeout: 30_000 });
      for (const [id, name] of [['dndnode_1', 'Testkurs 01'], ['dndnode_2', 'Testkurs 02'], ['dndnode_3', 'Testkurs 03']]) {
        await expect(app(page).locator(`[data-testid="learningpath-node-${id}"]`)).toContainText(name);
      }
      // The structure, not just the nodes: a chain, T03 hanging from T02 and
      // not from T01. Edge ids are "<target><source>".
      const edges = await page.locator('.vue-flow__edge').evaluateAll(
        (els) => els.map((e) => e.getAttribute('data-id')).sort());
      expect(edges, 'the path must be the chain T01 -> T02 -> T03')
        .toEqual(['dndnode_2dndnode_1', 'dndnode_3dndnode_2']);
    });

    await test.step('the manager removes the path again', async () => {
      await page.goto('/local/adele/index.php');
      const card = cardTitled(page, title);
      await card.getByRole('button', { name: /^(Delete|Löschen):/ }).click();
      await card.locator('.deletealert .btn-danger').click();
      await expect(card).toHaveCount(0, { timeout: 30_000 });
    });
  });

  test('the drop zones follow the pointer from node to node (#580)', { tag: ['@story:E1'] }, async ({ page }) => {
    // Somebody dragging a course from the sidebar to a node further down
    // passes other nodes on the way. The zones used to stay at the node
    // passed first; they have to be at the node the pointer is at.
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);
    const edges = (): Promise<(string | null)[]> => page.locator('.vue-flow__edge').evaluateAll(
      (els) => els.map((e) => e.getAttribute('data-id')).filter((id) => /^dndnode_\d+dndnode_\d+$/.test(id || '')).sort());

    await test.step('a path of two nodes, T01 -> T02 (not saved: nothing to clean up)', async () => {
      await page.goto('/local/adele/index.php');
      await app(page).locator('[data-testid="learningpath-create"]').click();
      await waitForEditorLoaded(page);
      await dropFirstCourse(page, 'T01');
      await dropCourseAt(page, 'T02', 'dndnode_1', 'child');
      expect(await edges()).toEqual(['dndnode_2dndnode_1']);
    });

    await test.step('T03 is dragged past T01 to T02 and dropped as its successor', async () => {
      await dropCourseAt(page, 'T03', 'dndnode_2', 'child', 'dndnode_1');
      expect(await edges(), 'T03 must hang from T02, the node it was dropped at')
        .toEqual(['dndnode_2dndnode_1', 'dndnode_3dndnode_2']);
    });

    await test.step('back and forth: the zones are at the node the pointer is at', async () => {
      await startDrag(page, 'T04');
      for (const node of ['dndnode_1', 'dndnode_3', 'dndnode_2', 'dndnode_1']) {
        let there = false;
        for (let attempt = 0; attempt < 6 && !there; attempt++) {
          await dragOverNode(page, node, attempt);
          there = await zonesAround(page, node);
        }
        expect(there, `with the pointer on ${node}, the zones must be around it`).toBe(true);
      }
      await abandonDrag(page, 'T04');
      await expect(app(page).locator('[data-testid^="learningpath-node-"]')).toHaveCount(3);
    });
  });

  test('a zone that lies nearer to a neighbouring node stays under the pointer (#580)', { tag: ['@story:E1'] }, async ({ page }) => {
    // The exception to "the zones are at the nearest node": T01 and T02 side
    // by side, both leading to T03. The zone for "parallel to T01" is drawn
    // to the right of T01 - where T02 is. Aiming at it brings the pointer
    // nearer to T02 than to T01, and the zone must not vanish under it.
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);
    const centre = async (id: string): Promise<{ x: number; y: number }> => {
      const box = await page.locator(`.vue-flow__node[data-id="${id}"]`).first().boundingBox();
      expect(box, `${id} must be on screen`).not.toBeNull();
      return { x: box!.x + box!.width / 2, y: box!.y + box!.height / 2 };
    };
    const distance = (a: { x: number; y: number }, b: { x: number; y: number }): number => Math.hypot(a.x - b.x, a.y - b.y);

    await test.step('T01 and T02 in parallel, both before T03 (not saved)', async () => {
      await page.goto('/local/adele/index.php');
      await app(page).locator('[data-testid="learningpath-create"]').click();
      await waitForEditorLoaded(page);
      await dropFirstCourse(page, 'T01');
      await dropCourseAt(page, 'T03', 'dndnode_1', 'child');
      await dropCourseAt(page, 'T02', 'dndnode_1', 'and');
      await expect(app(page).locator('[data-testid="learningpath-node-dndnode_3"]')).toContainText('Testkurs 02');
    });

    await test.step('the pointer on the parallel zone of T01: nearer to T02, and the zones stay', async () => {
      await startDrag(page, 'T04');
      await dragOverNode(page, 'dndnode_1');
      expect(await zonesAround(page, 'dndnode_1'), 'the zones must be around T01').toBe(true);
      const zone = await centre('dropzone_and');
      expect(distance(zone, await centre('dndnode_3')), 'the precondition: the zone is nearer to T02 than to T01, its own node')
        .toBeLessThan(distance(zone, await centre('dndnode_1')));

      expect(await dragOntoZone(page, 'and'), 'the editor must register the pointer on the zone').toBe(true);
      expect(await zonesAround(page, 'dndnode_1'), 'the zones must still be those of T01').toBe(true);

      // And off the zone, on T02, they are T02's.
      let there = false;
      for (let attempt = 0; attempt < 6 && !there; attempt++) {
        await dragOverNode(page, 'dndnode_3', attempt);
        there = await zonesAround(page, 'dndnode_3');
      }
      expect(there, 'with the pointer on T02 itself, the zones must be around T02').toBe(true);
      await abandonDrag(page, 'T04');
      await expect(app(page).locator('[data-testid^="learningpath-node-"]')).toHaveCount(3);
    });
  });

  test('a course dropped into the editor is still there after reopening', { tag: ['@story:E1'] }, async ({ page }) => {
    const title = `E1 Drag und Drop ${Date.now()}`;

    // Deleting a node asks through the browser's own confirm(); accepted
    // here so the cleanup at the end cannot hang on it.
    page.on('dialog', (dialog) => dialog.accept());

    await loginAs(page, env.adminUser, env.adminPassword);

    await test.step('a manager starts a new path', async () => {
      await page.goto('/local/adele/index.php');
      await app(page).locator('[data-testid="learningpath-create"]').click();
      await waitForEditorLoaded(page);
      await app(page).locator('[data-testid="learningpath-title"]').fill(title);
      await app(page).locator('[data-testid="learningpath-description"]')
        .fill('Angelegt von der E2E-Kette E1, ausschließlich über den Editor.');
    });

    await test.step('a course is dragged from the sidebar onto the canvas', async () => {
      const course = sidebarCourse(page, 'T01');
      await expect(course, 'the sidebar must offer the fixture course').toBeVisible({ timeout: 30_000 });

      // The empty canvas shows the starting marker, and that is the drop
      // target. dragTo performs a real HTML5 drag - the mouse API does not
      // trigger one here, because the sidebar entries are draggable elements
      // rather than mouse-driven widgets.
      await course.dragTo(page.locator('.vue-flow__node[data-id="starting_node"]').first());

      const node = app(page).locator('[data-testid^="learningpath-node-"]');
      await expect(node, 'the drop must produce a node').toHaveCount(1, { timeout: 30_000 });
      await expect(node).toContainText('Testkurs 01');
    });

    await test.step('the path is saved and reopened', async () => {
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
      await expect(app(page).locator('[data-testid="learningpath-create"]'))
        .toBeVisible({ timeout: 30_000 });

      await page.goto('/local/adele/index.php');
      const card = cardTitled(page, title);
      await expect(card).toHaveCount(1, { timeout: 30_000 });
      await card.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();

      // What was dropped has to come back from the server, with its business
      // identity and a state attribute - not just as a picture.
      const node = app(page).locator('[data-testid^="learningpath-node-"]');
      await expect(node, 'the node must survive leaving and reopening the editor')
        .toHaveCount(1, { timeout: 30_000 });
      await expect(node).toContainText('Testkurs 01');
      await expect(node).toHaveAttribute('data-status', /accessible|completed|not_accessible|closed|unknown/);
    });

    await test.step('the manager removes the path again', async () => {
      await page.goto('/local/adele/index.php');
      const card = cardTitled(page, title);
      await card.getByRole('button', { name: /^(Delete|Löschen):/ }).click();
      await card.locator('.deletealert .btn-danger').click();
      await expect(card).toHaveCount(0, { timeout: 30_000 });
    });
  });

  test('a learner cannot build anything', { tag: ['@story:E1'] }, async ({ page }) => {
    // The negative control for the authoring story: the editor is not merely
    // hidden, it is not reachable.
    await loginAs(page, fixture('ADELE_FIXTURE_CONTROL_USER'), fixturePassword());
    await page.goto('/local/adele/index.php');
    await expect(app(page)).toBeVisible({ timeout: 30_000 });

    await expect(app(page).locator('[data-testid="learningpath-create"]')).toHaveCount(0);
    await expect(page.locator('[data-draggable]'),
      'a learner must not even be offered courses to drag').toHaveCount(0);
  });
});
