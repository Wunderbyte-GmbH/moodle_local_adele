import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { Page, expect } from '@playwright/test';

/**
 * Helpers for the E2E chains.
 *
 * The plan (section 2) asks every user story to be confirmed on several
 * levels: interface, feedback, percentage, Moodle enrolment and REAL course
 * access. The last one is the point of these helpers - a row in the
 * participants list is not proof that the learner can open the course, and
 * a test that stops at the database would have passed through every
 * enrolment defect this project has had.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Read a fixture variable produced by seed_fixtures.php.
 *
 * @param name The variable name.
 * @returns Its value.
 */
export function fixture(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(
      `Environment variable ${name} is not set. It is produced by ` +
      `local_adele/tests/playwright/seed_fixtures.php; check that the fixture ` +
      `seeding step ran and that its output reached the job environment.`
    );
  }
  return value;
}

/** The reference path of the plan (section 4): T01 -> T02 -> T03. */
export const referencePath = {
  get id() { return fixture('ADELE_FIXTURE_PATH_LINEAR_A1'); },
  get learner() { return fixture('ADELE_FIXTURE_LEARNER_LINEAR_A1'); },
  get name() { return 'Linear A1'; },
};

/** A learner who is on no path at all - the negative control (section 2 G). */
export const controlUser = () => fixture('ADELE_FIXTURE_CONTROL_USER');

/** The id of a fixture course, by its shortname (T01, T02, ...). */
export const fixtureCourse = (shortname: string) =>
  fixture(`ADELE_FIXTURE_COURSE_${shortname.toUpperCase()}`);

/** The password every fixture account was given. */
export const fixturePassword = () => fixture('ADELE_FIXTURE_PASSWORD');

/**
 * What happened when this user opened this course.
 *
 * Moodle answers an unauthorised course view with a redirect to the
 * enrolment page, and a forbidden one with an exception page. Both mean "no
 * access", but they are different situations, so the outcome is reported
 * rather than asserted here.
 *
 * @param page The page, already authenticated as the person under test.
 * @param courseid The course to open.
 * @returns 'open' when the course page itself is shown, otherwise why not.
 */
export async function courseAccess(
  page: Page,
  courseid: string
): Promise<'open' | 'enrolment-page' | 'denied'> {
  await page.goto(`/course/view.php?id=${courseid}`);
  const url = page.url();
  if (url.includes('/enrol/index.php')) {
    return 'enrolment-page';
  }
  if (url.includes('/login/') || await page.locator('.errorbox, #notice').first().isVisible().catch(() => false)) {
    return 'denied';
  }
  return url.includes(`/course/view.php?id=${courseid}`) ? 'open' : 'denied';
}

/**
 * Run the ad-hoc task queue once.
 *
 * The effects these chains assert are produced by tasks, not by the click
 * that queues them. Waiting for cron is forbidden by the plan (section 20)
 * and proves nothing anyway - a timeout cannot tell a slow task from one
 * that never ran.
 */
export function drainTaskQueue(): void {
  execFileSync('php', ['-d', 'max_input_vars=5000', 'admin/cli/adhoc_task.php', '--execute'], {
    cwd: fixture('ADELE_MOODLE_ROOT'),
    encoding: 'utf8',
    // The task runner must see the same (possibly shifted) time as the web
    // server, or a task the server considers due would be refused here.
    env: { ...process.env, ...serverTimeEnv() },
  });
}

/**
 * Environment that puts a PHP process on the shared test clock.
 *
 * The test clock is libfaketime, preloaded into the PHP processes only - the
 * web server and the CLI task runner - and steered through one file. The
 * browser, the database and the test runner itself keep the real time.
 * Empty when the suite runs without a test clock.
 */
export function serverTimeEnv(): Record<string, string> {
  const lib = process.env.ADELE_FAKETIME_LIB;
  const file = process.env.ADELE_FAKETIME_FILE;
  if (!lib || !file) {
    return {};
  }
  return { LD_PRELOAD: lib, FAKETIME_TIMESTAMP_FILE: file, FAKETIME_NO_CACHE: '1' };
}

