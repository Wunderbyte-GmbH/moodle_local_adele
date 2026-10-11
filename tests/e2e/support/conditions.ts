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
 * The criteria editor of a node (access or completion): conditions dropped
 * from its sidebar, combined into AND chains (one under the other) and OR
 * columns (side by side), saved with the path.
 *
 * How the editor combines them: every column starts at "starting_condition"
 * and runs down through childCondition. A column is satisfied when all its
 * conditions are (AND); the criteria are satisfied when one column is (OR).
 * So what can be written directly is a disjunction of conjunctions -
 * (A AND B) OR C - and A AND (B OR C) only multiplied out, as
 * (A AND B) OR (A AND C).
 *
 * The drop zones of this editor ("source_and" below a condition,
 * "source_or" to the right of the first one in a column) appear during the
 * drag like those of the path editor, and the canvas moves as they appear:
 * the helper re-measures and aims into the visible part of the zone until the
 * editor confirms the hit.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { execFileSync } from 'node:child_process';
import { expect, Locator, Page } from '@playwright/test';
import { fixture } from './fixtures';

/** The text a zone of the criteria editor shows once it registers the pointer. */
const HIT = /new starting node|neue start-node/i;

/** The criteria editor's canvas. */
const flow = (page: Page): Locator => page.locator('.dndflowcompletion');

/** A condition (or zone, or feedback) in the criteria editor, by id. */
export const conditionNode = (page: Page, id: string): Locator =>
  flow(page).locator(`.vue-flow__node[data-id="${id}"]`);

/** The conditions currently on the canvas (without feedback and zones). */
export const conditions = (page: Page): Locator => flow(page).locator('.vue-flow__node-custom');

/**
 * Open the access criteria of a node from the path editor, and zoom the
 * canvas out a little so the drop zones fit into it.
 *
 * @param page The page showing the path editor.
 * @param nodeid The node, e.g. "dndnode_2".
 * @param covered Whether another node lies on top of it.
 */
export async function openAccessCriteria(page: Page, nodeid: string, covered = false): Promise<void> {
  const button = page.locator(`[data-testid="learningpath-node-${nodeid}"] button:has(i.fa-lock)`).first();
  if (covered) {
    // The node lies under another one (known defect, see editor-brackets):
    // a pointer cannot reach the button, the click event is sent to it
    // directly.
    await button.dispatchEvent('click');
  } else {
    await button.click({ timeout: 15_000 });
  }
  const pane = flow(page).locator('.vue-flow__pane').first();
  await expect(pane).toBeVisible({ timeout: 30_000 });
  // The editor fits its view on opening (one second of animation) and only
  // then allows zooming.
  await page.waitForTimeout(2_000);
  const area = await pane.boundingBox();
  expect(area, 'the criteria canvas must be on screen').toBeTruthy();
  await page.mouse.move(area!.x + area!.width / 2, area!.y + area!.height / 2);
  for (let step = 0; step < 3; step++) {
    await page.mouse.wheel(0, 300);
    await page.waitForTimeout(700);
  }
}

/**
 * Drag a condition out of the sidebar and drop it on a zone of a condition
 * already there.
 *
 * @param page The page showing the criteria editor.
 * @param name The condition as the sidebar names it, e.g. "Manual access".
 * @param target The condition the zone belongs to, e.g. "condition_1".
 * @param zone "source_and" (below: AND) or "source_or" (to the right: OR).
 */
export async function dropCondition(page: Page, name: string, target: string, zone: 'source_and' | 'source_or'): Promise<void> {
  const before = await conditions(page).count();
  const pane = flow(page).locator('.vue-flow__pane').first();
  await page.locator(`[data-draggable="${name}"]`).first().hover();
  await page.mouse.down();
  let area = await pane.boundingBox();
  const own = await conditionNode(page, target).boundingBox();
  expect(area && own, `${target} and the canvas must be on screen`).toBeTruthy();
  // Onto the condition first: the zones are drawn around the one nearest
  // to the pointer.
  for (let step = 1; step <= 4; step++) {
    await pane.hover({
      position: {
        x: Math.min(own!.x + own!.width / 2, area!.x + area!.width - 5) - area!.x + step,
        y: Math.min(own!.y + own!.height / 2, area!.y + area!.height - 5) - area!.y,
      },
      force: true,
    });
    await page.waitForTimeout(150);
  }
  let hit = false;
  for (let attempt = 0; attempt < 12 && !hit; attempt++) {
    area = await pane.boundingBox();
    const box = await conditionNode(page, zone).boundingBox({ timeout: 500 }).catch(() => null);
    if (box && area) {
      const top = Math.max(box.y, area.y) + 2;
      const bottom = Math.min(box.y + box.height, area.y + area.height) - 2;
      const left = Math.max(box.x, area.x) + 2;
      const right = Math.min(box.x + box.width, area.x + area.width) - 2;
      if (bottom > top && right > left) {
        await pane.hover({
          position: { x: (left + right) / 2 - area.x + (attempt % 2), y: (top + bottom) / 2 - area.y },
          force: true,
        });
      }
    }
    await page.waitForTimeout(200);
    hit = HIT.test(await conditionNode(page, zone).innerText({ timeout: 500 }).catch(() => ''));
  }
  expect(hit, `the editor never registered the pointer on ${zone} of ${target}`).toBe(true);
  await page.mouse.up();
  await expect(conditions(page), `dropping "${name}" must add a condition`).toHaveCount(before + 1, { timeout: 15_000 });
}

