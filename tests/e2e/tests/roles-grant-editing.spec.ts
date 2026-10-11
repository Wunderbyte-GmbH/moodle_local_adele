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
 * roles-grant-editing - chain R4 of the E2E plan (section 6), the granting
 * half; R4a covers an assignment that already exists.
 *
 *     a manager hands editing rights for one path to somebody else
 *     -> that person can edit exactly that path
 *     -> the right is taken back
 *     -> they cannot any more
 *
 * R4a proves that an existing assignment works; this proves that GRANTING it
 * works, through the search field in the editor rather than through the API
 * the fixtures use. Both halves are asserted on the effect - a saved rename,
 * not a visible button.
 *
 * The chain gives the right back at the end, because the fixtures say the
 * assistant edits exactly one path and the next run has to start there. The
 * manager stays behind as an editor - the interface refuses to remove the
 * last one - so the seed clears the editor table of the fixture paths on
 * every run.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { loginAs } from '../support/env';
import { fixture, fixturePassword, referencePath, saveLearningPath, waitForEditorLoaded } from '../support/fixtures';

/** The Vue application's mount point. */
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/** The overview row of one learning path. */
const rowOf = (page: Page, id: string): Locator =>
  app(page).locator(`[data-testid="learningpath-row-${id}"]`);

/**
 * Open the overview and wait for the application, not for a timer.
 *
 * @param page The page.
 */
async function openOverview(page: Page): Promise<void> {
  await page.goto('/local/adele/index.php');
  await expect(app(page)).toBeVisible({ timeout: 30_000 });
}

/**
 * Open the editor of a path from the overview.
 *
 * @param page The page.
 * @param id The learning path id.
 */
async function openEditor(page: Page, id: string): Promise<void> {
  const row = rowOf(page, id);
  await expect(row).toBeVisible({ timeout: 30_000 });
  await row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
  await expect(app(page).locator('[data-testid="learningpath-title"]'))
    .toBeVisible({ timeout: 30_000 });
}

/** The editor's search field for people who may edit this path. */
const editorSearch = (page: Page): Locator => app(page).locator('input.user-search-input');

/** The cards of the people who may edit this path. */
const editorCards = (page: Page): Locator => app(page).locator('.card-user');

test.describe('ADELE-E2E-R4b — editing rights are handed over and taken back', () => {
  test('granting lets the assistant edit, revoking stops them again', async ({ page }) => {
    const manager = fixture('ADELE_FIXTURE_MANAGER');
    const assistant = fixture('ADELE_FIXTURE_ASSISTANT');
    const assistantName = fixture('ADELE_FIXTURE_ASSISTANT_NAME');
    const managerName = fixture('ADELE_FIXTURE_MANAGER_NAME');
    const renamed = `Linear A1 vom Assistenten ${Date.now()}`;

    await test.step('precondition: the assistant may not edit this path', async () => {
      await loginAs(page, assistant, fixturePassword());
      await openOverview(page);
      const row = rowOf(page, referencePath.id);
      await expect(row).toBeVisible({ timeout: 30_000 });
      await expect(row.getByRole('button', { name: /^(Edit|Bearbeiten):/ })).toHaveCount(0);
    });

    await test.step('the manager adds the assistant as an editor', async () => {
      await loginAs(page, manager, fixturePassword());
      await openOverview(page);
      await openEditor(page, referencePath.id);

      // Typed, then picked from the result list - the way a person does it.
      // The field searches with a delay, so the result is awaited rather than
      // assumed.
      await editorSearch(page).fill(assistantName.split(' ')[1] || assistantName);
      const hit = app(page).locator('.user-item').filter({ hasText: assistantName });
      await expect(hit).toHaveCount(1, { timeout: 30_000 });
      await hit.click();

      await expect(editorCards(page).filter({ hasText: assistantName }),
        'the new editor must appear among the people who may edit this path')
        .toHaveCount(1);
    });

    await test.step('the assistant can now rename that path', async () => {
      await loginAs(page, assistant, fixturePassword());
      await openOverview(page);
      await openEditor(page, referencePath.id);

      const title = app(page).locator('[data-testid="learningpath-title"]');
      await waitForEditorLoaded(page);
      await title.fill(renamed);
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));

      // Reloaded, because the point is that the change reached the server.
      await openOverview(page);
      await expect(rowOf(page, referencePath.id).locator('h5').first())
        .toHaveText(renamed, { timeout: 30_000 });
    });

    await test.step('the assistant puts the fixture name back', async () => {
      await openEditor(page, referencePath.id);
      await waitForEditorLoaded(page);
      await app(page).locator('[data-testid="learningpath-title"]').fill(referencePath.name);
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
      await openOverview(page);
      await expect(rowOf(page, referencePath.id).locator('h5').first())
        .toHaveText(referencePath.name, { timeout: 30_000 });
    });

    await test.step('the only editor cannot be removed', async () => {
      await loginAs(page, manager, fixturePassword());
      await openOverview(page);
      await openEditor(page, referencePath.id);

      const card = editorCards(page).filter({ hasText: assistantName });
      await expect(card).toHaveCount(1, { timeout: 30_000 });
      // By design: a path must not end up with nobody who may edit it, so
      // the remove control only appears once a second person is listed.
      await expect(card.locator('button.text-danger'),
        'the last remaining editor must not be removable').toHaveCount(0);
    });

    await test.step('the manager adds themselves and then takes the right back', async () => {
      await editorSearch(page).fill(managerName.split(' ')[1] || managerName);
      const hit = app(page).locator('.user-item').filter({ hasText: managerName });
      await expect(hit).toHaveCount(1, { timeout: 30_000 });
      await hit.click();
      await expect(editorCards(page)).toHaveCount(2);

      const card = editorCards(page).filter({ hasText: assistantName });
      // Removing asks through the browser's own confirm(), which Playwright
      // dismisses unless something accepts it - and a dismissed confirm
      // cancels the removal without a word.
      page.once('dialog', (dialog) => dialog.accept());
      await card.locator('button.text-danger').click();
      await expect(card).toHaveCount(0);
    });

    await test.step('and the assistant is locked out again', async () => {
      await loginAs(page, assistant, fixturePassword());
      await openOverview(page);
      const row = rowOf(page, referencePath.id);
      await expect(row).toBeVisible({ timeout: 30_000 });
      await expect(row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }),
        'a revoked right must close the editing route again').toHaveCount(0);
    });
  });
});