/**
 * Whether this run has a test clock at all.
 */
export const hasServerClock = (): boolean => Object.keys(serverTimeEnv()).length > 0;

/**
 * Move the server's clock to an absolute instant (Unix seconds).
 *
 * Written as an offset from the real time, so the server clock keeps running
 * forwards from there - a frozen clock would stall Moodle's own waiting loops.
 *
 * @param epoch The instant the server should see as "now".
 */
export function setServerTime(epoch: number): void {
  const file = process.env.ADELE_FAKETIME_FILE;
  if (!file) {
    throw new Error('No test clock: ADELE_FAKETIME_FILE is not set.');
  }
  const offset = Math.round(epoch - Date.now() / 1000);
  writeFileSync(file, `${offset >= 0 ? '+' : ''}${offset}s\n`);
}

/**
 * Put the server back on the real time.
 */
export function resetServerTime(): void {
  const file = process.env.ADELE_FAKETIME_FILE;
  if (file) {
    writeFileSync(file, '+0s\n');
  }
}

/**
 * Assert that access appears once the queued work has been done.
 *
 * One drain is not enough: a task can queue the next one, and parts of the
 * chain are scheduled with a deliberate delay (local_adele schedules with a
 * two-minute buffer, enrol_adele removes after five minutes). So the runner
 * is called repeatedly until the effect shows, bounded - this is running the
 * queue, not waiting for wall-clock time.
 *
 * @param page The page, authenticated as the person under test.
 * @param courseid The course.
 * @param because What this proves, for the failure message.
 */
export async function expectCourseOpenAfterTasks(
  page: Page,
  courseid: string,
  because: string
): Promise<void> {
  await expect.poll(async () => {
    drainTaskQueue();
    return await courseAccess(page, courseid);
  }, { message: because, timeout: 60_000, intervals: [1000, 2000, 3000, 5000] }).toBe('open');
}

/**
 * Assert that access is gone once the queued work has been done.
 *
 * The counterpart to expectCourseOpenAfterTasks: a withdrawal is produced by
 * tasks as well, so the check runs the queue until the access is gone,
 * bounded. Used after a change that should REMOVE access - never for a
 * person who never had it, where an immediate check is the honest one.
 *
 * @param page The page, authenticated as the person under test.
 * @param courseid The course.
 * @param because What this proves, for the failure message.
 */
export async function expectCourseClosedAfterTasks(
  page: Page,
  courseid: string,
  because: string
): Promise<void> {
  await expect.poll(async () => {
    drainTaskQueue();
    return await courseAccess(page, courseid);
  }, { message: because, timeout: 60_000, intervals: [1000, 2000, 3000, 5000] }).not.toBe('open');
}

/**
 * Assert that this person really can work in this course.
 *
 * @param page The page, authenticated as the person under test.
 * @param courseid The course.
 * @param because What this proves, for the failure message.
 */
export async function expectCourseOpen(page: Page, courseid: string, because: string): Promise<void> {
  expect(await courseAccess(page, courseid), because).toBe('open');
}

/**
 * Assert that this person cannot get into this course.
 *
 * Either answer counts as refused; what must not happen is the course page.
 *
 * @param page The page, authenticated as the person under test.
 * @param courseid The course.
 * @param because What this proves, for the failure message.
 */
export async function expectCourseClosed(page: Page, courseid: string, because: string): Promise<void> {
  expect(await courseAccess(page, courseid), because).not.toBe('open');
}

/**
 * Complete a course the way a learner does: open it and mark its completion
 * activity as done.
 *
 * Every fixture course (T01-T12) completes on ONE activity - its
 * "Announcements" forum, set to manual completion. The click records the
 * activity completion; the course completion itself is aggregated by
 * Moodle's completion task (runCompletion()).
 *
 * @param page The page, logged in as the learner.
 * @param courseid The course.
 */
