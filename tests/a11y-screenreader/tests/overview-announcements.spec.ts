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
 * ADELE-SR-01 - what NVDA actually says about the learning path overview.
 *
 * The accessibility work for #575 B2 gave the icon-only controls a name.
 * Jest proves the attribute is in the markup and axe-core proves no rule is
 * broken; neither proves that a screen reader reads the name out. This does:
 * NVDA walks the page and the test reads its spoken phrase log.
 *
 * The log is attached to the report, so a failure can be read rather than
 * guessed at - and a passing run leaves a record of what a blind user would
 * have heard.
 *
 * Windows and NVDA only - and strictly so: importing @guidepup/playwright on
 * any other platform throws "No available supported screen readers" while the
 * file is being loaded, before a single test is collected. There is therefore
 * no skip to write here, and this directory must never be pulled into another
 * suite's testDir: it would take that suite down with it on Linux.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { nvdaTest } from '@guidepup/playwright';
import { env, loginAs } from '../support/env';

const { expect } = nvdaTest;

/** How many reading steps one page is walked. Enough for the overview. */
const READING_STEPS = 40;

nvdaTest.describe('ADELE-SR-01 — the overview is readable with NVDA', () => {
  nvdaTest('the controls announce what they do, not just that they are buttons', async ({
    page,
    nvda,
  }, testInfo) => {
    await nvdaTest.step('open the overview as somebody who may edit', async () => {
      // Logging in through the backend, as in the other suites: the login
      // form is not what this test is about, and reading it out would fill
      // the log with noise.
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto('/local/adele/index.php');
      await expect(page.locator('[id^="local-adele-app"]')).toBeVisible({ timeout: 60_000 });
      // The overview is a Vue application; wait for a card, not for a timer.
      await expect(page.locator('[data-testid^="learningpath-row-"]').first())
        .toBeVisible({ timeout: 60_000 });
    });

    await nvdaTest.step('let NVDA read the page', async () => {
      await nvda.navigateToWebContent();
      await nvda.clearSpokenPhraseLog();

      for (let step = 0; step < READING_STEPS; step++) {
        await nvda.next();
      }
    });

    const spoken = await nvda.spokenPhraseLog();

    // Attached whatever the outcome: on a failure it is the evidence, on a
    // pass it is the documentation.
    await testInfo.attach('nvda-spoken-phrases.txt', {
      body: spoken.join('\n'),
      contentType: 'text/plain',
    });

    const log = spoken.join(' | ');

    await nvdaTest.step('the visibility toggle is announced by name and state', async () => {
      // #575 B2: the control used to be a link with a tooltip, which a screen
      // reader announces as an unnamed link. It is now a toggle button whose
      // name carries the learning path.
      expect(log, 'NVDA must name the visibility control').toMatch(/Visible to users/i);
      expect(log, 'and announce its pressed state').toMatch(/pressed|not pressed/i);
    });

    await nvdaTest.step('the icon-only controls are not nameless', async () => {
      for (const control of ['Edit', 'Delete', 'Duplicate']) {
        expect(log, `NVDA must name the ${control} control`).toContain(control);
      }
      // A button with no name is read as "button" with nothing in front of
      // it; the names above are what keeps that from happening.
      expect(log, 'the create control must be reachable and named')
        .toMatch(/Add a new learning path|Lernpfad erstellen/i);
    });
  });
});
