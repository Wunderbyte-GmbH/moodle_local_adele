import path from 'node:path';
import { defineConfig, devices, ReporterDescription } from '@playwright/test';

/**
 * End-to-end suite for the ADELE ecosystem (E2E test plan, section 22).
 *
 * Separate from tests/playwright on purpose. That suite is a smoke test: it
 * runs on every push and has to stay fast. This one drives whole chains -
 * role, editor, path state, runtime evaluation, feedback, enrolment, real
 * course access - across all three plugins, takes minutes rather than
 * seconds, and is started by hand from the Actions tab.
 *
 * Fixtures come from local_adele/tests/playwright/fixtures via
 * seed_fixtures.php. They ship with the repository, so a run is reproducible
 * (plan section 25, "Fixtures reproduzierbar").
 */

/** Where a run leaves its evidence; set by the workflow, per suite. */
const exportDir = process.env.ADELE_E2E_EXPORT_DIR
  ? path.resolve(process.env.ADELE_E2E_EXPORT_DIR)
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
  testDir: './tests',
  outputDir: path.join(exportDir, 'test-results'),
  // A chain test logs in as several people and waits for recomputes; the
  // smoke suite's 60 s would cut legitimate work short.
  timeout: 180_000,
  expect: { timeout: 15_000 },
  // One worker, no parallelism: every spec changes the state of one shared
  // site - enrolments, path definitions, completions. Two of them at once
  // would produce failures nobody can reproduce.
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  // No retries. A chain test that passes on the second attempt has left the
  // site in a state the first attempt created; the green run would be
  // meaningless. A flaky E2E test is a defect, in the test or in the product.
  retries: 0,
  reporter,
  use: {
    baseURL: process.env.ADELE_BASE_URL,
    // Video for every test, passed or failed: the plan asks for the recording
    // of a successful run as well, and these recordings double as
    // documentation of the intended journey.
    video: 'on',
    screenshot: 'on',
    trace: 'retain-on-failure',
    ignoreHTTPSErrors: true,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});
