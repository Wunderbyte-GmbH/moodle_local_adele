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
 * editor-title-overwrite - local_adele #585: the editor keeps what was typed.
 *
 * Opening a path for editing from the overview used to load it twice
 * (LearningPathList.editLearningpath, then LearningpathsEdit.showForm). The
 * title field was usable after the first answer; when the second arrived
 * later, it overwrote what had been typed - and "Save" then stored the OLD
 * name, without any message. Found through the role chains, which failed at
 * random under load.
 *
 * Since the fix the path is loaded ONCE, by the editor, and the form is
 * offered only when that answer has arrived. Both halves are pinned here:
 * the single request, and - with that one answer held back like a slow
 * network would - no field to type into before it, and no overwriting after.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';
import { fixture, reseedFixtures } from '../support/fixtures';
import { ABSENCE_WINDOW_MS } from '../support/timing';

const LOAD = /info=local_adele_get_learningpath(&|$)/;

/**
 * As admin: the overview, and the "Edit" button of the fixture path.
 *
 * @param page The page.
 */
async function editButton(page: Page) {
  await loginAs(page, env.adminUser, env.adminPassword);
  await page.goto('/local/adele/index.php');
  const app = page.locator('[id^="local-adele-app"]');
  const button = app.locator(`[data-testid="learningpath-row-${fixture('ADELE_FIXTURE_PATH_LINEAR_A2')}"]`)
    .getByRole('button', { name: /^(Edit|Bearbeiten):/ });
  await expect(button).toBeVisible({ timeout: 30_000 });
  return { app, button };
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-E — the editor keeps what was typed (#585)', () => {
  test('opening a path for editing loads it exactly once', { tag: ['@story:E1'] }, async ({ page }) => {
    let loads = 0;
    page.on('request', (request) => {
      if (LOAD.test(request.url())) {
        loads++;
      }
    });
    const { app, button } = await editButton(page);
    await button.click();
    await expect(app.locator('[data-testid="learningpath-title"]')).toHaveValue('Linear A2', { timeout: 30_000 });
    await page.waitForLoadState('networkidle');
    expect(loads, 'requests for the path while the editor opens').toBe(1);
  });

  test('the title is offered when the path has arrived, and stays as typed', { tag: ['@story:E1'] }, async ({ page }) => {
    let release: () => void = () => undefined;
    const held = new Promise<void>((resolve) => { release = resolve; });
    let loads = 0;
    await page.route(LOAD, async (route) => {
      loads++;
      // A slow network: the answer is held back until the test lets it go.
      await held;
      await route.continue();
    });
    const { app, button } = await editButton(page);
    await button.click();
    const title = app.locator('[data-testid="learningpath-title"]');

    await test.step('while the path is on its way there is no field to type into', async () => {
      await expect.poll(() => loads, { timeout: 30_000 }).toBe(1);
      // Absence has no event: the field must stay away for a while.
      await page.waitForTimeout(ABSENCE_WINDOW_MS);
      await expect(title).toHaveCount(0);
    });

    await test.step('once it has arrived, what is typed stays', async () => {
      release();
      await expect(title).toHaveValue('Linear A2', { timeout: 30_000 });
      await title.fill('Neuer Name');
      // Whatever could still overwrite the field comes over the network:
      // wait until nothing has been in flight for half a second.
      await page.waitForLoadState('networkidle');
      await expect(title, 'nothing may overwrite what the person typed').toHaveValue('Neuer Name');
      expect(loads, 'and no second load was sent after it').toBe(1);
    });
  });
});
