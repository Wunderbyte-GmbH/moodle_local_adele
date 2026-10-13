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
 * roles-own-and-foreign-paths - chains R1 and R2 of the E2E plan as the
 * E2E issue (section 2) asks for them: the administrator and the manager,
 * each on a path of their OWN and on one SOMEBODY ELSE owns, really save,
 * reopen, add and remove an editor - and every right is checked again after
 * a reload and in a new session, separately.
 *
 *     R1: the administrator on a path the manager owns
 *     R2: the manager on a path of their own (created in the interface)
 *
 * The editor added and removed is the ADELE assistant; whether the right
 * reaches them is checked in their own fresh session (the overview offers
 * the edit button, or does not). What the stored path says is read on the
 * server after each step: name, owner, editors.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  callService,
  fixture,
  fixturePassword,
  reseedFixtures,
  runLabel,
  saveLearningPath,
  waitForEditorLoaded,
} from '../support/fixtures';
import {
  addEditor,
  app,
  createPathAs,
  editorCards,
  openEditor,
  openOverview,
  removeEditor,
  renameInEditor,
  rowOf,
  storedPath,
} from '../support/paths';

/** Who acts, as whom they log in. */
interface Actor {
  username: string;
  password: string;
}

/**
 * The assistant's view in a fresh session: may they edit this path?
 *
 * @param page The page.
 * @param lpid The path.
 */
async function assistantMayEdit(page: Page, lpid: number): Promise<boolean> {
  await loginAs(page, fixture('ADELE_FIXTURE_ASSISTANT'), fixturePassword());
  await openOverview(page);
  // The list has arrived once nothing is in flight any more. A new path is
  // not visible to everybody, so the assistant may not see it at all - which
  // is "may not edit" as well.
  await page.waitForLoadState('networkidle');
  const row = rowOf(page, lpid);
  if (!(await row.count())) {
    return false;
  }
  return (await row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).count()) > 0;
}

/**
 * The chain both stories share, from an open path onwards.
 *
 * @param page The page.
 * @param actor The person working on the path.
 * @param lpid The path.
 * @param owner The owner's username, as stored.
 * @param label The name the actor gives the path.
 */
