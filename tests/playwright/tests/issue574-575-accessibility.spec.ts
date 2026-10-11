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
 * ADELE-PW-574 - the interface can be addressed by business identity, and
 * ADELE-PW-575 - it can be operated and read without seeing the graph.
 *
 * Runs against the fixture learning paths (tests/playwright/fixtures), not
 * against a generated one: only a real path carries nodes with positions,
 * course names and conditions, and a generated node is not rendered at all.
 * Seeded by seed_fixtures.php.
 *
 * Deliberately NOT covered here: the learner's view of a path. It only
 * exists inside a mod_adele activity, which this plugin must not depend on;
 * that spec belongs to mod_adele's own suite.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import AxeBuilder from '@axe-core/playwright';
import * as fs from 'node:fs';
import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';

/** The fixture path used throughout: three courses, one after the other. */
const PATH_ID = () => {
  const value = process.env.ADELE_FIXTURE_PATH_LINEAR_A1;
  if (!value) {
    throw new Error(
      'ADELE_FIXTURE_PATH_LINEAR_A1 is not set. It is produced by ' +
      'tests/playwright/seed_fixtures.php; check that the seeding step ran.'
    );
  }
  return value;
};

/**
 * The Vue application's mount point.
 *
 * @param page The page.
 * @returns The application root.
 */
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/**
 * Open the fixture path in the editor.
 *
 * @param page The page.
 */
async function openFixturePath(page: Page): Promise<void> {
  await page.goto('/local/adele/index.php');
  const row = app(page).locator(`[data-testid="learningpath-row-${PATH_ID()}"]`);
  await expect(row).toBeVisible({ timeout: 30_000 });
  await row.getByRole('button', { name: /^(Edit|Bearbeiten)/ }).click();
  await expect(app(page).locator('[data-testid^="learningpath-node-"]').first())
    .toBeVisible({ timeout: 30_000 });
}

test.describe('ADELE-PW-574 — machine-readable interface', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, env.adminUser, env.adminPassword);
  });

  test('the overview addresses every learning path by its id', async ({ page }) => {
    await page.goto('/local/adele/index.php');
    const row = app(page).locator(`[data-testid="learningpath-row-${PATH_ID()}"]`);
    await expect(row).toBeVisible({ timeout: 30_000 });

    // One row per path, and the controls of THAT row, not of the first card
    // that happens to carry the same label.
    await expect(row).toHaveCount(1);
    const toggle = row.locator(`[data-testid="learningpath-visibility-toggle-${PATH_ID()}"]`);
    await expect(toggle).toHaveAttribute('aria-pressed', /true|false/);
    await expect(app(page).locator('[data-testid="learningpath-create"]')).toBeVisible();
  });

  test('every node carries its identity and its state', async ({ page }) => {
    await openFixturePath(page);
    const nodes = app(page).locator('[data-testid^="learningpath-node-"]');
    await expect(nodes).toHaveCount(3);

    for (const node of await nodes.all()) {
      // The state is one of the backend's, never an empty attribute and
      // never its internal error string.
      await expect(node).toHaveAttribute(
        'data-status',
        /^(accessible|completed|not_accessible|closed|unknown)$/
      );
      // Name and state are readable without looking at the colour (#575 B1).
      await expect(node).toHaveAttribute('role', 'group');
      await expect(node).toHaveAttribute('aria-label', /.+ - .+/);
    }
  });

  test('the editor fields and controls are addressable', async ({ page }) => {
    await openFixturePath(page);
    for (const hook of ['learningpath-title', 'learningpath-description', 'learningpath-save']) {
      await expect(app(page).locator(`[data-testid="${hook}"]`).first()).toBeVisible();
    }
    // The title field is labelled, not merely preceded by a heading.
    await expect(page.locator('label[for="goalnameplaceholder"]')).toHaveCount(1);
  });
});