/**
 * Choose the node a "Certain node completed" condition is about.
 *
 * @param page The page showing the criteria editor.
 * @param id The condition, e.g. "condition_3".
 * @param name The node as the list names it, e.g. "Testkurs 01".
 */
export async function chooseNode(page: Page, id: string, name: string): Promise<void> {
  const select = conditionNode(page, id).locator('select').first();
  await select.selectOption({ label: name });
  await expect(conditionNode(page, id).locator('.adele-condition-warning'), 'the condition must be complete now').toHaveCount(0);
}

/**
 * Set how many predecessors an "According to predecessor nodes" condition
 * asks for.
 *
 * @param page The page showing the criteria editor.
 * @param id The condition, e.g. "condition_1".
 * @param count The number.
 */
export async function requirePredecessors(page: Page, id: string, count: number): Promise<void> {
  const ids = await conditions(page).evaluateAll((els) => els.map((el) => el.getAttribute('data-id')));
  expect(ids, `${id} must be on the canvas`).toContain(id);
  const select = conditionNode(page, id).locator('select').first();
  const offered = await select.locator('option:not([disabled])').allInnerTexts();
  const shown = (await conditionNode(page, id).locator('.form-group').innerText()).replace(/\s+/g, ' ');
  expect(offered.map((text) => text.trim()), `the numbers ${id} offers (it shows: "${shown}")`).toContain(String(count));
  await select.selectOption(String(count), { timeout: 10_000 });
  await expect(select).toHaveValue(String(count));
}

/**
 * Insert a course with the keyboard dialog (#575 B5) - the only way to give
 * a node a second predecessor or successor (#595).
 *
 * @param page The page showing the path editor.
 * @param courseid The course, by its id (sidebar entry).
 * @param target The node it is placed relative to, as the dialog names it.
 * @param relation "dropzone_child" (after), "dropzone_parent" (before),
 *                 "dropzone_and" (parallel) or "dropzone_or" (into the node).
 */
export async function insertWithKeyboard(page: Page, courseid: string, target: string, relation: string): Promise<void> {
  const nodes = page.locator('[data-testid^="learningpath-node-"]');
  const before = await nodes.count();
  const insert = page.locator(`[data-testid="learningpath-sidebar-insert-${courseid}"]`);
  await insert.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('[data-testid="learningpath-insert-dialog"]')).toBeVisible();
  await page.locator('[data-testid="learningpath-insert-target"]').selectOption({ label: target });
  await page.locator('[data-testid="learningpath-insert-relation"]').selectOption(relation);
  await page.locator('[data-testid="learningpath-insert-confirm"]').press('Enter');
  await expect(page.locator('[data-testid="learningpath-insert-dialog"]')).toBeHidden();
  await expect(nodes).toHaveCount(relation === 'dropzone_or' ? before : before + 1, { timeout: 20_000 });
}

/**
 * The nodes of a path AS STORED: id => courses in it, predecessors, successors.
 *
 * @param title The title of the learning path.
 */