async function saveReopenAndShare(page: Page, actor: Actor, lpid: number, owner: string, label: string): Promise<void> {
  const assistant = fixture('ADELE_FIXTURE_ASSISTANT');
  const assistantName = fixture('ADELE_FIXTURE_ASSISTANT_NAME');

  await test.step('the actor makes the path visible in the overview', async () => {
    // A hidden path is not listed for an assistant who did not create it,
    // editor or not (#472) - so it is shared visibly, as a person would.
    await loginAs(page, actor.username, actor.password);
    await openOverview(page);
    const toggle = app(page).locator(`[data-testid="learningpath-visibility-toggle-${lpid}"]`);
    if (storedPath(lpid).visibility !== 1) {
      await toggle.click();
      await expect.poll(() => storedPath(lpid).visibility, { message: 'the server stores the path as visible' }).toBe(1);
    }
    await expect(toggle.locator('i.fa-eye')).toHaveCount(1);
  });

  await test.step('precondition: the assistant may not edit the path', async () => {
    expect(storedPath(lpid).editors).not.toContain(assistant);
    expect(await assistantMayEdit(page, lpid)).toBe(false);
  });

  await test.step('rename and save; stored, and still there after a reload and on reopening', async () => {
    await loginAs(page, actor.username, actor.password);
    await openOverview(page);
    await openEditor(page, lpid);
    await renameInEditor(page, label);
    expect(storedPath(lpid).name, 'the server stores the new name').toBe(label);
    expect(storedPath(lpid).owner, 'saving does not change the owner').toBe(owner);

    await openOverview(page);
    await expect(rowOf(page, lpid).locator('h5').first()).toHaveText(label, { timeout: 30_000 });
    await openEditor(page, lpid);
    await expect(app(page).locator('[data-testid="learningpath-title"]'), 'reopened, the editor shows the stored name')
      .toHaveValue(label);
  });

  await test.step('add the assistant as an editor; stored, and visible after a reload', async () => {
    await addEditor(page, assistantName);
    expect(storedPath(lpid).editors).toEqual(expect.arrayContaining([assistant, owner]));
    await openOverview(page);
    await openEditor(page, lpid);
    await expect(editorCards(page).filter({ hasText: assistantName })).toHaveCount(1);
  });

  await test.step('in a new session of their own, the assistant may now edit the path', async () => {
    expect(await assistantMayEdit(page, lpid)).toBe(true);
    await openEditor(page, lpid);
    await expect(app(page).locator('[data-testid="learningpath-title"]')).toHaveValue(label);
  });

  await test.step('in a new session, the actor takes the right back; the owner stays', async () => {
    await loginAs(page, actor.username, actor.password);
    await openOverview(page);
    await openEditor(page, lpid);
    const ownerCard = editorCards(page).filter({ has: page.locator('.owner-crown') });
    await expect(ownerCard, 'the owner is on the list').toHaveCount(1);
    await expect(ownerCard.locator('button.text-danger'), 'and cannot be removed').toHaveCount(0);
    await removeEditor(page, assistantName);
    expect(storedPath(lpid).editors).not.toContain(assistant);
    expect(storedPath(lpid).editors).toContain(owner);
  });

  await test.step('in a new session, the assistant may not edit any more', async () => {
    expect(await assistantMayEdit(page, lpid)).toBe(false);
  });
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-R1/R2 — own and foreign paths, saved, reopened, shared', () => {
  test('R1: the administrator on a path the manager owns', { tag: ['@story:R1'] }, async ({ page }) => {
    const manager = fixture('ADELE_FIXTURE_MANAGER');
    await loginAs(page, manager, fixturePassword());
    const lpid = await createPathAs(page, runLabel('R1 Pfad des Managers'));
    try {
      expect(storedPath(lpid).owner, 'precondition: the manager owns it').toBe(manager);
      await saveReopenAndShare(page, { username: env.adminUser, password: env.adminPassword }, lpid, manager,
        runLabel('R1 vom Administrator'));
    } finally {
      await loginAs(page, env.adminUser, env.adminPassword);
      await callService(page, 'local_adele_delete_learningpath', { userid: 0, learningpathid: lpid, contextid: 1 });
    }
  });

  test('R2: the manager on a path of their own, created in the interface', { tag: ['@story:R2'] }, async ({ page }) => {
    const manager = fixture('ADELE_FIXTURE_MANAGER');
    const title = runLabel('R2 eigener Pfad');
    await loginAs(page, manager, fixturePassword());
    await openOverview(page);
    await app(page).locator('[data-testid="learningpath-create"]').click();
    await waitForEditorLoaded(page);
    await app(page).locator('[data-testid="learningpath-title"]').fill(title);
    await app(page).locator('[data-testid="learningpath-description"]').fill('Angelegt von der E2E-Kette R2.');
    await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
    await openOverview(page);
    const row = app(page).locator('[data-testid^="learningpath-row-"]').filter({ has: page.locator('h5', { hasText: title }) });
    await expect(row).toHaveCount(1, { timeout: 30_000 });
    const lpid = Number(((await row.getAttribute('data-testid')) || '').replace('learningpath-row-', ''));
    try {
      expect(storedPath(lpid).owner, 'the creator owns the path').toBe(manager);
      expect(storedPath(lpid).editors, 'and stands on its list of editors').toContain(manager);
      await saveReopenAndShare(page, { username: manager, password: fixturePassword() }, lpid, manager,
        runLabel('R2 eigener Pfad umbenannt'));
    } finally {
      await loginAs(page, manager, fixturePassword());
      await callService(page, 'local_adele_delete_learningpath', { userid: 0, learningpathid: lpid, contextid: 1 });
    }
  });
});
