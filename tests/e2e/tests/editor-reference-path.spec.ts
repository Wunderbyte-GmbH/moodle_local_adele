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
 * editor-reference-path - chain E1 of the E2E plan as the E2E issue
 * (section 2) asks for it: the reference path T01 -> T02 -> T03 built
 * entirely in the interface, WITH its configuration, then saved, left, a
 * new session, reopened - and every element asserted on its own.
 *
 *     nodes        T01, T02, T03 (by drag and drop)
 *     relations    T01 -> T02 -> T03
 *     completion   each node: the course is completed (what a dropped
 *                  course gets)
 *     access T02   its predecessor (written by the editor on the drop)
 *     access T03   its predecessor AND a time window (2030-01-15
 *                  10:00-12:00 UTC), with an author's feedback text instead
 *                  of the standard one
 *
 * Checked twice: in the stored path on the server (labels, structure,
 * values, feedback) and in the reopened editor (what the person sees).
 * Dates are typed in a browser on UTC, so the stored timestamps are known.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { execFileSync } from 'node:child_process';
import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  conditionNode,
  conditions,
  dropCondition,
  openAccessCriteria,
  saveCriteria,
  storedConditionColumns,
} from '../support/conditions';
import { dropCourseAt, dropFirstCourse, VIEWPORT } from '../support/editor';
import {
  callService,
  fixture,
  reseedFixtures,
  runLabel,
  saveLearningPath,
  waitForEditorLoaded,
} from '../support/fixtures';
import { app } from '../support/paths';

/** The window of T03, as typed and as stored (UTC). */
const WINDOW = { start: '2030-01-15T10:00', end: '2030-01-15T12:00', startEpoch: 1894701600, endEpoch: 1894708800 };

/** The author's text for T03's access criterion. */
const AUTHOR_TEXT = 'E1: Erst Testkurs 02 abschließen, dann im Zeitfenster beginnen.';

/**
 * The stored access or completion criteria of one node, condition by
 * condition: label, value, and the feedback node's texts.
 *
 * @param title The path's name.
 * @param nodeid The node.
 * @param kind 'restriction' or 'completion'.
 */
