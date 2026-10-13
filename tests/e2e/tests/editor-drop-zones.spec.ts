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
 * editor-drop-zones - which drop zones a node offers, and that every zone
 * offered can be reached (local_adele #595).
 *
 * A node that already has a successor offers no zone for another successor,
 * a node with a predecessor none for another predecessor. Those zones used
 * to be drawn to the side, where the pointer could not get onto them: on the
 * way there the zones were removed or handed to another node. What is
 * offered now must be reachable - led there step by step, not in one jump -
 * and a zone the pointer is on stays.
 *
 * Two shapes, the ones the issue was measured on: a chain of three, and two
 * parallel nodes before a third.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import { reseedFixtures, waitForEditorLoaded } from '../support/fixtures';
import {
  VIEWPORT, DropZone, abandonDrag, dragOverNode, dropCourseAt, dropFirstCourse, leadOntoZone, offeredZones,
  startDrag, zonesAround,
} from '../support/editor';
import { editorPause, POINTER_STEP_MS } from '../support/timing';

/** The Vue application's mount point. */
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/**
 * Start a new, unsaved path (nothing to clean up afterwards).
 *
 * @param page The page, logged in.
 */
async function newPath(page: Page): Promise<void> {
  await page.goto('/local/adele/index.php');
  await app(page).locator('[data-testid="learningpath-create"]').click();
  await waitForEditorLoaded(page);
}

/**
 * With one course dragged over the canvas: node by node, the zones offered
 * are the expected ones, and each of them is reached by leading the pointer
 * there from the node.
 *
 * @param page The page showing the editor.
 * @param expected Node id => the zones it must offer.
 */
async function expectZones(page: Page, expected: Record<string, DropZone[]>): Promise<void> {
  await startDrag(page, 'T05');
  for (const [node, zones] of Object.entries(expected)) {
    for (const zone of zones) {
      // Back onto the node before every zone: the way to be travelled starts there.
      let there = false;
      for (let attempt = 0; attempt < 6 && !there; attempt++) {
        await dragOverNode(page, node, attempt);
        there = await zonesAround(page, node);
      }
      expect(there, `with the pointer on ${node}, the zones must be around it`).toBe(true);
      expect(await offeredZones(page), `the zones offered at ${node}`).toEqual([...zones].sort());

      expect(await leadOntoZone(page, node, zone), `the ${zone} zone of ${node} must be reachable`).toBe(true);
      expect(await zonesAround(page, node), `on its ${zone} zone, the zones must still be those of ${node}`).toBe(true);
    }
  }
  await abandonDrag(page, 'T05');
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-E1 — the drop zones a node offers (#595)', () => {
  test.use({ viewport: VIEWPORT });

  test('a chain: no second successor, no second predecessor, every zone offered is reachable', { tag: ['@story:E1'] }, async ({ page }) => {
    test.setTimeout(300_000);
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);
    await newPath(page);
    await dropFirstCourse(page, 'T01');
    await dropCourseAt(page, 'T02', 'dndnode_1', 'child');
    await dropCourseAt(page, 'T03', 'dndnode_2', 'child');

    await expectZones(page, {
      // The first node: a predecessor (before the start) yes, a second successor no.
      dndnode_1: ['parent', 'and', 'or'],
      // The middle: neither.
      dndnode_2: ['and', 'or'],
      // The end: a successor yes, a second predecessor no.
      dndnode_3: ['child', 'and', 'or'],
    });
    await expect(app(page).locator('[data-testid^="learningpath-node-"]'), 'nothing was dropped').toHaveCount(3);
  });

  test('a zone the pointer is on stays - also where the starting marker lies beneath it', { tag: ['@story:E1'] }, async ({ page }) => {
    // With a single node the starting marker is drawn to its right, under
    // the right part of the zone for "parallel". Moving along the zone onto
    // that part used to count as "on the marker": the zones vanished under
    // the pointer.
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);
    await newPath(page);
    await dropFirstCourse(page, 'T01');

    await startDrag(page, 'T05');
    await dragOverNode(page, 'dndnode_1');
    expect(await zonesAround(page, 'dndnode_1')).toBe(true);
    expect(await offeredZones(page), 'a single node offers all four').toEqual(['and', 'child', 'or', 'parent']);
    expect(await leadOntoZone(page, 'dndnode_1', 'and'), 'the zone must be reachable').toBe(true);

    const zone = page.locator('.vue-flow__node[data-id="dropzone_and"]');
    const marker = page.locator('.vue-flow__node[data-id="starting_node"]').first();
    const pane = page.locator('.vue-flow__pane').first();
    const [area, box, under] = [await pane.boundingBox(), await zone.boundingBox(), await marker.boundingBox()];
    expect(area && box && under).toBeTruthy();
    const target = { x: box!.x + box!.width * 0.85, y: box!.y + box!.height / 2 };
    expect(target.x > under!.x && target.x < under!.x + under!.width && target.y > under!.y && target.y < under!.y + under!.height,
      'the precondition: this spot of the zone lies over the starting marker').toBe(true);

    // Along the zone, in steps, and resting at the end: the editor keeps looking.
    const from = { x: box!.x + box!.width / 2, y: target.y };
    for (let step = 1; step <= 6; step++) {
      await pane.hover({
        position: { x: from.x + ((target.x - from.x) * step) / 6 - area!.x, y: target.y - area!.y },
        force: true,
      });
      await editorPause(page, POINTER_STEP_MS);
    }
    for (let rest = 0; rest < 4; rest++) {
      await pane.hover({ position: { x: target.x - area!.x - (rest % 2), y: target.y - area!.y }, force: true });
      await editorPause(page, POINTER_STEP_MS);
    }
    await expect(zone, 'the zone must still be there').toHaveCount(1);
    await expect(zone, 'and still show the hit').toContainText(/drop to connect|ablegen zum verbinden/i);
    await expect(marker, 'the marker beneath must not claim the pointer').not.toContainText(/drop to connect|ablegen zum verbinden/i);
    expect(await zonesAround(page, 'dndnode_1'), 'around the same node').toBe(true);

    await page.mouse.up();
    await expect(app(page).locator('[data-testid^="learningpath-node-"]'), 'dropped there, the course is added')
      .toHaveCount(2, { timeout: 30_000 });
  });

  test('two parallel nodes before a third: the same rule', { tag: ['@story:E1'] }, async ({ page }) => {
    test.setTimeout(300_000);
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);
    await newPath(page);
    await dropFirstCourse(page, 'T01');
    await dropCourseAt(page, 'T03', 'dndnode_1', 'child');
    await dropCourseAt(page, 'T02', 'dndnode_1', 'and');
    await expect(app(page).locator('[data-testid="learningpath-node-dndnode_3"]')).toContainText('Testkurs 02');

    await expectZones(page, {
      // T01 and T02 (dndnode_3) both start the path and both lead to T03.
      dndnode_1: ['parent', 'and', 'or'],
      dndnode_3: ['parent', 'and', 'or'],
      // T03 (dndnode_2) has two predecessors and no successor.
      dndnode_2: ['child', 'and', 'or'],
    });
    await expect(app(page).locator('[data-testid^="learningpath-node-"]'), 'nothing was dropped').toHaveCount(3);
  });

  test('a branch is built from the side zone of the successor, and each arm continues on its own', { tag: ['@story:E1'] }, async ({ page }) => {
    // The way to a second successor now that its zone is gone: "parallel" at
    // the successor that is already there.
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);
    await newPath(page);
    const edges = (): Promise<(string | null)[]> => page.locator('.vue-flow__edge').evaluateAll(
      (els) => els.map((e) => e.getAttribute('data-id')).sort());

    await dropFirstCourse(page, 'T01');
    await dropCourseAt(page, 'T02', 'dndnode_1', 'child');
    await dropCourseAt(page, 'T04', 'dndnode_2', 'and');
    expect(await edges(), 'T02 and T04 both follow T01').toEqual(['dndnode_2dndnode_1', 'dndnode_3dndnode_1']);

    await dropCourseAt(page, 'T03', 'dndnode_2', 'child');
    expect(await edges(), 'T03 follows T02 alone - the arm of T04 is not touched')
      .toEqual(['dndnode_2dndnode_1', 'dndnode_3dndnode_1', 'dndnode_4dndnode_2']);
  });
});