export async function completeCourse(page: Page, courseid: string): Promise<void> {
  await page.goto(`/course/view.php?id=${courseid}`);
  // Moodle names the button after the activity for assistive technology:
  // "Mark Ankündigungen as done", afterwards "Ankündigungen is marked as
  // done. Press to undo." (core_course completion_manual:aria:*).
  const mark = page.getByRole('button', { name: /^Mark .+ as done$/ }).first();
  const done = page.getByRole('button', { name: /is marked as done/ }).first();
  await expect(mark, `course ${courseid} must offer its completion activity to the learner`).toBeVisible({ timeout: 30_000 });
  // The button is server-rendered; its click handler arrives with Moodle's
  // JavaScript a moment later, and a click before that does nothing at all.
  // So: let the page settle, click, and click again ONLY while the button
  // still offers "Mark ... as done" - a second click on a toggled button
  // would undo the completion.
  await page.waitForLoadState('networkidle');
  for (let attempt = 0; attempt < 3 && !(await done.isVisible()); attempt++) {
    if (await mark.isVisible()) {
      await mark.click();
    }
    await done.waitFor({ state: 'visible', timeout: 5_000 }).catch(() => undefined);
  }
  await expect(done, `the completion of course ${courseid} must be recorded`).toBeVisible({ timeout: 10_000 });
}

/**
 * Let the system act on completions: Moodle's completion task aggregates
 * course completion, then the queue of local_adele and enrol_adele runs.
 * Both under the same (possibly shifted) clock as the web server.
 */
export function runCompletion(): void {
  execFileSync(
    'php',
    ['-d', 'max_input_vars=5000', 'admin/cli/scheduled_task.php', '--execute=\\core\\task\\completion_regular_task'],
    { cwd: fixture('ADELE_MOODLE_ROOT'), encoding: 'utf8', env: { ...process.env, ...serverTimeEnv() } }
  );
  drainTaskQueue();
}

/**
 * The state of one node as the learner's own path view shows it
 * (data-status: accessible, completed, not_accessible, closed).
 *
 * @param page The page, logged in as the learner.
 * @param hostcourse The course embedding the path.
 * @param activityname The embedding activity's name.
 * @param nodeid The node, e.g. "dndnode_2".
 */
export async function nodeStatus(page: Page, hostcourse: string, activityname: string, nodeid: string): Promise<string> {
  await page.goto(`/course/view.php?id=${hostcourse}`);
  // In the page content: the course index beside it lists the same link.
  await page.getByRole('main').getByRole('link', { name: activityname }).click();
  const node = page.locator(`[data-testid="learningpath-node-${nodeid}"]`);
  await expect(node).toBeVisible({ timeout: 60_000 });
  return (await node.getAttribute('data-status')) || '';
}

/**
 * Whether this site has the catquiz ecosystem (seeded chain D).
 */
export const hasCatquiz = (): boolean => Boolean(process.env.ADELE_FIXTURE_D_CMID);

/**
 * Signal one finished CAT attempt with the given person ability - what a
 * real adaptive test leaves behind (attempt, catquiz result, completion
 * event), without playing one through. See support/catquiz_signal.php.
 *
 * @param username The learner.
 * @param ability The person ability on the fixture scale.
 */
export function catquizSignal(username: string, ability: number): void {
  execFileSync(
    'php',
    [
      '-d', 'max_input_vars=5000',
      'local/adele/tests/e2e/support/catquiz_signal.php',
      `--user=${username}`,
      `--cmid=${fixture('ADELE_FIXTURE_D_CMID')}`,
      `--scale=${fixture('ADELE_FIXTURE_D_SCALE')}`,
      `--ability=${ability}`,
    ],
    { cwd: fixture('ADELE_MOODLE_ROOT'), encoding: 'utf8', env: { ...process.env, ...serverTimeEnv(), ADELE_SEED_I_KNOW: '1' } }
  );
}