function storedCriteria(title: string, nodeid: string, kind: string): { id: string; label: string; data: Record<string, unknown> }[] {
  const script = `
    define('CLI_SCRIPT', true);
    require('config.php');
    $path = $DB->get_record('local_adele_learning_paths', ['name' => $argv[1]], 'json', MUST_EXIST);
    $out = [];
    foreach (json_decode($path->json, true)['tree']['nodes'] as $node) {
      if ($node['id'] !== $argv[2]) { continue; }
      foreach ($node[$argv[3]]['nodes'] ?? [] as $c) {
        $out[] = ['id' => $c['id'], 'label' => $c['data']['label'] ?? '', 'data' => $c['data'] ?? []];
      }
    }
    echo json_encode($out);`;
  const out = execFileSync('php', ['-r', script, '--', title, nodeid, kind], { cwd: fixture('ADELE_MOODLE_ROOT'), encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop() || '[]');
}

/**
 * The stored path's nodes with their courses and relations.
 *
 * @param title The path's name.
 */
function storedGraph(title: string): { nodes: Record<string, { course: string; parents: string[]; children: string[] }>; id: number } {
  const script = `
    define('CLI_SCRIPT', true);
    require('config.php');
    $path = $DB->get_record('local_adele_learning_paths', ['name' => $argv[1]], 'id, json', MUST_EXIST);
    $nodes = [];
    foreach (json_decode($path->json, true)['tree']['nodes'] as $node) {
      if (strpos($node['id'], 'dndnode_') !== 0) { continue; }
      $courseid = (int) (($node['data']['course_node_id'] ?? [0])[0] ?? 0);
      $nodes[$node['id']] = [
        'course' => (string) $DB->get_field('course', 'shortname', ['id' => $courseid]),
        'parents' => array_values(array_filter((array) ($node['parentCourse'] ?? []), fn($p) => $p !== 'starting_node')),
        'children' => array_values((array) ($node['childCourse'] ?? [])),
      ];
    }
    echo json_encode(['id' => (int) $path->id, 'nodes' => $nodes]);`;
  const out = execFileSync('php', ['-r', script, '--', title], { cwd: fixture('ADELE_MOODLE_ROOT'), encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop() || '{}');
}

/**
 * Open the editor of a path by its card title, from the overview.
 *
 * @param page The page.
 * @param title The path's name.
 */
async function reopen(page: Page, title: string): Promise<void> {
  await page.goto('/local/adele/index.php');
  const card = app(page).locator('[data-testid^="learningpath-row-"]').filter({ has: page.locator('h5', { hasText: title }) });
  await expect(card).toHaveCount(1, { timeout: 30_000 });
  await card.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
  await expect(app(page).locator('[data-testid^="learningpath-node-"]')).toHaveCount(3, { timeout: 30_000 });
  await waitForEditorLoaded(page);
}

// Every file starts from the seeded state, whatever ran before it
// (support/fixtures: reseedFixtures; E2E issue, section 3B).
test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-E1 — the reference path with its configuration, built in the interface', () => {
  test.use({ viewport: VIEWPORT, timezoneId: 'UTC' });

  test('T01 -> T02 -> T03 with completion, predecessor, AND time window and an author text', { tag: ['@story:E1'] }, async ({ page }) => {
    const title = runLabel('E1 Referenzpfad vollständig');
    page.on('dialog', (dialog) => dialog.accept());
    await loginAs(page, env.adminUser, env.adminPassword);
    let lpid = 0;

    try {
      await test.step('build the three nodes and save the path', async () => {
        await page.goto('/local/adele/index.php');
        await app(page).locator('[data-testid="learningpath-create"]').click();
        await waitForEditorLoaded(page);
        await app(page).locator('[data-testid="learningpath-title"]').fill(title);
        await app(page).locator('[data-testid="learningpath-description"]').fill('E1 vollständig, nur über die Oberfläche.');
        await dropFirstCourse(page, 'T01');
        await dropCourseAt(page, 'T02', 'dndnode_1', 'child');
        await dropCourseAt(page, 'T03', 'dndnode_2', 'child');
        await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
        lpid = storedGraph(title).id;
      });

      await test.step('T03: its predecessor AND a time window, with an author text', async () => {
        await reopen(page, title);
        await openAccessCriteria(page, 'dndnode_3');
        await expect(conditions(page), 'the editor wrote the predecessor condition on the drop').toHaveCount(1);
        await dropCondition(page, 'Start and end time', 'condition_1', 'source_and');
        const timed = conditions(page).filter({ has: page.locator('input[type="datetime-local"]') });
        await expect(timed).toHaveCount(1);
        await timed.locator('input[type="datetime-local"]').nth(0).fill(WINDOW.start);
        await timed.locator('input[type="datetime-local"]').nth(1).fill(WINDOW.end);

        // The author's text replaces the standard one: untick "use the
        // standard text", then write.
        const feedback = page.locator('.dndflowcompletion .vue-flow__node[data-id$="_feedback"]').first();
        await expect(feedback, 'the criterion offers a feedback field').toBeVisible({ timeout: 15_000 });
        const standard = feedback.locator('input[type="checkbox"][id^="feedback_before_restriction_"]');
        if (await standard.isDisabled()) {
          await feedback.locator('button:has(i.fa-eye-slash)').click();
        }
        if (await standard.isChecked()) {
          await standard.uncheck();
        }
        const text = feedback.locator('textarea[id^="feedback_before_restriction_textarea_"]');
        await text.fill(AUTHOR_TEXT);
        await text.blur();
        await saveCriteria(page);
        await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
      });

      await test.step('stored: nodes, relations, criteria and texts - each on its own', async () => {
        const graph = storedGraph(title);
        expect(graph.nodes.dndnode_1).toEqual({ course: 'T01', parents: [], children: ['dndnode_2'] });
        expect(graph.nodes.dndnode_2).toEqual({ course: 'T02', parents: ['dndnode_1'], children: ['dndnode_3'] });
        expect(graph.nodes.dndnode_3).toEqual({ course: 'T03', parents: ['dndnode_2'], children: [] });

        for (const node of ['dndnode_1', 'dndnode_2', 'dndnode_3']) {
          expect(storedConditionColumns(title, node, 'completion'), `${node} completes with its course`)
            .toEqual([['course_completed']]);
        }
        expect(storedConditionColumns(title, 'dndnode_2', 'restriction')).toEqual([['parent_courses']]);
        expect(storedConditionColumns(title, 'dndnode_3', 'restriction'), 'predecessor AND window, one column')
          .toEqual([['parent_courses', 'timed']]);

        const criteria = storedCriteria(title, 'dndnode_3', 'restriction');
        const timed = criteria.find((c) => c.label === 'timed');
        expect(timed?.data.value).toEqual({ start: WINDOW.startEpoch, end: WINDOW.endEpoch });
        const predecessor = criteria.find((c) => c.label === 'parent_courses');
        expect((predecessor?.data.value as { courses_id?: unknown })?.courses_id, 'the predecessor is T02\'s node')
          .toEqual(['dndnode_2']);
        const texts = criteria.filter((c) => c.id.endsWith('_feedback'))
          .map((c) => (c.data as { feedback_before?: string }).feedback_before);
        expect(texts, 'the author\'s text is stored with the criterion').toContain(AUTHOR_TEXT);
      });

      await test.step('a new session: reopened, the editor shows all of it', async () => {
        await loginAs(page, env.adminUser, env.adminPassword);
        await reopen(page, title);
        for (const [id, name] of [['dndnode_1', 'Testkurs 01'], ['dndnode_2', 'Testkurs 02'], ['dndnode_3', 'Testkurs 03']]) {
          await expect(app(page).locator(`[data-testid="learningpath-node-${id}"]`)).toContainText(name);
        }
        const edges = await page.locator('.vue-flow__edge').evaluateAll(
          (els) => els.map((e) => e.getAttribute('data-id')).filter((id) => /^dndnode_\d+dndnode_\d+$/.test(id || '')).sort());
        expect(edges).toEqual(['dndnode_2dndnode_1', 'dndnode_3dndnode_2']);

        await openAccessCriteria(page, 'dndnode_3');
        await expect(conditions(page)).toHaveCount(2);
        const timed = conditions(page).filter({ has: page.locator('input[type="datetime-local"]') });
        await expect(timed.locator('input[type="datetime-local"]').nth(0)).toHaveValue(WINDOW.start);
        await expect(timed.locator('input[type="datetime-local"]').nth(1)).toHaveValue(WINDOW.end);
        await expect(page.locator('.dndflowcompletion textarea[id^="feedback_before_restriction_textarea_"]').first())
          .toHaveValue(AUTHOR_TEXT);
        await expect(conditionNode(page, 'condition_1')).toBeVisible();
      });
    } finally {
      if (lpid) {
        await loginAs(page, env.adminUser, env.adminPassword);
        await callService(page, 'local_adele_delete_learningpath', { userid: 0, learningpathid: lpid, contextid: 1 });
      }
    }
  });
});
