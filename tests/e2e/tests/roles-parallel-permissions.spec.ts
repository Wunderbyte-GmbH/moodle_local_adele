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
 *   assistant + collaborator, assistant revoked -> the collaboration still
 *                                                  opens its path
 *   collaborator only, collaboration revoked -> nothing is left
 *   owner + collaborator, collaboration revoked -> cannot arise: the owner
 *                                                  always stays on the list
 *                                                  (decision 2026-10-08)
 *
 * "assistant only, assistant revoked" is decided at the end of
 * roles-teacher-becomes-assistant.
 *
 * The assistant role is never revoked by the plugin (decision on
 * local_adele #503/#509); "revoked" means an administrator takes the system
 * role away on Moodle's own page.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  callService, fixture, fixturePassword, referencePath, saveLearningPath, setSystemRole, waitForEditorLoaded,
} from '../support/fixtures';

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

  test('without the assistant role the collaboration still opens its path - and without that, nothing is left', async ({ page }) => {
    const manager = fixture('ADELE_FIXTURE_MANAGER');
    const managerName = fixture('ADELE_FIXTURE_MANAGER_NAME');
    const assistant = fixture('ADELE_FIXTURE_ASSISTANT');
    const assistantName = fixture('ADELE_FIXTURE_ASSISTANT_NAME');
    const own = fixture('ADELE_FIXTURE_PATH_LINEAR_A2');
    const renamed = `Linear A2 ohne Assistentenrolle ${Date.now()}`;
    /** What the server answers when the person asks for the editors of their path. */
    const editorsOfOwn = (): Promise<{ data?: unknown; error?: string }> =>
      callService(page, 'local_adele_get_lp_edit_users', { contextid: 1, lpid: Number(own) });

    await test.step('an administrator takes the system role away by hand', async () => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await setSystemRole(page, 'Adele Assistant', assistantName, false);
    });

    try {
      await test.step('collaborator only: no new paths, no foreign paths - but the path of the collaboration', async () => {
        await loginAs(page, assistant, fixturePassword());
        await openOverview(page);
        await expect(rowOf(page, own)).toBeVisible({ timeout: 30_000 });
        await expect(app(page).locator('[data-testid="learningpath-create"]'),
          'creating paths came with the role and goes with it').toHaveCount(0);
        await expect(rowOf(page, referencePath.id),
          'a path the person does not collaborate on is no longer listed').toHaveCount(0);

        await openEditor(page, own);
        await renameAndReload(page, own, renamed);
        await openEditor(page, own);
        await renameAndReload(page, own, 'Linear A2');
        expect((await editorsOfOwn()).error, 'the server answers a collaborator').toBeUndefined();
      });

      await test.step('the collaboration is revoked as well', async () => {
        await loginAs(page, manager, fixturePassword());
        await openEditor(page, own);
        // A second person first: the interface protects the last editor.
        await addCollaborator(page, managerName);
        await removeCollaborator(page, assistantName);
      });

      await test.step('nothing is left of it: the path is not listed, and the server refuses', async () => {
        await loginAs(page, assistant, fixturePassword());
        await openOverview(page);
        await expect(app(page).locator('[data-testid="learningpath-create"]')).toHaveCount(0);
        // Given time to arrive: an empty list and a list not yet loaded look alike.
        await page.waitForLoadState('networkidle');
        await expect(rowOf(page, own),
          'with neither role nor collaboration the path is not listed any more').toHaveCount(0);
        await expect(rowOf(page, referencePath.id)).toHaveCount(0);
        expect((await editorsOfOwn()).error, 'the server must refuse for lack of permission').toBe('nopermissions');
      });
    } finally {
      // The other chains rely on this person being an assistant and the one
      // editor of Linear A2.
      await test.step('role and collaboration are given back', async () => {
        await loginAs(page, env.adminUser, env.adminPassword);
        await setSystemRole(page, 'Adele Assistant', assistantName, true);
        await openEditor(page, own);
        const cards = app(page).locator('.card-user');
        await expect(cards.first()).toBeVisible({ timeout: 30_000 });
        if ((await cards.filter({ hasText: assistantName }).count()) === 0) {
          await addCollaborator(page, assistantName);
        }
        if ((await cards.filter({ hasText: managerName }).count()) > 0) {
          await removeCollaborator(page, managerName);
        }
        await expect(cards.filter({ hasText: assistantName })).toHaveCount(1);
        await expect(cards.filter({ hasText: managerName })).toHaveCount(0);
      });
    }
  });

  // Decision 2026-10-08 ("Regel B"): the owner always stands on the list of
  // editors. Taking them off is refused - in the interface and on the server
  // - so the row "owner + collaborator, collaboration revoked" cannot arise:
  // the owner keeps the path. (Before, the owner could take themselves off
  // and lost their own path - found with this chain.)
  test('the owner cannot be taken off the list of editors and keeps the path', async ({ page }) => {
    const assistant = fixture('ADELE_FIXTURE_ASSISTANT');
    const assistantName = fixture('ADELE_FIXTURE_ASSISTANT_NAME');
    const managerName = fixture('ADELE_FIXTURE_MANAGER_NAME');
    const title = `R5 Eigentum ${Date.now()}`;
    const renamed = `${title} umbenannt`;
    const card = (name: string): Locator => app(page).locator('[data-testid^="learningpath-row-"]')
      .filter({ has: page.locator('h5', { hasText: name }) });
    const openOwn = async (name: string): Promise<void> => {
      await openOverview(page);
      await expect(card(name), `the owner must still be shown "${name}"`).toHaveCount(1, { timeout: 30_000 });
      await card(name).getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
      await waitForEditorLoaded(page);
    };
    page.on('dialog', (dialog) => dialog.accept());

    try {
    await test.step('an assistant creates a path: owner, and first on its list of editors', async () => {
      await loginAs(page, assistant, fixturePassword());
      await openOverview(page);
      await app(page).locator('[data-testid="learningpath-create"]').click();
      await waitForEditorLoaded(page);
      await app(page).locator('[data-testid="learningpath-title"]').fill(title);
      await app(page).locator('[data-testid="learningpath-description"]').fill('Angelegt von der E2E-Kette R5.');
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
      await openOwn(title);
      await expect(app(page).locator('.card-user').filter({ hasText: assistantName }),
        'whoever creates a path is put on its list of editors').toHaveCount(1, { timeout: 30_000 });
    });

    await test.step('a second editor joins; the owner cannot be taken off', async () => {
      await addCollaborator(page, managerName);
      const own = app(page).locator('.card-user').filter({ hasText: assistantName });
      const other = app(page).locator('.card-user').filter({ hasText: managerName });
      await expect(other.locator('button.text-danger'), 'the other editor can be removed').toHaveCount(1);
      await expect(own.locator('button.text-danger'), 'the owner card offers no removal').toHaveCount(0);
    });

    await test.step('the server refuses to take the owner off', async () => {
      await openOverview(page);
      const rowid = (await card(title).getAttribute('data-testid'))!.replace('learningpath-row-', '');
      const ownerid = await page.evaluate(() => (window as unknown as { M: { cfg: { userId?: number } } }).M.cfg.userId ?? 0);
      const answer = await callService(page, 'local_adele_remove_lp_edit_users', {
        contextid: 1, lpid: Number(rowid), userid: Number(ownerid),
      });
      expect(answer.error, 'removing the owner must be refused').toBe('editorremoveowner');
      await openOwn(title);
      await expect(app(page).locator('.card-user').filter({ hasText: assistantName }), 'still on the list').toHaveCount(1);
    });

    await test.step('the path is still the owner\'s to edit', async () => {
      await openOwn(title);
      await app(page).locator('[data-testid="learningpath-title"]').fill(renamed);
      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
      await openOverview(page);
      await expect(card(renamed), 'the rename must have reached the server').toHaveCount(1, { timeout: 30_000 });
    });

    await test.step('and the owner\'s to delete', async () => {
      await card(renamed).getByRole('button', { name: /^(Delete|Löschen):/ }).click();
      await card(renamed).locator('.deletealert .btn-danger').click();
      await expect(card(renamed)).toHaveCount(0, { timeout: 30_000 });
    });
    } finally {
      // Whatever became of it: a manager sees every path and clears it away.
      await loginAs(page, fixture('ADELE_FIXTURE_MANAGER'), fixturePassword());
      await openOverview(page);
      // The list arrives after the page: wait for a path that is always there.
      await expect(rowOf(page, referencePath.id)).toBeVisible({ timeout: 30_000 });
      const left = app(page).locator('[data-testid^="learningpath-row-"]')
        .filter({ has: page.locator('h5', { hasText: title }) });
      if (await left.count()) {
        await left.first().getByRole('button', { name: /^(Delete|Löschen):/ }).click();
        await left.first().locator('.deletealert .btn-danger').click();
        await expect(left).toHaveCount(0, { timeout: 30_000 });
      }
    }
  });
});
