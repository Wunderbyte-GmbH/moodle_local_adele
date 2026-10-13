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
 * roles-direct-access - chains R3 to R6 of the E2E plan, from the side the
 * interface does not show: what the server answers when a person asks for
 * something directly, through the web services the editor itself uses.
 *
 * A missing button proves nothing (E2E issue, section 2: "direkte Lese-/
 * Schreib-Negativzugriffe"). So each refused request is checked twice: by
 * the server's answer AND by the stored state afterwards - name, owner,
 * visibility and the list of editors of the path must be exactly what they
 * were.
 *
 * People:
 *   - the control learner: on no path, no ADELE role;
 *   - the reference learner: subscribed to Linear A1 only;
 *   - the ADELE assistant: may create paths and is an editor of none of the
 *     fixture paths (the seed resets the lists);
 *   - the ADELE manager, as the positive control: same requests, allowed.
 *
 * Policy as the code states it (and as this file pins it):
 *   - reading a path: its editors, people subscribed to it, people who may
 *     see participants, and everybody holding an ADELE role
 *     (get_learningpath: check_access) - so an assistant may READ any path;
 *   - changing it (save, delete, visibility, editors): editors of that path,
 *     managers and administrators (require_lp_editor_access);
 *   - duplicating: the owner, managers and administrators (#471);
 *   - handing over ownership: managers and administrators only.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect, Page } from '@playwright/test';
import { loginAs } from '../support/env';
import {
  callService,
  controlUser,
  fixture,
  fixturePassword,
  referencePath,
  reseedFixtures,
} from '../support/fixtures';
import { pathCount, storedPath } from '../support/paths';

/** The stored path plus the number of paths: what a refused request must leave alone. */
const pathState = (lpid: string) => ({ ...storedPath(lpid), paths: pathCount() });

/**
 * The user id of the person logged in on this page.
 *
 * @param page The page.
 */
async function ownId(page: Page): Promise<number> {
  await page.goto('/user/preferences.php');
  return page.evaluate(() => Number((window as unknown as { M: { cfg: { userId: number } } }).M.cfg.userId));
}

/**
 * Every changing request a person could send for one path, by name.
 *
 * @param lpid The path.
 * @param self The asking person's user id - the classic self-grant.
 */
function changingRequests(lpid: number, self: number): [string, string, Record<string, unknown>][] {
  return [
    ['rename it', 'local_adele_save_learningpath',
      { userid: self, learningpathid: lpid, name: 'Übernommen', description: '', json: '{"tree":{"nodes":[],"edges":[]}}', contextid: 1 }],
    ['hide it', 'local_adele_update_lp_visiblity', { contextid: 1, lpid, visibility: false }],
    ['make oneself an editor', 'local_adele_create_lp_edit_users', { contextid: 1, lpid, userid: self }],
    ['read its editors', 'local_adele_get_lp_edit_users', { contextid: 1, lpid }],
    ['take over ownership', 'local_adele_set_lp_owner', { contextid: 1, lpid, userid: self }],
    ['duplicate it', 'local_adele_duplicate_learningpath', { userid: self, learningpathid: lpid, contextid: 1 }],
    ['delete it', 'local_adele_delete_learningpath', { userid: self, learningpathid: lpid, contextid: 1 }],
  ];
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-R — what the server refuses when asked directly', () => {
  for (const who of ['the control learner', 'the reference learner', 'the assistant'] as const) {
    test(`${who} changes nothing on a path they do not edit`, { tag: ['@story:R3', '@story:R6'] }, async ({ page }) => {
      const user = who === 'the control learner' ? controlUser()
        : who === 'the reference learner' ? referencePath.learner
          : fixture('ADELE_FIXTURE_ASSISTANT');
      const lpid = Number(referencePath.id);
      const before = pathState(referencePath.id);
      expect(before.editors, `precondition: ${user} is no editor of ${referencePath.name}`).not.toContain(user);

      await loginAs(page, user, fixturePassword());
      const self = await ownId(page);
      for (const [what, method, args] of changingRequests(lpid, self)) {
        await test.step(`${what}: refused`, async () => {
          const answer = await callService(page, method, args);
          expect(answer.error, `${method} must be refused for ${user}, it answered ${JSON.stringify(answer.data)}`)
            .toBeTruthy();
        });
      }
      await test.step('and the stored path is exactly as before', async () => {
        expect(pathState(referencePath.id)).toEqual(before);
      });
    });
  }

  test('reading: a learner reads their own path, not another one', { tag: ['@story:R3'] }, async ({ page }) => {
    const other = fixture('ADELE_FIXTURE_PATH_LINEAR_A2');
    await loginAs(page, referencePath.learner, fixturePassword());
    const self = await ownId(page);

    const own = await callService(page, 'local_adele_get_learningpath',
      { userid: self, learningpathid: Number(referencePath.id), contextid: 1 });
    expect(own.error, 'the subscribed learner may load the path they are on').toBeFalsy();
    expect((own.data as { name?: string }).name).toBe(referencePath.name);

    const foreign = await callService(page, 'local_adele_get_learningpath',
      { userid: self, learningpathid: Number(other), contextid: 1 });
    expect(foreign.error, 'a path the learner is not on must be refused').toBe('nopermissions');

    await loginAs(page, controlUser(), fixturePassword());
    const control = await callService(page, 'local_adele_get_learningpath',
      { userid: await ownId(page), learningpathid: Number(referencePath.id), contextid: 1 });
    expect(control.error, 'a person on no path reads none').toBe('nopermissions');
  });

  test('the positive control: the manager may do what the others may not', { tag: ['@story:R2', '@story:R6'] }, async ({ page }) => {
    const manager = fixture('ADELE_FIXTURE_MANAGER');
    const lpid = Number(referencePath.id);
    const before = pathState(referencePath.id);
    await loginAs(page, manager, fixturePassword());
    const self = await ownId(page);

    await test.step('read the editors', async () => {
      const answer = await callService(page, 'local_adele_get_lp_edit_users', { contextid: 1, lpid });
      expect(answer.error).toBeFalsy();
    });
    await test.step('hide the path and show it again', async () => {
      expect((await callService(page, 'local_adele_update_lp_visiblity', { contextid: 1, lpid, visibility: false })).error).toBeFalsy();
      expect(pathState(referencePath.id).visibility).toBe(0);
      expect((await callService(page, 'local_adele_update_lp_visiblity', { contextid: 1, lpid, visibility: true })).error).toBeFalsy();
      expect(pathState(referencePath.id).visibility).toBe(1);
    });
    await test.step('add oneself as an editor and leave again', async () => {
      expect((await callService(page, 'local_adele_create_lp_edit_users', { contextid: 1, lpid, userid: self })).error).toBeFalsy();
      expect(pathState(referencePath.id).editors).toContain(manager);
      expect((await callService(page, 'local_adele_remove_lp_edit_users', { contextid: 1, lpid, userid: self })).error).toBeFalsy();
      expect(pathState(referencePath.id).editors).not.toContain(manager);
    });
    await test.step('the path is as before', async () => {
      expect(pathState(referencePath.id)).toEqual(before);
    });
  });
});
