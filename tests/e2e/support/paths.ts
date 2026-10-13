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
 * The learning path overview and the editor's list of editors, operated the
 * way a person does - and read back from the server, where the plan wants
 * proof that a change was stored (section 2: persistence).
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { execFileSync } from 'node:child_process';
import { expect, Locator, Page } from '@playwright/test';
import { callService, fixture, saveLearningPath, waitForEditorLoaded } from './fixtures';

/** The Vue application's mount point. */
export const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/** The overview card of one learning path. */
export const rowOf = (page: Page, id: string | number): Locator =>
  app(page).locator(`[data-testid="learningpath-row-${id}"]`);

/** The editor's cards of the people who may edit the path. */
export const editorCards = (page: Page): Locator => app(page).locator('.card-user');

/**
 * Open the overview and wait for the application.
 *
 * @param page The page.
 */
export async function openOverview(page: Page): Promise<void> {
  await page.goto('/local/adele/index.php');
  await expect(app(page)).toBeVisible({ timeout: 30_000 });
}

/**
 * Open the editor of a path from its overview card.
 *
 * @param page The page, on the overview.
 * @param id The path.
 */
export async function openEditor(page: Page, id: string | number): Promise<void> {
  const row = rowOf(page, id);
  await expect(row).toBeVisible({ timeout: 30_000 });
  await row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
  await expect(app(page).locator('[data-testid="learningpath-title"]')).toBeVisible({ timeout: 30_000 });
  await waitForEditorLoaded(page);
}

/**
 * In the open editor: give the path a new title and save.
 *
 * @param page The page with the editor.
 * @param title The new title.
 */
export async function renameInEditor(page: Page, title: string): Promise<void> {
  await app(page).locator('[data-testid="learningpath-title"]').fill(title);
  await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
}

/**
 * In the open editor: add a person to the editors - typed, then picked from
 * the result list.
 *
 * @param page The page with the editor.
 * @param fullname The person's full name, e.g. "Fixture Assistant".
 */
export async function addEditor(page: Page, fullname: string): Promise<void> {
  await app(page).locator('input.user-search-input').fill(fullname.split(' ').slice(1).join(' ') || fullname);
  const hit = app(page).locator('.user-item').filter({ hasText: fullname });
  await expect(hit, `the search must offer ${fullname}`).toHaveCount(1, { timeout: 30_000 });
  await hit.click();
  await expect(editorCards(page).filter({ hasText: fullname }), `${fullname} must appear among the editors`)
    .toHaveCount(1, { timeout: 15_000 });
}

/**
 * In the open editor: take a person off the editors. The removal asks through
 * the browser's confirm(), which is accepted here - a dismissed confirm
 * cancels without a word.
 *
 * @param page The page with the editor.
 * @param fullname The person's full name.
 */
export async function removeEditor(page: Page, fullname: string): Promise<void> {
  const card = editorCards(page).filter({ hasText: fullname });
  await expect(card).toHaveCount(1, { timeout: 30_000 });
  page.once('dialog', (dialog) => dialog.accept());
  await card.locator('button.text-danger').click();
  await expect(card).toHaveCount(0, { timeout: 15_000 });
}

/** What the server stores about a path. */
export interface StoredPath {
  name: string;
  owner: string;
  visibility: number;
  editors: string[];
}

/**
 * Read a path's stored state on the server, independent of the browser.
 *
 * @param lpid The learning path.
 * @returns Name, owner (username), visibility and editors (usernames).
 */
export function storedPath(lpid: string | number): StoredPath {
  const script = `
    define('CLI_SCRIPT', true);
    require('config.php');
    $lp = $DB->get_record('local_adele_learning_paths', ['id' => (int) $argv[1]], 'id, name, createdby, visibility', MUST_EXIST);
    $editors = $DB->get_fieldset_sql(
      'SELECT u.username FROM {local_adele_lp_editors} e JOIN {user} u ON u.id = e.userid
        WHERE e.learningpathid = ? ORDER BY u.username', [$lp->id]);
    echo json_encode([
      'name' => $lp->name,
      'owner' => (string) $DB->get_field('user', 'username', ['id' => $lp->createdby]),
      'visibility' => (int) $lp->visibility,
      'editors' => array_values($editors),
    ]);`;
  const out = execFileSync('php', ['-r', script, '--', String(lpid)], { cwd: fixture('ADELE_MOODLE_ROOT'), encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop() || '{}');
}

/**
 * Create a path as the person logged in, through the service the editor
 * uses - for chains that need a path somebody else owns.
 *
 * @param page The page, logged in as the future owner.
 * @param name The path's name.
 * @returns Its id.
 */
export async function createPathAs(page: Page, name: string): Promise<number> {
  await page.goto('/local/adele/index.php');
  const userid = await page.evaluate(() => Number((window as unknown as { M: { cfg: { userId: number } } }).M.cfg.userId));
  const answer = await callService(page, 'local_adele_save_learningpath', {
    userid, learningpathid: 0, name, description: 'E2E', json: '{"tree":{"nodes":[],"edges":[]}}', contextid: 1,
  });
  expect(answer.error, `creating "${name}" must succeed`).toBeFalsy();
  const id = Number((answer.data as { learningpath?: { id?: number } }).learningpath?.id);
  expect(id, 'the server must return the new path').toBeGreaterThan(0);
  return id;
}

/**
 * How many learning paths the site has - a duplicate or a deletion shows here.
 */
export function pathCount(): number {
  const out = execFileSync('php', ['-r',
    "define('CLI_SCRIPT', true); require('config.php'); echo $DB->count_records('local_adele_learning_paths');"],
  { cwd: fixture('ADELE_MOODLE_ROOT'), encoding: 'utf8' });
  return Number(out.trim().split('\n').pop());
}
