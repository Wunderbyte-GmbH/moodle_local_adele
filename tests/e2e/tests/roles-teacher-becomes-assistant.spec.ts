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
 * roles-teacher-becomes-assistant - chain R3 of the E2E plan (section 6).
 *
 *     no teaching role
 *     -> no editor access
 *     -> enrolled as a teacher in a course
 *     -> editor access, own learning path can be created
 *
 * The mechanism under test is local_adele's own: assigning the COURSE role
 * named in the setting `enrollassistant` grants the system role
 * adeleassistant (enrollment::assign_assistant_to_role). The fixture sets
 * that setting to the editing teacher role; without it the mechanism is off
 * and this chain would prove nothing.
 *
 * The enrolment is done through the participants page, because that is the
 * action the user story describes. The effect is checked by creating a path,
 * not by looking for a button.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import { fixture, fixturePassword, saveLearningPath, waitForEditorLoaded } from '../support/fixtures';

/** The Vue application's mount point. */
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/**
 * Open the learning path overview.
 *
 * @param page The page.
 */
async function openOverview(page: Page): Promise<void> {
  await page.goto('/local/adele/index.php');
  await expect(app(page)).toBeVisible({ timeout: 30_000 });
}

test.describe('ADELE-E2E-R3 — a course teacher becomes an ADELE assistant', () => {
  test('the teaching role opens the editor, and the person can create a path', async ({ page }) => {
    const teacher = fixture('ADELE_FIXTURE_TEACHER');
    const teachername = fixture('ADELE_FIXTURE_TEACHER_NAME');
    const course = fixture('ADELE_FIXTURE_HOST_COURSE');
    const pathname = `Pfad der Lehrkraft ${Date.now()}`;

    await test.step('precondition: without a teaching role there is no editor', async () => {
      await loginAs(page, teacher, fixturePassword());
      await openOverview(page);
      await expect(app(page).locator('[data-testid="learningpath-create"]'),
        'a person with no role anywhere must not be offered the editor').toHaveCount(0);
    });

    await test.step('an administrator enrols them as a teacher', async () => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto(`/user/index.php?id=${course}`);

      await page.getByRole('button', { name: /Enrol users|Nutzer.*einschreiben/i }).first().click();

      // The participant picker is a Moodle autocomplete: type, then pick the
      // entry by name rather than by position.
      const picker = page.getByRole('combobox', { name: /Select users|Nutzer.*auswählen/i }).first();
      await picker.fill(teachername);
      await page.getByRole('option', { name: new RegExp(teachername) }).first().click();

      // The role matters: only the role named in the setting triggers the
      // assistant assignment.
      await page.getByRole('combobox', { name: /Assign role|Rolle zuweisen/i })
        .selectOption({ label: 'Teacher' });

      await page.getByRole('button', { name: /^Enrol users|Nutzer einschreiben$/i }).last().click();
      await expect(page.locator('table#participants tbody tr').filter({ hasText: teachername }))
        .toHaveCount(1, { timeout: 30_000 });
    });

    await test.step('the teacher now reaches the editor and creates their own path', async () => {
      await loginAs(page, teacher, fixturePassword());
      await openOverview(page);

      const create = app(page).locator('[data-testid="learningpath-create"]');
      await expect(create, 'the teaching role must open the editor').toBeVisible({ timeout: 30_000 });

      await create.click();
      await waitForEditorLoaded(page);
      await app(page).locator('[data-testid="learningpath-title"]').fill(pathname);
      await app(page).locator('[data-testid="learningpath-description"]')
        .fill('Angelegt von der E2E-Kette R3.');
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));

      // Reloaded: the point is that the path reached the server, under this
      // person's own account.
      await openOverview(page);
      await expect(app(page).locator('.learningcard h5').filter({ hasText: pathname }))
        .toHaveCount(1, { timeout: 30_000 });
    });

    await test.step('the teacher removes their own path again', async () => {
      const card = app(page).locator('[data-testid^="learningpath-row-"]')
        .filter({ has: page.locator('h5', { hasText: pathname }) });
      await card.getByRole('button', { name: /^(Delete|Löschen):/ }).click();
      await card.locator('.deletealert .btn-danger').click();
      await expect(card).toHaveCount(0, { timeout: 30_000 });
    });
  });
});
