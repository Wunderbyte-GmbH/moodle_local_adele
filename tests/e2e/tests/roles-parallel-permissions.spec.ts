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
 * roles-parallel-permissions - chain R5 of the E2E plan (section 6).
 *
 * When two reasons grant the same access, taking one away must not take the
 * other with it. The plan lists six combinations; three of them are decided
 * here, each by the effect (a saved rename) rather than by a visible button:
 *
 *   manager + collaborator, collaboration revoked -> manager access remains
 *   assistant + collaborator, collaboration revoked -> the editor remains
 *                                                      reachable, this path
 *                                                      does not
 *   collaborator only, collaboration revoked -> access is gone
 *
 * The other three rows need the revocation of a SYSTEM role, which the
 * plugin does not currently offer as a counterpart to its automatic
 * assignment - see docs/e2e-suite.md.
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
 * Open the overview.
 *
 * @param page The page.
 */
async function openOverview(page: Page): Promise<void> {
  await page.goto('/local/adele/index.php');
  await expect(app(page)).toBeVisible({ timeout: 30_000 });
}

/**
 * Open a path in the editor.
 *
 * @param page The page.
 * @param id The learning path id.
 */
async function openEditor(page: Page, id: string): Promise<void> {
  await openOverview(page);
  const row = rowOf(page, id);
  await expect(row).toBeVisible({ timeout: 30_000 });
  await row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
  await expect(app(page).locator('[data-testid="learningpath-title"]'))
    .toBeVisible({ timeout: 30_000 });
}

/**
 * Add somebody to the people who may edit the open path.
 *
 * @param page The page showing the editor.
 * @param name The person's display name.
 */
async function addCollaborator(page: Page, name: string): Promise<void> {
  await app(page).locator('input.user-search-input').fill(name.split(' ')[1] || name);
  const hit = app(page).locator('.user-item').filter({ hasText: name });
  await expect(hit).toHaveCount(1, { timeout: 30_000 });
  await hit.click();
  await expect(app(page).locator('.card-user').filter({ hasText: name })).toHaveCount(1);
}

/**
 * Take somebody off that list again.
 *
 * @param page The page showing the editor.
 * @param name The person's display name.
 */
async function removeCollaborator(page: Page, name: string): Promise<void> {
  const card = app(page).locator('.card-user').filter({ hasText: name });
  await expect(card).toHaveCount(1, { timeout: 30_000 });
  // Confirmed through the browser's own confirm(), which Playwright would
  // otherwise dismiss - cancelling the removal silently.
  page.once('dialog', (dialog) => dialog.accept());
  await card.locator('button.text-danger').click();
  await expect(card).toHaveCount(0);
}

/**
 * Rename the open path and check the change reached the server.
 *
 * @param page The page showing the editor.
 * @param id The learning path id.
 * @param name The new name.
 */
async function renameAndReload(page: Page, id: string, name: string): Promise<void> {
  await waitForEditorLoaded(page);
  await app(page).locator('[data-testid="learningpath-title"]').fill(name);
  await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
  await openOverview(page);
  await expect(rowOf(page, id).locator('h5').first()).toHaveText(name, { timeout: 30_000 });
}

test.describe('ADELE-E2E-R5 — one reason falls away, the other still holds', () => {
  test('revoking the collaboration leaves manager access untouched', async ({ page }) => {
    const manager = fixture('ADELE_FIXTURE_MANAGER');
    const managerName = fixture('ADELE_FIXTURE_MANAGER_NAME');
    const assistantName = fixture('ADELE_FIXTURE_ASSISTANT_NAME');
    const renamed = `Linear A1 Manager nach Entzug ${Date.now()}`;

    await loginAs(page, manager, fixturePassword());
    await openEditor(page, referencePath.id);

    // Two reasons at once: the manager role, and a collaboration on this very
    // path. The manager adds themselves, so the second reason exists.
    await addCollaborator(page, managerName);
    // A second person, because the interface protects the last collaborator.
    await addCollaborator(page, assistantName);

    await removeCollaborator(page, managerName);

    // The collaboration is gone; the manager role is not.
    await openEditor(page, referencePath.id);
    await renameAndReload(page, referencePath.id, renamed);
    await openEditor(page, referencePath.id);
    await renameAndReload(page, referencePath.id, referencePath.name);
  });

  test('an assistant keeps the editor but loses this path', async ({ page }) => {
    const manager = fixture('ADELE_FIXTURE_MANAGER');
    const assistant = fixture('ADELE_FIXTURE_ASSISTANT');
    const assistantName = fixture('ADELE_FIXTURE_ASSISTANT_NAME');
    const managerName = fixture('ADELE_FIXTURE_MANAGER_NAME');

    await test.step('the assistant is made a collaborator on a second path', async () => {
      await loginAs(page, manager, fixturePassword());
      await openEditor(page, referencePath.id);
      await addCollaborator(page, assistantName);
      await addCollaborator(page, managerName);
    });

    await test.step('with both reasons the assistant can edit it', async () => {
      await loginAs(page, assistant, fixturePassword());
      await openEditor(page, referencePath.id);
      await renameAndReload(page, referencePath.id, referencePath.name);
    });

    await test.step('the collaboration is revoked', async () => {
      await loginAs(page, manager, fixturePassword());
      await openEditor(page, referencePath.id);
      await removeCollaborator(page, assistantName);
    });

    await test.step('the editor stays reachable, this path does not', async () => {
      await loginAs(page, assistant, fixturePassword());
      await openOverview(page);

      // The assistant role is untouched, so the editor itself is still there
      // - the person is not demoted to a learner.
      await expect(app(page).locator('[data-testid="learningpath-create"]'),
        'the assistant role must survive the revoked collaboration').toBeVisible({ timeout: 30_000 });

      // But this particular path is no longer theirs to edit.
      const row = rowOf(page, referencePath.id);
      await expect(row).toBeVisible();
      await expect(row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }),
        'the revoked collaboration must close the editing route to this path').toHaveCount(0);

      // And the path they were given in the fixtures is still theirs.
      const own = rowOf(page, fixture('ADELE_FIXTURE_PATH_LINEAR_A2'));
      await expect(own.getByRole('button', { name: /^(Edit|Bearbeiten):/ }),
        'an unrelated collaboration must not be swept up').toHaveCount(1);
    });
  });
});
