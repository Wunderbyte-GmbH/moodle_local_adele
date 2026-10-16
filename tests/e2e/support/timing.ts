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
 * The time model of the editors, in one place.
 *
 * The E2E issue (section 3B) forbids blanket waits for business states:
 * those are awaited by their DOM reaction or response. What remains are
 * pauses the editors themselves dictate, because they look at the pointer
 * or accept input only at intervals. Each pause here names the product
 * behaviour it follows; a pause without such a reason does not belong in
 * the suite.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { expect, Locator, Page } from '@playwright/test';

/**
 * The path editor's sidebar looks at the drag at most every 100 ms
 * (vue3/components/flowchart/SidebarPath.vue: throttle(handleDrag, 100)).
 * One step of a guided pointer has to outlast one look.
 */
export const POINTER_STEP_MS = 140;

/**
 * A pointer that has arrived: two looks of the editor plus the re-render of
 * the zones it draws in answer.
 */
export const POINTER_REST_MS = 300;

/**
 * After a zoom step the criteria editors ignore the wheel for 500 ms
 * (RestrictionFlow.vue / CompletionFlow.vue: zoomLock, setTimeout 500).
 */
export const ZOOM_LOCK_MS = 550;

/**
 * How long something must stay ABSENT to count as absent. Absence has no
 * event to wait for, so a fixed window is the only honest check; it is used
 * only where a response is deliberately held back by the test (#585).
 */
export const ABSENCE_WINDOW_MS = 1_500;

/**
 * Pause for the editor, by one of the intervals above.
 *
 * @param page The page.
 * @param ms One of the constants above.
 */
export async function editorPause(page: Page, ms: number = POINTER_STEP_MS): Promise<void> {
  await page.waitForTimeout(ms);
}

/**
 * The transform of a vue-flow canvas right now.
 *
 * @param scope The element holding the canvas.
 */
export async function viewportTransform(scope: Locator): Promise<string> {
  // vue-flow writes the transform onto the transformation pane, not onto
  // .vue-flow__viewport (measured: that one carries no style at all).
  return (await scope.locator('.vue-flow__transformationpane').first().getAttribute('style')) || '';
}

/**
 * Wait until the canvas has stopped moving: three readings, 100 ms apart,
 * agree. Replaces fixed waits for fit and zoom animations.
 *
 * @param scope The element holding the canvas.
 * @param timeout Upper bound.
 */
export async function viewportAtRest(scope: Locator, timeout = 10_000): Promise<void> {
  let last = '';
  let same = 0;
  await expect.poll(async () => {
    const now = await viewportTransform(scope);
    same = now === last ? same + 1 : 0;
    last = now;
    return same;
  }, { message: 'the canvas must come to rest', timeout, intervals: [100] }).toBeGreaterThanOrEqual(2);
}

/**
 * Wait until the canvas has moved away from a transform read earlier.
 *
 * @param scope The element holding the canvas.
 * @param from The transform before.
 * @param timeout Upper bound.
 * @returns Whether it moved within the bound.
 */
export async function viewportMoved(scope: Locator, from: string, timeout: number): Promise<boolean> {
  return expect.poll(() => viewportTransform(scope), { timeout, intervals: [50] }).not.toBe(from)
    .then(() => true, () => false);
}
