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
 * editor-title-overwrite - a product defect, kept visible until it is fixed.
 *
 * Opening a path for editing from the overview loads it twice
 * (LearningPathList.editLearningpath, then LearningpathsEdit.showForm). The
 * title field is already editable after the first answer; when the second
 * answer arrives later, it overwrites what was typed - and "Save" then stores
 * the OLD name, without any message.
 *
 * Reproduced deterministically by delaying the second answer. Found through
 * the role chains, which failed at random under load. Issue draft:
 * enrol_adele/docs/issues/local_adele-issue-editor-title-overwritten.md.
 *
 * Turn the fixme into a test once fixed: it must then pass as it stands.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect } from '@playwright/test';
import { env, loginAs } from '../support/env';
import { fixture } from '../support/fixtures';

test.describe('ADELE-E2E-E — the editor keeps what was typed', () => {
  test.fixme('a late second load must not overwrite the typed title', async ({ page }) => {
    let loads = 0;
    await page.route(/info=local_adele_get_learningpath(&|$)/, async (route) => {
      loads++;
      if (loads === 2) {
        // A slow network: the second answer arrives after the person typed.
        await new Promise((resolve) => setTimeout(resolve, 2500));
      }
      await route.continue();
    });
    await loginAs(page, env.adminUser, env.adminPassword);
    await page.goto('/local/adele/index.php');
    const app = page.locator('[id^="local-adele-app"]');
    await app.locator(`[data-testid="learningpath-row-${fixture('ADELE_FIXTURE_PATH_LINEAR_A2')}"]`)
      .getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
    const title = app.locator('[data-testid="learningpath-title"]');
    await expect(title).toBeVisible({ timeout: 30_000 });
    await title.fill('Neuer Name');
    await page.waitForTimeout(4_000);
    await expect(title, 'what the person typed must survive the late load').toHaveValue('Neuer Name');
  });
});
