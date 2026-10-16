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
 * roles-and-collaboration - chain R1 of the E2E plan (section 6).
 *
 *     As an administrator I want to see and manage every learning path,
 *     so that I can administer, repair and take over paths centrally.
 *
 * The whole life cycle of one path, driven through the interface: create,
 * rename, duplicate, delete. Each step is confirmed on screen AND by reloading
 * the page - a card that only exists in the frontend's store until the next
 * reload would pass a check on the first render (plan section 2 F).
 *
 * And the negative control the plan requires for every role story: a
 * learner reaches the same page and gets none of these controls.
 *
 * Logins go through the backend (support/env.ts) and are not part of the
 * recording.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  controlUser,
  fixturePassword,
  referencePath,
  reseedFixtures,
  runLabel,
  saveLearningPath,
  waitForEditorLoaded,
} from '../support/fixtures';

/** The Vue application's mount point. */
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/**
 * Overview cards whose heading is exactly this title.
 *
 * @param page The page showing the overview.
 * @param title The exact title.
 * @returns The matching card roots.
 */
function cards(page: Page, title: string): Locator {
  const exact = new RegExp(`^\\s*${title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*$`);
  return app(page).locator('[data-testid^="learningpath-row-"]')
    .filter({ has: page.locator('h5').filter({ hasText: exact }) });
}

/**
 * Open the overview and wait for the list itself, not for a timer.
 *
 * @param page The page.
 */
async function openOverview(page: Page): Promise<void> {
  await page.goto('/local/adele/index.php');
  await expect(app(page).locator(`[data-testid="learningpath-row-${referencePath.id}"]`))
    .toBeVisible({ timeout: 30_000 });
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-R1 — the administrator manages every learning path', () => {
  test('create, rename, duplicate and delete a path', { tag: ['@story:R1'] }, async ({ page }) => {
    const title = runLabel('E2E R1');
    const renamed = runLabel('E2E R1 umbenannt');

    await loginAs(page, env.adminUser, env.adminPassword);

    await test.step('create a learning path', async () => {
      await openOverview(page);
      await app(page).locator('[data-testid="learningpath-create"]').click();
      await waitForEditorLoaded(page);
      await app(page).locator('[data-testid="learningpath-title"]').fill(title);
      await app(page).locator('[data-testid="learningpath-description"]').fill('Angelegt von der E2E-Kette R1.');
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));

      await expect(cards(page, title)).toHaveCount(1, { timeout: 30_000 });
      await openOverview(page);
      await expect(cards(page, title), 'the new path must survive a reload').toHaveCount(1);
    });

    await test.step('rename it', async () => {
      await cards(page, title).getByRole('button', { name: new RegExp(`^(Edit|Bearbeiten): ${title}$`) })
        .click();
      const field = app(page).locator('[data-testid="learningpath-title"]');
      await expect(field).toHaveValue(title);
      await field.fill(renamed);
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));

      await expect(cards(page, renamed)).toHaveCount(1, { timeout: 30_000 });
      await openOverview(page);
      await expect(cards(page, renamed), 'the new name must survive a reload').toHaveCount(1);
      await expect(cards(page, title), 'the old name must be gone, not duplicated').toHaveCount(0);
    });

    await test.step('duplicate it', async () => {
      await cards(page, renamed)
        .getByRole('button', { name: new RegExp(`^(Duplicate|Duplizieren): ${renamed}$`) })
        .click();

      // local_adele names a copy "<name> copy" (learning_paths.php), which
      // keeps it unique as #492 requires.
      await expect(cards(page, `${renamed} copy`)).toHaveCount(1, { timeout: 30_000 });
      await openOverview(page);
      await expect(cards(page, `${renamed} copy`)).toHaveCount(1);
      await expect(cards(page, renamed), 'duplicating must not touch the original').toHaveCount(1);
    });

    await test.step('delete the original and the copy', async () => {
      for (const name of [`${renamed} copy`, renamed]) {
        const card = cards(page, name);
        await card.getByRole('button', { name: new RegExp(`^(Delete|Löschen): ${name}$`) }).click();
        // The confirmation sits inside the card, so it can only be the one
        // for this path.
        await card.locator('.deletealert .btn-danger').click();
        await expect(cards(page, name)).toHaveCount(0, { timeout: 30_000 });
      }
      await openOverview(page);
      await expect(cards(page, renamed), 'deleted paths must stay deleted after a reload').toHaveCount(0);
      await expect(cards(page, `${renamed} copy`)).toHaveCount(0);
    });
  });

  test('a learner gets none of these controls', { tag: ['@story:R1'] }, async ({ page }) => {
    await loginAs(page, controlUser(), fixturePassword());
    await page.goto('/local/adele/index.php');
    await expect(app(page)).toBeVisible({ timeout: 30_000 });

    // No create control, and no editing control on any path the learner can
    // see. Checked on the controls themselves rather than on a missing text,
    // so a relabelled button cannot make this pass by accident.
    await expect(app(page).locator('[data-testid="learningpath-create"]')).toHaveCount(0);
    await expect(app(page).locator('[data-testid^="learningpath-visibility-toggle-"]')).toHaveCount(0);
    await expect(app(page).getByRole('button', { name: /^(Edit|Bearbeiten|Delete|Löschen|Duplicate|Duplizieren):/ }))
      .toHaveCount(0);
  });
});
