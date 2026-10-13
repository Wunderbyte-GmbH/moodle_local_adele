import path from 'node:path';
import { defineConfig, devices, ReporterDescription } from '@playwright/test';
import { screenReaderConfig } from '@guidepup/playwright';

/**
 * Screen reader suite for local_adele (E2E test plan, accessibility chains).
 *
 * This is the only suite that checks what a screen reader actually SAYS.
 * Everything else - including the axe-core run in the smoke suite - checks
 * the markup and can at best predict an announcement; here NVDA reads the
 * page and the test reads NVDA's spoken phrase log.
 *
 * Consequences, all of them from Guidepup's own requirements:
 * - Windows only. NVDA does not exist elsewhere, and @guidepup/playwright
 *   throws while being imported on any other platform - this config cannot
 *   even be loaded on Linux, which is why the suite has its own workflow and
 *   its own directory.
 * - Not headless and never parallel: a screen reader is a single instance
 *   that attaches to a visible desktop session. screenReaderConfig carries
 *   those settings; they are spread in below rather than copied.
 * - Its own Playwright version: @guidepup/playwright requires 1.57 or newer,
 *   while the other suites are pinned to 1.49.1. Separate directory,
 *   separate lockfile, no interference.
 *
 * Slow by nature - NVDA speaks in real time - so the timeouts are generous.
 */

/** Where a run leaves its evidence; set by the workflow. */
const exportDir = process.env.ADELE_A11Y_EXPORT_DIR
  ? path.resolve(process.env.ADELE_A11Y_EXPORT_DIR)
  : __dirname;

const reporter: ReporterDescription[] = process.env.CI
  ? [
      ['list'],
      ['html', { open: 'never', outputFolder: path.join(exportDir, 'playwright-report') }],
      ['json', { outputFile: path.join(exportDir, 'results.json') }],
      ['junit', { outputFile: path.join(exportDir, 'junit.xml') }],
    ]
  : [['list']];

export default defineConfig({
  ...screenReaderConfig,
  testDir: './tests',
  outputDir: path.join(exportDir, 'test-results'),
  // NVDA speaks at human speed, and every navigation step waits for it.
  timeout: 15 * 60_000,
  expect: { timeout: 30_000 },
  forbidOnly: !!process.env.CI,
  // No retries: a screen reader run that only passes on the second attempt
  // says nothing about what a person would have heard on the first.
  retries: 0,
  reporter,
  use: {
    ...screenReaderConfig.use,
    baseURL: process.env.ADELE_BASE_URL,
    // Video for every test, passed or failed. For this suite the recording is
    // the point: it is the only thing that lets a sighted reviewer follow
    // what the screen reader did, and the plan asks for the evidence of a
    // green run as well.
    video: 'on',
    screenshot: 'on',
    trace: 'retain-on-failure',
    ignoreHTTPSErrors: true,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'], channel: 'chrome' } },
  ],
});
