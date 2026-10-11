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
 * Real drag-and-drop in the learning path editor.
 *
 * How the editor works: while a course from the sidebar is dragged near a
 * node, four drop zones appear around that node (predecessor, successor and
 * two parallel ones). Dropping on a zone inserts the course in that relation.
 * The zones exist only DURING the drag, and the canvas re-frames its view as
 * they appear - so the target moves while the pointer is on its way.
 *
 * What did NOT work, measured (local_adele #580):
 * - locator.dragTo(): resolves its target before the drag starts, when the
 *   zones do not exist yet.
 * - the raw mouse API (mouse.down/move/up): starts no HTML5 drag here at all,
 *   not a single drag event arrives.
 * - aiming once at a zone's centre: the re-framed canvas pushes the lower
 *   zones partly outside the visible area, and the page may scroll.
 *
 * What works: start the drag with locator.hover() + mouse.down() and steer it
 * with pane.hover({ position, force }) - those moves DO carry an HTML5 drag.
 * Then re-measure the zone on every step and aim into its visible part until
 * the editor itself confirms the hit ("Drop to connect"), and only then drop.
 *
 * Tests using this need a viewport tall enough to show the whole canvas
 * (see VIEWPORT), otherwise the browser scrolls mid-drag.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { execFileSync } from 'node:child_process';
import { expect, Locator, Page } from '@playwright/test';
import { fixture } from './fixtures';

/** A viewport in which the whole editor canvas fits without scrolling. */
export const VIEWPORT = { width: 1920, height: 1800 };

/** The relation a dropped course gets to the node it is dropped next to. */
export type DropZone = 'parent' | 'child' | 'and' | 'or';

/** The text a zone shows once the editor registers the pointer on it. */
const HIT = /drop to connect|ablegen zum verbinden/i;

/** The Vue application's mount point. */
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/**
 * The sidebar entry of a fixture course.
 *
 * @param page The page.
 * @param shortname The fixture course, e.g. "T01".
 */
export const sidebarCourse = (page: Page, shortname: string): Locator =>
  page.locator(`[data-draggable="${fixture('ADELE_FIXTURE_COURSE_' + shortname)}"]`);

/** The canvas the drag is steered across. */
const pane = (page: Page): Locator => page.locator('.vue-flow__pane').first();

/** A graph element by its business id, e.g. "dndnode_1" or "dropzone_child". */
const flowNode = (page: Page, id: string): Locator => page.locator(`.vue-flow__node[data-id="${id}"]`);

/**
 * The box of an element if it exists RIGHT NOW, else null.
 *
 * locator.boundingBox() waits for the element to appear - for a drop zone
 * that is not drawn yet, that is the whole test timeout.
 *
 * @param locator The element.
 */
async function boxNow(locator: Locator): Promise<{ x: number; y: number; width: number; height: number } | null> {
  if ((await locator.count()) === 0) {
    return null;
  }
  return locator.first().boundingBox({ timeout: 1_000 }).catch(() => null);
}

/**
 * Whether the zone currently drawn lies on the expected side of the target.
 *
 * Zone ids are fixed names, not per node, so their position is the only way
 * to tell which node they belong to.
 *
 * @param page The page.
 * @param zone The zone.
 * @param target The node it should belong to.
 */
async function zoneBelongsTo(page: Page, zone: DropZone, target: string): Promise<boolean> {
  const z = await boxNow(flowNode(page, `dropzone_${zone}`));
  const t = await boxNow(flowNode(page, target));
  if (!z || !t) {
    return false;
  }
  const centred = (a: number, b: number): boolean => Math.abs(a - b) < 15;
  switch (zone) {
    case 'child':
      return z.y >= t.y + t.height - 2 && centred(z.x + z.width / 2, t.x + t.width / 2);
    case 'parent':
      return z.y + z.height <= t.y + 2 && centred(z.x + z.width / 2, t.x + t.width / 2);
    case 'and':
      return z.x >= t.x + t.width - 2;
    case 'or':
      return z.x + z.width <= t.x + 2;
  }
  return false;
}

/**
 * Whether the zones currently drawn are those of the given node.
 *
 * Stricter than zoneBelongsTo(): the zone for "parallel" is drawn at a fixed
 * place relative to its node - 450 canvas units right of the node's left
 * edge, its middle at the height of the node's middle (drawDropzone.ts). The
 * zone itself is 200 units high, which gives the scale. That tells apart the
 * nodes of a column and the nodes of a row, where "to the right of it" does
 * neither.
 *
 * @param page The page.
 * @param target The node the zones should belong to.
 */
export async function zonesAround(page: Page, target: string): Promise<boolean> {
  const z = await boxNow(flowNode(page, 'dropzone_and'));
  const t = await boxNow(flowNode(page, target));
  if (!z || !t) {
    return false;
  }
  const tolerance = Math.max(4, z.height * 0.15);
  const unit = z.height / 200;
  return Math.abs(z.x - t.x - 450 * unit) < tolerance
    && Math.abs(z.y + z.height / 2 - (t.y + t.height / 2)) < tolerance;
}

/**
 * Start dragging a course out of the sidebar. The mouse button stays down;
 * end with page.mouse.up().
 *
 * @param page The page showing the editor.
 * @param shortname The fixture course.
 * @returns The course name as the sidebar shows it.
 */
export async function startDrag(page: Page, shortname: string): Promise<string> {
  await fitCanvas(page);
  const { source, label } = await settledSidebarCourse(page, shortname);
  await source.hover();
  await page.mouse.down();
  return label;
}

/**
 * During a drag: jump with the pointer onto the middle of a node.
 *
 * @param page The page.
 * @param target The node.
 * @param nudge Pixels to the right of the middle - a second jump to the very
 *              same spot would not be a movement.
 */
export async function dragOverNode(page: Page, target: string, nudge = 0): Promise<void> {
  const dst = await boxNow(flowNode(page, target));
  const area = await pane(page).boundingBox();
  expect(dst && area, `node ${target} and the canvas must be on screen`).toBeTruthy();
  await pane(page).hover({
    position: { x: dst!.x + dst!.width / 2 - area!.x + nudge, y: dst!.y + dst!.height / 2 - area!.y },
    force: true,
  });
  await page.waitForTimeout(300);
}

/**
 * During a drag: move the pointer onto a zone until the editor confirms the
 * hit ("Drop to connect").
 *
 * Aims into the VISIBLE part of the zone, re-measured each time. Bounded: a
 * zone that cannot be hit is a defect worth a red test, not a hang.
 *
 * @param page The page.
 * @param zone The zone.
 * @returns Whether the editor registered the pointer on the zone.
 */
export async function dragOntoZone(page: Page, zone: DropZone): Promise<boolean> {
  const zoneLocator = flowNode(page, `dropzone_${zone}`);
  let hit = false;
  for (let attempt = 0; attempt < 10 && !hit; attempt++) {
    const box = await boxNow(zoneLocator);
    const area = await pane(page).boundingBox();
    if (box && area) {
      const top = Math.max(box.y, area.y) + 1;
      const bottom = Math.min(box.y + box.height, area.y + area.height) - 1;
      const left = Math.max(box.x, area.x) + 1;
      const right = Math.min(box.x + box.width, area.x + area.width) - 1;
      if (bottom > top && right > left) {
        await pane(page).hover({
          position: { x: (left + right) / 2 - area.x + (attempt % 2), y: Math.min(top + 30, (top + bottom) / 2) - area.y },
          force: true,
        });
      }
    }
    await page.waitForTimeout(250);
    // With a timeout of its own: a zone that has gone would otherwise be
    // waited for until the test's time is up.
    hit = HIT.test(await zoneLocator.first().innerText({ timeout: 1_000 }).catch(() => ''));
  }
  return hit;
}

/**
 * End a running drag without dropping anything: back to the sidebar, where
 * nothing accepts a drop.
 *
 * @param page The page.
 * @param shortname The fixture course being dragged.
 */
export async function abandonDrag(page: Page, shortname: string): Promise<void> {
  await sidebarCourse(page, shortname).hover({ force: true });
  await page.mouse.up();
  await expect(page.locator('.vue-flow__node[data-id^="dropzone_"]'), 'the zones must go when the drag ends').toHaveCount(0);
}

/**
 * Bring the whole path into view with room to spare.
 *
 * The editor fits the view when it opens and when the canvas resizes, but
 * not after a node has been added - the next target can then sit partly
 * below the visible canvas. The drop zones also extend well beyond their
 * node. So: the editor's own "fit view" button (#480), then zoom out a little
 * further through its zoom slider, so neither the target nor its zones end
 * up at the edge.
 *
 * @param page The page showing the editor.
 */
export async function fitCanvas(page: Page): Promise<void> {
  const fit = app(page).locator('.adele-fit-btn');
  if (await fit.count()) {
    await fit.first().click();
    await page.waitForTimeout(600);
  }
  const slider = app(page).locator('input.adele-zoom-slider');
  if (await slider.count()) {
    await slider.first().evaluate((el: HTMLInputElement) => {
      const next = Math.max(0.05, parseFloat(el.value) * 0.6);
      el.value = String(next);
      el.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await page.waitForTimeout(300);
  }
}

/**
 * The sidebar entry of a course, once the sidebar has stopped moving.
 *
 * The drag helpers grab by position. While the course list is still being
 * built (entries arriving, the list reflowing), the spot measured a moment
 * ago can hold a neighbouring course by the time the mouse goes down - the
 * wrong course then ends up in the path. Stable means: the number of
 * entries and the entry's box are the same on two readings in a row.
 *
 * @param page The page.
 * @param shortname The fixture course.
 * @returns The entry and the course name it shows.
 */
async function settledSidebarCourse(page: Page, shortname: string): Promise<{ source: Locator; label: string }> {
  const source = sidebarCourse(page, shortname);
  await expect(source).toBeVisible({ timeout: 30_000 });
  let previous = '';
  for (let attempt = 0; attempt < 20; attempt++) {
    const count = await page.locator('[data-draggable]').count();
    const box = await source.boundingBox();
    const now = `${count}:${box ? [box.x, box.y, box.width, box.height].map(Math.round).join(',') : '-'}`;
    if (now === previous) {
      break;
    }
    previous = now;
    await page.waitForTimeout(300);
  }
  const label = ((await source.innerText()) || '').split('\n')[0].trim();
  return { source, label };
}

/**
 * Expect the course to have landed in the path - in the given node, or in
 * any node.
 *
 * @param page The page.
 * @param label The course name as the sidebar shows it.
 * @param nodeid The node it must be in, or null for any node.
 */
async function expectCourseInPath(page: Page, label: string, nodeid: string | null): Promise<void> {
  const where = nodeid
    ? app(page).locator(`[data-testid="learningpath-node-${nodeid}"]`)
    : app(page).locator('[data-testid^="learningpath-node-"]').filter({ hasText: label });
  await expect(where.first(), `the dragged course "${label}" must be the one that landed`).toContainText(label, { timeout: 30_000 });
}

/**
 * Drop the first course onto the empty canvas.
 *
 * The starting marker is a real element from the beginning, so the plain
 * HTML5 drag of dragTo() reaches it.
 *
 * @param page The page showing the editor.
 * @param shortname The fixture course.
 */
export async function dropFirstCourse(page: Page, shortname: string): Promise<void> {
  const before = await app(page).locator('[data-testid^="learningpath-node-"]').count();
  const { source, label } = await settledSidebarCourse(page, shortname);
  await source.dragTo(flowNode(page, 'starting_node').first());
  await expect(app(page).locator('[data-testid^="learningpath-node-"]')).toHaveCount(before + 1, { timeout: 30_000 });
  await expectCourseInPath(page, label, null);
}

/**
 * Drag a course next to an existing node and drop it on one of its zones.
 *
 * @param page The page showing the editor.
 * @param shortname The fixture course to drag.
 * @param target The business id of the node to attach to, e.g. "dndnode_1".
 * @param zone The relation.
 * @param via A node to pass on the way, the way a hand coming from the
 *            sidebar passes other nodes: the zones appear there first and
 *            have to follow the pointer to the target (#580).
 */
export async function dropCourseAt(
  page: Page, shortname: string, target: string, zone: DropZone, via: string | null = null
): Promise<void> {
  const nodes = app(page).locator('[data-testid^="learningpath-node-"]');
  const before = await nodes.count();
  const label = await startDrag(page, shortname);

  if (via) {
    await dragOverNode(page, via);
    expect(await zonesAround(page, via), `the zones must first appear at ${via}, the node passed on the way`).toBe(true);
  }

  // The zones are drawn around the node nearest to the pointer and follow it
  // from node to node (#580). The pointer jumps onto the target; that the
  // zones lie around THAT node is checked before anything is dropped. A few
  // attempts, each a little to the side: the editor looks at the pointer
  // only every 100 ms, and a jump to the very same spot is no movement.
  let placed = false;
  for (let attempt = 0; attempt < 6 && !placed; attempt++) {
    await dragOverNode(page, target, attempt);
    placed = (await zoneBelongsTo(page, zone, target)) && (await zonesAround(page, target));
  }
  expect(placed, `the ${zone} zone never appeared next to ${target}`).toBe(true);

  const hit = await dragOntoZone(page, zone);
  expect(hit, `the editor never registered the pointer on the ${zone} zone of ${target}`).toBe(true);
  expect(await zonesAround(page, target), `aiming at the ${zone} zone must not move the zones away from ${target}`).toBe(true);

  await page.mouse.up();
  // The "or" zone adds the course INTO the existing node (it becomes a stack
  // of alternatives); every other zone creates a node of its own.
  const expected = zone === 'or' ? before : before + 1;
  await expect(nodes, `dropping ${shortname} on the ${zone} zone must leave ${expected} node(s)`)
    .toHaveCount(expected, { timeout: 30_000 });
  // The "or" zone adds the course INTO the target node; the others create
  // a node of their own somewhere in the path.
  await expectCourseInPath(page, label, zone === 'or' ? target : null);
}

/**
 * The access criterion "according to predecessor nodes" of one node, as
 * STORED on the server.
 *
 * Read through Moodle's CLI rather than the interface on purpose: the
 * criterion editor (parent_courses.vue) fills in all predecessors whenever
 * it is opened, so what it shows says nothing about what the drop saved.
 *
 * @param title The title of the learning path.
 * @param nodeid The node, e.g. "dndnode_2".
 * @returns courses_id and min_courses, or null when the node has no such criterion.
 */
export function storedPredecessorCriterion(title: string, nodeid: string): { courses_id: string[]; min_courses: number } | null {
  const script = `
    define('CLI_SCRIPT', true);
    require('config.php');
    $path = $DB->get_record('local_adele_learning_paths', ['name' => $argv[1]], 'json', MUST_EXIST);
    foreach (json_decode($path->json, true)['tree']['nodes'] as $node) {
      if ($node['id'] !== $argv[2]) { continue; }
      foreach ($node['restriction']['nodes'] ?? [] as $c) {
        if (($c['data']['label'] ?? '') === 'parent_courses') { echo json_encode($c['data']['value']); exit; }
      }
    }
    echo 'null';`;
  const out = execFileSync('php', ['-r', script, '--', title, nodeid], {
    cwd: fixture('ADELE_MOODLE_ROOT'),
    encoding: 'utf8',
  });
  return JSON.parse(out.trim().split('\n').pop() || 'null');
}