test.describe('ADELE-PW-575 — usable without the graph', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, env.adminUser, env.adminPassword);
  });

  test('the live region exists before anything is announced', async ({ page }) => {
    await page.goto('/local/adele/index.php');
    const announcer = page.locator('[data-testid="learningpath-announcer"]');
    await expect(announcer).toHaveCount(1);
    await expect(announcer).toHaveAttribute('aria-live', 'polite');
    await expect(announcer).toHaveText('');
  });

  test('a course can be added with the keyboard alone', async ({ page }) => {
    await openFixturePath(page);
    const before = await app(page).locator('[data-testid^="learningpath-node-"]').count();

    // No mouse from here on: focus the first insert control and drive the
    // dialog with the keyboard, as someone who cannot drag would.
    const insert = page.locator('[data-testid^="learningpath-sidebar-insert-"]').first();
    await expect(insert).toBeVisible({ timeout: 30_000 });
    await insert.focus();
    await page.keyboard.press('Enter');

    const dialog = page.locator('[data-testid="learningpath-insert-dialog"]');
    await expect(dialog).toBeVisible();
    // The dialog takes the focus rather than leaving it behind it.
    await expect(page.locator('[data-testid="learningpath-insert-target"]')).toBeFocused();

    await page.locator('[data-testid="learningpath-insert-confirm"]').press('Enter');
    await expect(dialog).toBeHidden();

    await expect(app(page).locator('[data-testid^="learningpath-node-"]'))
      .toHaveCount(before + 1, { timeout: 20_000 });
    // And the change is announced, not only drawn (#575 B4).
    await expect(page.locator('[data-testid="learningpath-announcer"]')).not.toHaveText('');
  });

  test('the dialog closes with Escape and gives the focus back', async ({ page }) => {
    await openFixturePath(page);
    const insert = page.locator('[data-testid^="learningpath-sidebar-insert-"]').first();
    await expect(insert).toBeVisible({ timeout: 30_000 });
    await insert.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-testid="learningpath-insert-dialog"]')).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(page.locator('[data-testid="learningpath-insert-dialog"]')).toBeHidden();
    await expect(insert).toBeFocused();
  });

  test('the overview has no serious accessibility violations', async ({ page }, testInfo) => {
    await page.goto('/local/adele/index.php');
    await expect(app(page).locator(`[data-testid="learningpath-row-${PATH_ID()}"]`))
      .toBeVisible({ timeout: 30_000 });

    // Scoped to this plugin's application: Moodle's own theme is not what
    // these issues are about, and its findings would drown ours.
    const results = await new AxeBuilder({ page })
      .include('[id^="local-adele-app"]')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();

    const serious = results.violations.filter(
      (violation) => violation.impact === 'critical' || violation.impact === 'serious'
    );
    // Named in the message: a bare count tells whoever reads the CI log
    // nothing about what to fix.
    // Named down to the element: a rule id and a count tell whoever reads
    // the CI log nothing about WHAT to fix - and a violation that only shows
    // up on the runner (fonts, viewport, state) cannot be reproduced locally
    // to find out. Selector, markup and, for contrast, the measured colours.
    const details = serious.flatMap((violation) => violation.nodes.map((node) => {
      const data = (node.any?.[0]?.data ?? {}) as Record<string, unknown>;
      const colours = data.contrastRatio
        ? ` fg ${data.fgColor} on bg ${data.bgColor} = ${data.contrastRatio}:1 (needs ${data.expectedContrastRatio}, ${data.fontSize} ${data.fontWeight})`
        : '';
      return `${violation.id} at ${JSON.stringify(node.target)}${colours} :: ${String(node.html).slice(0, 160)}`;
    }));

    // Evidence attached to the run, green or red: the full axe result, and
    // for every serious finding the element itself - outlined, photographed
    // on its own and in the page, with the styles the browser computed for
    // it at that moment. A screenshot of the page alone says nothing about
    // contrast (anti-aliasing mixes the colours, the state may have changed
    // since the check); this ties the picture to the element and the values
    // axe measured.
    // Written as files next to the test's other results (test-results/…, part
    // of the uploaded artifact) and attached to the report.
    const evidence = async (name: string, body: string | Buffer, contentType: string): Promise<void> => {
      const file = testInfo.outputPath(name);
      fs.writeFileSync(file, body);
      await testInfo.attach(name, { path: file, contentType });
    };
    // Without the passes (megabytes): the findings, and what axe could not
    // decide ("incomplete" - for contrast, e.g. a background it could not
    // determine), with the browser and viewport it ran in.
    await evidence('axe-results.json', JSON.stringify({
      url: results.url,
      timestamp: results.timestamp,
      testEnvironment: results.testEnvironment,
      violations: results.violations,
      incomplete: results.incomplete,
    }, null, 2), 'application/json');
    let finding = 0;
    for (const violation of serious) {
      for (const node of violation.nodes) {
        finding++;
        const selector = typeof node.target[0] === 'string' ? node.target[0] : null;
        if (!selector) {
          continue;
        }
        const element = page.locator(selector).first();
        const styles = await element.evaluate((el) => {
          const css = getComputedStyle(el);
          (el as HTMLElement).style.outline = '3px solid magenta';
          (el as HTMLElement).style.outlineOffset = '2px';
          return {
            color: css.color,
            backgroundColor: css.backgroundColor,
            opacity: css.opacity,
            fontFamily: css.fontFamily,
            fontSize: css.fontSize,
            fontWeight: css.fontWeight,
            text: (el.textContent || '').trim().slice(0, 120),
          };
        }).catch((error) => ({ error: String(error) }));
        await evidence(`finding-${finding}-${violation.id}.json`, JSON.stringify({
          rule: violation.id, target: node.target, html: node.html,
          measured: node.any?.[0]?.data ?? null, computed: styles,
        }, null, 2), 'application/json');
        const picture = await element.screenshot({ timeout: 5_000 }).catch(() => null);
        if (picture) {
          await evidence(`finding-${finding}-${violation.id}-element.png`, picture, 'image/png');
        }
      }
    }
    if (finding) {
      await evidence('findings-in-page.png', await page.screenshot({ fullPage: true }), 'image/png');
    }
    expect(details, details.join('\n')).toEqual([]);
  });
});
