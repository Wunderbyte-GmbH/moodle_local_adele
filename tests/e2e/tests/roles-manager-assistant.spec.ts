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
 * roles-manager-assistant - chains R2 and R4 of the E2E plan (section 6).
 *
 * R2: an ADELE manager sees and edits every learning path, including ones
 *     somebody else created.
 * R4a: a collaborator edits exactly the paths they were made an editor of -
 *      and no others.
 *
 * Both are checked on the EFFECT, not on the menu: the test changes the path
 * and reloads. A visible edit button proves nothing if the save is refused,
 * and a missing one proves nothing if the route still works.
 *
 * The fixtures give the assistant an editor assignment on exactly one path
 * (Linear A2) through local_adele's own API, so the pair "may edit" and
 * "may not edit" exists for the same person at the same moment.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { loginAs } from '../support/env';
import { fixture, fixturePassword, referencePath, saveLearningPath, waitForEditorLoaded } from '../support/fixtures';

/** The path the assistant was made an editor of. */
const editablePath = {
  get id() { return fixture('ADELE_FIXTURE_PATH_LINEAR_A2'); },
  name: 'Linear A2',
};

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
 * Rename a path through the editor and confirm it stuck.
 *
 * @param page The page, authenticated as the person under test.
 * @param id The learning path id.
 * @param name The new name.
 */
async function renameThroughEditor(page: Page, id: string, name: string): Promise<void> {
  const row = rowOf(page, id);
  await expect(row).toBeVisible({ timeout: 30_000 });
  await row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();

  const title = app(page).locator('[data-testid="learningpath-title"]');
  await expect(title).toBeVisible({ timeout: 30_000 });
  await waitForEditorLoaded(page);
  await title.fill(name);
  await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));

  // Reload rather than trust the rendered list: the point is that the change
  // reached the server.
  await openOverview(page);
  // As a string, not a regular expression: the names carry brackets, and as a
  // pattern those would be groups rather than characters - the check would
  // then pass on text that merely looks similar.
  await expect(rowOf(page, id).locator('h5').first()).toHaveText(name, { timeout: 30_000 });
}

test.describe('ADELE-E2E-R2 — the manager administers paths they did not create', () => {
  test('the manager renames a path created by somebody else', { tag: ['@story:R2'] }, async ({ page }) => {
    const manager = fixture('ADELE_FIXTURE_MANAGER');
    const renamed = `Linear A1 vom Manager ${Date.now()}`;

    await loginAs(page, manager, fixturePassword());
    await openOverview(page);

    // The fixture paths belong to the admin account; the manager has never
    // touched them.
    await expect(rowOf(page, referencePath.id),
      'a manager must see paths created by others').toBeVisible({ timeout: 30_000 });

    try {
      await renameThroughEditor(page, referencePath.id, renamed);
    } finally {
      // Put the fixture name back. The seed matches paths BY NAME, so a path
      // left under a test name is re-imported as a second copy on the next
      // run, and the duplicates pile up until an assertion about "exactly
      // one" breaks for a reason nobody will look for here.
      await renameThroughEditor(page, referencePath.id, referencePath.name);
    }
  });

  test('a learner sees no learning path administration at all', { tag: ['@story:R2'] }, async ({ page }) => {
    await loginAs(page, referencePath.learner, fixturePassword());
    await openOverview(page);

    await expect(app(page).locator('[data-testid="learningpath-create"]')).toHaveCount(0);
    await expect(app(page).getByRole('button', { name: /^(Edit|Bearbeiten):/ })).toHaveCount(0);
  });
});

test.describe('ADELE-E2E-R4a — the collaborator edits only what they were given', () => {
  test('the assistant renames the path they are an editor of', { tag: ['@story:R4'] }, async ({ page }) => {
    const assistant = fixture('ADELE_FIXTURE_ASSISTANT');
    const renamed = `Linear A2 vom Assistenten ${Date.now()}`;

    await loginAs(page, assistant, fixturePassword());
    await openOverview(page);
    try {
      await renameThroughEditor(page, editablePath.id, renamed);
    } finally {
      await renameThroughEditor(page, editablePath.id, editablePath.name);
    }
  });

  test('the assistant gets no editing route to the other path', { tag: ['@story:R4'] }, async ({ page }) => {
    const assistant = fixture('ADELE_FIXTURE_ASSISTANT');

    await loginAs(page, assistant, fixturePassword());
    await openOverview(page);

    // Wait for a path they DO have before asserting an absence; otherwise the
    // assertion passes while the list is still loading - the classic way an
    // absence test becomes worthless.
    await expect(rowOf(page, editablePath.id)).toBeVisible({ timeout: 30_000 });

    // The other path IS on screen - the assistant may look at it - but it
    // offers viewing only. Asserted in both directions, so neither a
    // disappearing card nor an appearing edit button can pass unnoticed.
    const other = rowOf(page, referencePath.id);
    await expect(other, 'a visible path stays visible to an assistant').toBeVisible();
    await expect(other.getByRole('button', { name: /^(Edit|Bearbeiten):/ }),
      'a path without an editor assignment must offer this assistant no edit control')
      .toHaveCount(0);
    await expect(other.getByRole('button', { name: /^(View|Ansehen):/ }),
      'and it must still offer the viewing route').toHaveCount(1);
  });
});
