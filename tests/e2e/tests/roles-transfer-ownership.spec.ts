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
 * roles-transfer-ownership - chain R6 of the E2E plan (section 6).
 *
 *     a manager hands a learning path to a new owner
 *     -> the path names that person as its owner, now and after reloading
 *     -> the new owner can still work on it
 *     -> the previous owner, a manager, keeps full access
 *
 * The transfer is the crown control in the editor, which only a manager sees
 * and only on cards that are not already the owner. Checked on the stored
 * state, not on the optimistic update the frontend does right after the
 * click: the component writes the new owner into its own store immediately,
 * so a check without reloading would pass even if nothing had been saved.
 *
 * Ownership is the path's createdby field, and the fixture seed writes it
 * back to the admin account on every run - so this chain needs no cleanup of
 * its own.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { loginAs } from '../support/env';
import { fixture, fixturePassword, saveLearningPath, waitForEditorLoaded } from '../support/fixtures';

/** The path the assistant may edit, and whose ownership is handed over here. */
const path = {
  get id() { return fixture('ADELE_FIXTURE_PATH_LINEAR_A2'); },
  name: 'Linear A2',
};

/** The Vue application's mount point. */
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/**
 * Open a learning path in the editor, starting from the overview.
 *
 * @param page The page.
 * @param id The learning path id.
 */
async function openEditor(page: Page, id: string): Promise<void> {
  await page.goto('/local/adele/index.php');
  const row = app(page).locator(`[data-testid="learningpath-row-${id}"]`);
  await expect(row).toBeVisible({ timeout: 30_000 });
  await row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
  await expect(app(page).locator('[data-testid="learningpath-title"]'))
    .toBeVisible({ timeout: 30_000 });
}

/** The card of one person among those who may edit this path. */
const cardOf = (page: Page, name: string): Locator =>
  app(page).locator('.card-user').filter({ hasText: name });

/** The line naming the current owner. */
const ownerLine = (page: Page): Locator => app(page).locator('.owner-label');

test.describe('ADELE-E2E-R6 — a learning path is handed to a new owner', () => {
  test('the manager transfers ownership, and it sticks', async ({ page }) => {
    const manager = fixture('ADELE_FIXTURE_MANAGER');
    const assistant = fixture('ADELE_FIXTURE_ASSISTANT');
    const assistantName = fixture('ADELE_FIXTURE_ASSISTANT_NAME');
    const renamed = `${path.name} nach Eigentumswechsel ${Date.now()}`;

    await test.step('precondition: the assistant is an editor but not the owner', async () => {
      await loginAs(page, manager, fixturePassword());
      await openEditor(page, path.id);

      const card = cardOf(page, assistantName);
      await expect(card).toHaveCount(1, { timeout: 30_000 });
      await expect(card.locator('.owner-crown'),
        'the assistant must not already wear the crown').toHaveCount(0);
      await expect(card.locator('.setowner-btn'),
        'a manager must be offered the transfer').toHaveCount(1);
      await expect(ownerLine(page)).not.toContainText(assistantName);
    });

    await test.step('the manager hands the path over', async () => {
      // The transfer asks through the browser's own confirm(); Playwright
      // dismisses that unless something accepts it, and a dismissed confirm
      // cancels the transfer without a word.
      page.once('dialog', (dialog) => dialog.accept());
      await cardOf(page, assistantName).locator('.setowner-btn').click();

      await expect(ownerLine(page)).toContainText(assistantName);
    });

    await test.step('the transfer survives reopening the editor', async () => {
      await openEditor(page, path.id);

      await expect(ownerLine(page), 'the new owner must come back from the server')
        .toContainText(assistantName);
      const card = cardOf(page, assistantName);
      await expect(card.locator('.owner-crown')).toHaveCount(1);
      await expect(card.locator('.setowner-btn'),
        'the owner cannot be made owner again').toHaveCount(0);
    });

    await test.step('the new owner can still work on the path', async () => {
      await loginAs(page, assistant, fixturePassword());
      await openEditor(page, path.id);
      await waitForEditorLoaded(page);
      await app(page).locator('[data-testid="learningpath-title"]').fill(renamed);
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));

      await page.goto('/local/adele/index.php');
      await expect(app(page).locator(`[data-testid="learningpath-row-${path.id}"]`).locator('h5').first())
        .toHaveText(renamed, { timeout: 30_000 });
    });

    await test.step('the previous owner, a manager, keeps full access', async () => {
      // Plan variant "alter Eigentümer als Manager": handing the path away
      // must not lock the manager out of it.
      await loginAs(page, manager, fixturePassword());
      await openEditor(page, path.id);
      await waitForEditorLoaded(page);
      await app(page).locator('[data-testid="learningpath-title"]').fill(path.name);
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));

      await page.goto('/local/adele/index.php');
      await expect(app(page).locator(`[data-testid="learningpath-row-${path.id}"]`).locator('h5').first())
        .toHaveText(path.name, { timeout: 30_000 });
    });
  });
});