export function storedNodes(title: string): Record<string, { courses: number; parents: string[]; children: string[] }> {
  const script = `
    define('CLI_SCRIPT', true);
    require('config.php');
    $path = $DB->get_record('local_adele_learning_paths', ['name' => $argv[1]], 'json', MUST_EXIST);
    $out = [];
    foreach (json_decode($path->json, true)['tree']['nodes'] as $node) {
      if (strpos($node['id'], 'dndnode_') !== 0) { continue; }
      $out[$node['id']] = [
        'courses' => count((array) ($node['data']['course_node_id'] ?? [])),
        'parents' => array_values((array) ($node['parentCourse'] ?? [])),
        'children' => array_values((array) ($node['childCourse'] ?? [])),
      ];
    }
    echo json_encode($out);`;
  const out = execFileSync('php', ['-r', script, '--', title], { cwd: fixture('ADELE_MOODLE_ROOT'), encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop() || '{}');
}

/**
 * Delete a condition with its bin icon.
 *
 * @param page The page showing the criteria editor.
 * @param id The condition, e.g. "condition_2".
 */
export async function deleteCondition(page: Page, id: string): Promise<void> {
  const before = await conditions(page).count();
  await conditionNode(page, id).locator('.fa-trash').first().click();
  await expect(conditions(page), `deleting ${id} must remove it`).toHaveCount(before - 1, { timeout: 15_000 });
}

/**
 * Save the criteria (which saves the whole path) and wait for the path editor.
 *
 * @param page The page showing the criteria editor.
 */
export async function saveCriteria(page: Page): Promise<void> {
  // A refused save stays in the criteria editor and says why - in a toast,
  // or in the browser's own alert box.
  const alerts: string[] = [];
  const listen = (dialog: { message: () => string }): void => {
    alerts.push(dialog.message());
  };
  page.on('dialog', listen);
  await page.locator('.save-restore-controls button.btn-primary').first().click();
  const back = page.locator('[data-testid="learningpath-title"]');
  await back.waitFor({ state: 'visible', timeout: 30_000 }).catch(() => undefined);
  page.off('dialog', listen);
  const said = [...alerts, ...await page.locator('.vue-notification').allInnerTexts()].join(' | ');
  await expect(back, `saving must lead back to the path editor${said ? ' - the editor said: ' + said : ''}`)
    .toBeVisible({ timeout: 1_000 });
}

/**
 * Leave the criteria editor with "Close", confirming that the changes are to
 * be thrown away.
 *
 * @param page The page showing the criteria editor.
 */
export async function closeCriteriaDiscarding(page: Page): Promise<void> {
  await page.locator('.save-restore-controls button.btn-secondary').first().click();
  await page.locator('#confim-cancel-learning-path').first().click();
  await expect(page.locator('[data-testid="learningpath-title"]'), 'closing must lead back to the path editor')
    .toBeVisible({ timeout: 30_000 });
}

/**
 * The criteria of a node AS STORED, one entry per OR column, each the AND
 * chain of its condition labels top-down - e.g.
 * [["parent_courses", "manual"], ["timed"]] for (A AND B) OR C.
 *
 * Read through Moodle's CLI: what the editor shows on reopening is rebuilt
 * from the same data, but a test that only looks at the editor would not see
 * a criterion that the editor fills in on its own when opened.
 *
 * @param title The title of the learning path.
 * @param nodeid The node, e.g. "dndnode_2".
 * @param kind "restriction" (access) or "completion".
 */
export function storedConditionColumns(title: string, nodeid: string, kind = 'restriction'): string[][] {
  const script = `
    define('CLI_SCRIPT', true);
    require('config.php');
    $path = $DB->get_record('local_adele_learning_paths', ['name' => $argv[1]], 'json', MUST_EXIST);
    $columns = [];
    foreach (json_decode($path->json, true)['tree']['nodes'] as $node) {
      if ($node['id'] !== $argv[2]) { continue; }
      $byid = [];
      foreach ($node[$argv[3]]['nodes'] ?? [] as $c) { $byid[$c['id']] = $c; }
      foreach ($byid as $c) {
        if (strpos($c['id'], '_feedback') !== false || (array) ($c['parentCondition'] ?? []) !== ['starting_condition']) { continue; }
        $chain = [];
        for ($current = $c; $current; ) {
          $chain[] = $current['data']['label'] ?? '?';
          $next = null;
          foreach ((array) ($current['childCondition'] ?? []) as $child) {
            if (strpos((string) $child, '_feedback') === false && isset($byid[$child])) { $next = $byid[$child]; }
          }
          $current = $next;
        }
        $columns[] = $chain;
      }
    }
    echo json_encode($columns);`;
  const out = execFileSync('php', ['-r', script, '--', title, nodeid, kind], {
    cwd: fixture('ADELE_MOODLE_ROOT'),
    encoding: 'utf8',
  });
  return JSON.parse(out.trim().split('\n').pop() || '[]');
}
