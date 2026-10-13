import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { Locator, Page, expect } from '@playwright/test';

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
 * Moodle's answers that do mean "no access" when they come as an error page
 * rather than as the enrolment page. Anything else on an error page is a
 * failure of the test, not a refusal.
 */
const REFUSALS = [
  /currently unavailable|derzeit nicht verfügbar|zur zeit nicht verfügbar/i, // coursehidden
  /not enrolled|nicht eingeschrieben/i,
];

/**
 * The person's enrolments in a course, read on the server - independent of
 * what the browser shows.
 *
 * @param user The person: username, or user id as a number.
 * @param courseid The course.
 * @returns username; siteadmin; active: whether Moodle counts them as
 *          actively enrolled right now; methods: every enrolment with its
 *          method and status.
 */
export function enrolmentState(user: string | number, courseid: string): {
  username: string;
  siteadmin: boolean;
  active: boolean;
  methods: { enrol: string; userstatus: number; instancestatus: number; timestart: number; timeend: number }[];
} {
  const script = `
    define('CLI_SCRIPT', true);
    require('config.php');
    $field = ctype_digit($argv[1]) ? 'id' : 'username';
    $user = $DB->get_record('user', [$field => $argv[1], 'deleted' => 0], 'id, username', MUST_EXIST);
    $context = context_course::instance((int) $argv[2]);
    $methods = $DB->get_records_sql(
      "SELECT ue.id, e.enrol, ue.status AS userstatus, e.status AS instancestatus, ue.timestart, ue.timeend
         FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
        WHERE ue.userid = ? AND e.courseid = ?", [$user->id, (int) $argv[2]]);
    echo json_encode([
      'username' => $user->username,
      'siteadmin' => is_siteadmin($user->id),
      'active' => is_enrolled($context, $user->id, '', true),
      'methods' => array_values(array_map(fn($m) => [
        'enrol' => $m->enrol, 'userstatus' => (int) $m->userstatus, 'instancestatus' => (int) $m->instancestatus,
        'timestart' => (int) $m->timestart, 'timeend' => (int) $m->timeend], $methods)),
    ]);`;
  const out = execFileSync('php', ['-r', script, '--', String(user), courseid], {
    cwd: fixture('ADELE_MOODLE_ROOT'),
    encoding: 'utf8',
    // Same clock as the web server: an enrolment that starts "now" must be
    // judged by the same now.
    env: { ...process.env, ...serverTimeEnv() },
  });
  return JSON.parse(out.trim().split('\n').pop() || '{}');
}

/**
 * What happened when this user opened this course.
 *
 * Strict on purpose (E2E issue, section 3A): "open" needs the course page OF
 * THIS COURSE, rendered for a person who is not a site administrator and
 * whom the server counts as actively enrolled; "enrolment-page" needs
 * Moodle's enrolment page for this course and a server that agrees there is
 * no active enrolment; "denied" needs one of the known refusal messages.
 * Everything else - a lost session, a server error, a foreign course, an
 * unknown error page, a contradiction between browser and server - fails
 * the test instead of passing as a refusal.
 *
 * @param page The page, already authenticated as the person under test.
 * @param courseid The course to open.
 * @returns 'open', or which refusal.
 */
export async function courseAccess(
  page: Page,
  courseid: string
): Promise<'open' | 'enrolment-page' | 'denied'> {
  const response = await page.goto(`/course/view.php?id=${courseid}`);
  const where = `course ${courseid}`;
  if (!response) {
    throw new Error(`${where}: no response at all`);
  }
  if (response.status() >= 500) {
    throw new Error(`${where}: server error ${response.status()}`);
  }
  const url = new URL(page.url());
  if (url.pathname.startsWith('/login/')) {
    throw new Error(`${where}: landed on the login page - the session is gone, that is no refusal`);
  }
  const body = page.locator('body');
  const bodyid = (await body.getAttribute('id')) || '';
  const bodyclass = ((await body.getAttribute('class')) || '').split(/\s+/);
  // Who is logged in, as the page itself says (Moodle's M.cfg), and what the
  // server knows about that person and this course.
  const cfg = await page.evaluate(() => {
    const m = (window as unknown as { M?: { cfg?: { userId?: number; courseId?: number } } }).M;
    return { userId: Number(m?.cfg?.userId || 0), courseId: Number(m?.cfg?.courseId || 0) };
  }).catch(() => ({ userId: 0, courseId: 0 }));
  if (!cfg.userId) {
    throw new Error(`${where}: nobody is logged in on this page (${page.url()})`);
  }
  const server = enrolmentState(cfg.userId, courseid);
  if (server.siteadmin) {
    throw new Error(`${where}: checked as a site administrator, who may enter every course - that proves nothing`);
  }

  if (url.pathname === '/enrol/index.php') {
    if (url.searchParams.get('id') !== courseid || bodyid !== 'page-enrol-index') {
      throw new Error(`${where}: an enrolment page, but not this course's (${page.url()})`);
    }
    if (server.active) {
      throw new Error(`${where}: the browser shows the enrolment page, the server counts ${server.username} as enrolled `
        + JSON.stringify(server.methods));
    }
    return 'enrolment-page';
  }
  const error = page.locator('.errorbox');
  if (await error.count()) {
    const text = ((await error.first().innerText().catch(() => '')) || '').replace(/\s+/g, ' ').trim();
    if (REFUSALS.some((refusal) => refusal.test(text))) {
      return 'denied';
    }
    throw new Error(`${where}: Moodle error page that is no known refusal: "${text.slice(0, 200)}"`);
  }
  if (url.pathname === '/course/view.php' && url.searchParams.get('id') === courseid
      && bodyid.startsWith('page-course-view') && bodyclass.includes(`course-${courseid}`)
      && String(cfg.courseId) === courseid) {
    if (!server.active) {
      throw new Error(`${where}: the browser shows the course, the server does not count ${server.username} as enrolled `
        + JSON.stringify(server.methods));
    }
    return 'open';
  }
  throw new Error(`${where}: unexpected page ${page.url()} (body ${bodyid})`);
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
    return await accessThroughAdele(page, courseid);
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
 * Assert that this person really can work in this course - through ADELE.
 *
 * @param page The page, authenticated as the person under test.
 * @param courseid The course.
 * @param because What this proves, for the failure message.
 */
export async function expectCourseOpen(page: Page, courseid: string, because: string): Promise<void> {
  expect(await accessThroughAdele(page, courseid), because).toBe('open');
}

/**
 * courseAccess, plus: an "open" course has to be open through ADELE.
 *
 * A course the learner reaches through some other enrolment - a manual one
 * left over from a fixture, a cohort, guest access - proves nothing about
 * the path (E2E issue, section 3A: "ADELE- vs. Fremdeinschreibung"). The
 * person must hold an ADELE enrolment that is active on both levels: the
 * user enrolment and the enrol instance.
 *
 * @param page The page, authenticated as the person under test.
 * @param courseid The course.
 * @returns What courseAccess returned, or 'open-not-through-adele'.
 */
export async function accessThroughAdele(page: Page, courseid: string): Promise<string> {
  const access = await courseAccess(page, courseid);
  if (access !== 'open') {
    return access;
  }
  const userid = await page.evaluate(() =>
    Number((window as unknown as { M?: { cfg?: { userId?: number } } }).M?.cfg?.userId || 0));
  if (!userid) {
    return 'open-but-nobody-logged-in';
  }
  const server = enrolmentState(userid, courseid);
  const adele = server.methods.some((m) => m.enrol === 'adele' && m.userstatus === 0 && m.instancestatus === 0);
  return adele ? 'open' : 'open-not-through-adele';
}

/**
 * Assert that this person cannot get into this course.
 *
 * Only a recognised refusal counts (see courseAccess); anything unexpected
 * already fails inside courseAccess.
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
  // Moodle's manual completion button, by what it does - not by its label:
  // the label is "Mark Announcements as done" for one learner and
  // "Ankündigungen als erledigt kennzeichnen" for the next (chain F has a
  // learner whose language is German).
  const toggle = 'button[data-action="toggle-manual-completion"]';
  const mark = page.locator(`${toggle}[data-toggletype="manual:mark-done"]`).first();
  const done = page.locator(`${toggle}[data-toggletype="manual:undo"]`).first();
  await expect(mark, `course ${courseid} must offer its completion activity to the learner`).toBeVisible({ timeout: 30_000 });
  // The button is server-rendered; its click handler arrives with Moodle's
  // JavaScript a moment later, and a click before that does nothing at all.
  // So: let the page settle, click, and click again ONLY while the button
  // still offers "mark as done" - a second click on a toggled button
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

/** What a learner can read on one node of their path. */
export interface NodeTexts {
  /** data-status of the node: accessible, completed, not_accessible, closed. */
  status: string;
  /** The information card behind the i symbol. */
  info: string;
  /** The feedback area behind the speech bubble. */
  feedback: string;
  /** Colour of the speech bubble - the node's state as the button shows it. */
  colour: string;
}

/**
 * Everything one node tells the learner: its state, the information card
 * (i symbol) and the feedback area (speech bubble), with white space
 * normalised.
 *
 * Both open only on a click on their icon, and the canvas may clip the icon -
 * so the click event is dispatched to it directly: same Vue handler, no
 * visible spot needed.
 *
 * @param page The page, logged in as the learner.
 * @param hostcourse The course embedding the path.
 * @param activityname The embedding activity's name.
 * @param nodeid The node, e.g. "dndnode_2".
 */
export async function nodeTexts(page: Page, hostcourse: string, activityname: string, nodeid: string): Promise<NodeTexts> {
  const status = await nodeStatus(page, hostcourse, activityname, nodeid);
  const wrapper = page.locator(`.vue-flow__node[data-id="${nodeid}"]`);
  const squeeze = (text: string): string => text.replace(/\s+/g, ' ').trim();
  await wrapper.locator('.information').first().dispatchEvent('click');
  const card = wrapper.locator('.additional-card');
  await expect(card, `${nodeid} must offer its information card`).toBeVisible({ timeout: 10_000 });
  const info = squeeze(await card.innerText());
  const bubble = wrapper.locator('.toggle-button').first();
  await bubble.dispatchEvent('click');
  const area = wrapper.locator('.selectable');
  await expect(area, `${nodeid} must offer its feedback area`).toBeVisible({ timeout: 10_000 });
  const feedback = squeeze(await area.innerText());
  const colour = await bubble.locator('i').first().evaluate((icon) => getComputedStyle(icon).color);
  return { status, info, feedback, colour };
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

/**
 * Click a learning path's save button and wait for the SERVER's answer.
 *
 * The editor goes back to the overview and requests the list before the save
 * has answered, so "the overview is visible again" is no proof that the save
 * is through - a list loaded too early shows the old state for good. This
 * waits for the response of local_adele_save_learningpath itself and
 * requires it to report no error.
 *
 * @param page The page.
 * @param save The save button.
 */
export async function saveLearningPath(page: Page, save: Locator): Promise<void> {
  const response = page.waitForResponse((r) => r.url().includes('local_adele_save_learningpath'), { timeout: 30_000 });
  await save.click();
  const body = JSON.parse(await (await response).text());
  expect(body?.[0]?.error, `the server must accept the save: ${JSON.stringify(body?.[0]?.exception ?? '')}`).toBe(false);
}

/**
 * Wait until the learning path editor has finished loading.
 *
 * The editor loads the path twice when it is opened from the overview
 * (LearningPathList.editLearningpath, then LearningpathsEdit.showForm), and a
 * late second answer overwrites what was typed into the title meanwhile -
 * a product defect, kept visible by its own fixme test
 * (editor-title-overwrite.spec.ts). Chains about something else wait here,
 * so that defect cannot turn them red at random.
 *
 * @param page The page.
 */
export async function waitForEditorLoaded(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle');
}

/**
 * As a teacher: open a learner's path from the activity's user list and set
 * one of the two master checkboxes on the given nodes, then save.
 *
 * Both checkboxes sit behind the disclosure button "Master checkboxes" in
 * the teacher's view of the learner's path (MasterConditions.vue). The canvas
 * may clip a node's controls, so the clicks are dispatched to the elements
 * directly - same Vue handlers, no visible spot needed.
 *
 * @param page The page, logged in as somebody who may edit the learner's path.
 * @param host The course embedding the path.
 * @param activity The embedding activity's name.
 * @param learner A text that identifies the learner's row (e.g. the last name).
 * @param nodes Node ids, e.g. ["dndnode_1"].
 * @param box "Master access checkbox" or "Master completion checkbox".
 * @param checked Whether the box is to be ticked.
 */
export async function setMasterCheckbox(
  page: Page,
  host: string,
  activity: string,
  learner: string,
  nodes: string[],
  box: 'Master access checkbox' | 'Master completion checkbox',
  checked: boolean
): Promise<void> {
  await page.goto(`/course/view.php?id=${host}`);
  await page.getByRole('main').getByRole('link', { name: activity }).click();
  await page.locator('tr').filter({ hasText: learner }).first().getByRole('link').first().click();
  for (const nodeid of nodes) {
    const node = page.locator(`[data-testid="learningpath-node-${nodeid}"]`);
    await expect(node).toBeVisible({ timeout: 60_000 });
    const checkbox = node.getByLabel(box);
    if (!(await checkbox.isVisible())) {
      await node.getByRole('button', { name: 'Master checkboxes' }).dispatchEvent('click');
    }
    await expect(checkbox, `the teacher must be offered the ${box} on ${nodeid}`).toBeVisible();
    if ((await checkbox.isChecked()) !== checked) {
      await checkbox.dispatchEvent('click');
    }
    await expect(checkbox).toBeChecked({ checked });
  }
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByText('Learning path saved')).toBeVisible({ timeout: 30_000 });
  drainTaskQueue();
}

/**
 * A learner's enrolment in a course as an admin sees it on the participants
 * page: "Active", "Suspended" or "not listed".
 *
 * @param page The page, logged in as admin.
 * @param courseid The course.
 * @param learner A text that identifies the learner's row.
 */
export async function enrolmentStatus(page: Page, courseid: string, learner: string): Promise<string> {
  await page.goto(`/user/index.php?id=${courseid}&perpage=5000`);
  const row = page.locator('table#participants tbody tr').filter({ hasText: learner });
  if (!(await row.count())) {
    return 'not listed';
  }
  const text = (await row.first().innerText()).replace(/\s+/g, ' ');
  return /Suspended/.test(text) ? 'Suspended' : /Active/.test(text) ? 'Active' : text;
}

/** One row of the participant list of an embedded path (UserList.vue). */
export interface ParticipantRow {
  /** Last name. */
  name: string;
  /** Percentage as the progress bar announces it (aria-valuenow). */
  percent: string;
  /** Best route, "completed/total". */
  route: string;
  /** Rank. */
  rank: string;
  /** Whether the row links to the learner's detail view (teacher view only). */
  link: boolean;
}

/**
 * Suspend or reactivate somebody's enrolment in a course on Moodle's own
 * participants page - what a teacher does to take a participant out without
 * unenrolling them.
 *
 * @param page The page, logged in as somebody who may edit enrolments.
 * @param courseid The course.
 * @param person A text that identifies the person's row.
 * @param status The new status.
 */
export async function setEnrolmentStatus(
  page: Page,
  courseid: string,
  person: string,
  status: 'Active' | 'Suspended'
): Promise<void> {
  await page.goto(`/user/index.php?id=${courseid}&perpage=5000`);
  const row = page.locator('table#participants tbody tr').filter({ hasText: person }).first();
  await expect(row, `${person} must be listed in course ${courseid}`).toBeVisible({ timeout: 30_000 });
  await page.waitForLoadState('networkidle');
  await row.locator('a[data-action="editenrolment"]').first().click();
  const dialog = page.getByRole('dialog').filter({ has: page.locator('select[name="status"]') });
  await expect(dialog).toBeVisible({ timeout: 30_000 });
  await dialog.locator('select[name="status"]').selectOption({ label: status });
  await dialog.getByRole('button', { name: 'Save changes' }).click();
  await expect(dialog).toBeHidden({ timeout: 30_000 });
  await expect
    .poll(() => enrolmentStatus(page, courseid, person), { timeout: 30_000, intervals: [1_000, 2_000] })
    .toBe(status);
}

/**
 * The participant list as the page shows it right now, one line per row:
 * "<last name> | <percent> | <route> | rank <rank>", with " | link" where the
 * row leads to the learner's detail view.
 *
 * Read from the END of each row: the teacher's view has a leading ID column
 * the participant's view lacks.
 *
 * @param page A page showing an embedded path.
 */
export async function participantList(page: Page): Promise<string[]> {
  const rows: ParticipantRow[] = await page.locator('#adele-userlist-table tbody tr').evaluateAll((trs) =>
    trs.map((tr) => {
      const cells = Array.from(tr.querySelectorAll('td'));
      const text = (index: number) => (cells[cells.length - index]?.textContent || '').trim();
      const bar = cells[cells.length - 3]?.querySelector('[role="progressbar"]');
      return {
        name: text(4),
        percent: bar?.getAttribute('aria-valuenow') || '',
        route: text(2),
        rank: text(1),
        link: Boolean(tr.querySelector('a')),
      };
    })
  );
  return rows.map((row) => `${row.name} | ${row.percent} | ${row.route} | rank ${row.rank}${row.link ? ' | link' : ''}`);
}

/**
 * Call a Moodle web service the way the page's own JavaScript does: with the
 * session of the person logged in, through lib/ajax/service.php.
 *
 * For the negative tests of the plan: what the interface does not offer may
 * still be asked for directly, and what the server answers THEN is the
 * boundary - not the absence of a button.
 *
 * @param page The page, logged in as the person asking.
 * @param methodname The web service function.
 * @param args Its arguments.
 * @returns The data, or the error code the server refused with.
 */
export async function callService(
  page: Page,
  methodname: string,
  args: Record<string, unknown>
): Promise<{ data?: unknown; error?: string }> {
  const ready = () => page.evaluate(() => Boolean((window as unknown as { M?: { cfg?: { sesskey?: string } } }).M?.cfg?.sesskey))
    .catch(() => false);
  if (!(await ready())) {
    // Any page of the site carries the session key.
    await page.goto('/user/preferences.php');
  }
  return page.evaluate(async ([method, parameters]) => {
    const sesskey = (window as unknown as { M: { cfg: { sesskey: string } } }).M.cfg.sesskey;
    const response = await fetch(`/lib/ajax/service.php?sesskey=${sesskey}&info=${method}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify([{ index: 0, methodname: method, args: parameters }]),
    });
    const answer = (await response.json())[0];
    if (answer.error) {
      return { error: String(answer.exception?.errorcode || 'error') };
    }
    return { data: answer.data };
  }, [methodname, args] as const);
}

/**
 * The completion of an activity as the person logged in sees it on the course
 * page: Moodle's button ("To do" / "Done") and the conditions behind it, e.g.
 * "Done | Done: Finish the learning path".
 *
 * @param page The page, logged in as the learner.
 * @param courseid The course.
 * @param activityname The activity's name.
 */
export async function activityCompletion(page: Page, courseid: string, activityname: string): Promise<string> {
  await page.goto(`/course/view.php?id=${courseid}`);
  const info = page.locator(`[data-region="activity-information"][data-activityname="${activityname}"]`).first();
  await expect(info, `the course page must show the completion of "${activityname}"`).toBeVisible({ timeout: 30_000 });
  const clean = (text: string) => text.replace(/\s+/g, ' ').trim();
  const button = clean(await info.locator('button.dropdown-toggle').first().innerText());
  // The conditions sit in a closed dropdown: read the text, not the rendering.
  const conditions = await info.locator('.completion-dialog [role="listitem"]')
    .evaluateAll((items) => items.map((item) => (item.textContent || '').replace(/\s+/g, ' ').trim()));
  return `${button} | ${conditions.join(' ; ')}`;
}

/**
 * The completion of an activity for one person as the teacher's activity
 * completion report states it: "Completed" or "Not completed".
 *
 * @param page The page, logged in as somebody who may see the report.
 * @param courseid The course.
 * @param activityname The activity's name.
 * @param person A text that identifies the person's row (e.g. the last name).
 */
export async function completionReport(page: Page, courseid: string, activityname: string, person: string): Promise<string> {
  await page.goto(`/report/progress/index.php?course=${courseid}`);
  const cell = page.locator('tr').filter({ hasText: person }).first()
    .locator(`[data-activityname="${activityname}"] img`).first();
  await expect(cell, `the report must list ${person} for "${activityname}"`).toBeVisible({ timeout: 30_000 });
  const alt = (await cell.getAttribute('alt')) || '';
  // "<person>, <activity>: Completed <date>" or "...: Not completed" - the
  // latter with a date as well once a completion has been taken back.
  const state = alt.slice(alt.lastIndexOf(`${activityname}: `) + activityname.length + 2).trim();
  for (const known of ['Not completed', 'Completed']) {
    if (state.startsWith(known)) {
      return known;
    }
  }
  return state;
}

/**
 * As somebody who may assign roles: set a person's roles in a course on the
 * participants page - Moodle's own inline editor, the way a teacher or
 * manager changes somebody's role without touching the enrolment.
 *
 * The editor is an autocomplete; what gets saved is its hidden <select>, so
 * that is what is checked before saving (a click that did not arrive must
 * not pass as a role change).
 *
 * @param page The page, logged in.
 * @param courseid The course.
 * @param person A text that identifies the person's row.
 * @param roles The complete set of role names the person is to have, e.g. ["Non-editing teacher", "Student"].
 */
export async function setCourseRoles(page: Page, courseid: string, person: string, roles: string[]): Promise<void> {
  await page.goto(`/user/index.php?id=${courseid}&perpage=5000`);
  const row = page.locator('table#participants tbody tr').filter({ hasText: person }).first();
  await expect(row, `${person} must be listed in course ${courseid}`).toBeVisible({ timeout: 30_000 });
  const cell = row.locator('[data-itemtype="user_roles"]');
  await page.waitForLoadState('networkidle');
  await cell.locator('a[data-inplaceeditablelink]').click();
  const select = cell.locator('select.form-autocomplete-original-select');
  await expect(select).toBeAttached({ timeout: 15_000 });
  const chosen = () => select.locator('option:checked').allInnerTexts().then((texts) => texts.map((text) => text.trim()).sort());
  // Take away what is not wanted ...
  for (const current of await chosen()) {
    if (!roles.includes(current)) {
      await cell.locator('.form-autocomplete-selection [role="option"]').filter({ hasText: current }).first().click();
    }
  }
  // ... and add what is missing.
  const input = cell.locator('input[id^="form_autocomplete_input"]');
  for (const role of roles) {
    for (let attempt = 0; attempt < 3 && !(await chosen()).includes(role); attempt++) {
      await input.click();
      await input.fill('');
      await input.pressSequentially(role, { delay: 20 });
      const suggestion = cell.locator('.form-autocomplete-suggestions [role="option"]')
        .filter({ hasText: new RegExp(`^\\s*${role}\\s*$`) }).first();
      await suggestion.waitFor({ state: 'visible', timeout: 5_000 }).catch(() => undefined);
      if (await suggestion.isVisible()) {
        await suggestion.click();
      }
    }
  }
  expect(await chosen(), `the roles selected for ${person}`).toEqual([...roles].sort());
  await cell.getByRole('link', { name: 'Save changes' }).click();
  await expect(cell.locator('select'), 'the role editor must close after saving').toHaveCount(0, { timeout: 15_000 });
  for (const role of roles) {
    await expect(cell, `${person} must now be shown as ${role}`).toContainText(role);
  }
}

/**
 * Give or take a SYSTEM role by hand, on Moodle's own page (Site
 * administration > Users > Permissions > Assign system roles).
 *
 * This is the route an administrator has for taking the ADELE assistant
 * role away again: the plugin assigns it automatically and, by decision
 * (local_adele #503/#509), never revokes it.
 *
 * @param page The page, logged in as an administrator.
 * @param rolename The role as the page names it, e.g. "Adele Assistant".
 * @param person A text that identifies the person in the lists (name or e-mail).
 * @param held Whether the person is to hold the role afterwards.
 */
export async function setSystemRole(page: Page, rolename: string, person: string, held: boolean): Promise<void> {
  await page.goto('/admin/roles/assign.php?contextid=1');
  await page.getByRole('link', { name: rolename, exact: true }).first().click();
  const holders = page.locator('select#removeselect');
  const others = page.locator('select#addselect');
  await expect(holders).toBeVisible({ timeout: 30_000 });
  const holds = async (): Promise<boolean> => (await holders.locator('option').filter({ hasText: person }).count()) > 0;
  if ((await holds()) !== held) {
    const [list, search, button] = held
      ? [others, page.locator('#addselect_searchtext'), page.locator('#add')]
      : [holders, page.locator('#removeselect_searchtext'), page.locator('#remove')];
    // Searched for: a long list is not shown in full.
    await search.fill(person);
    const option = list.locator('option').filter({ hasText: person });
    await expect(option, `${person} must be offered in the list`).toHaveCount(1, { timeout: 30_000 });
    await list.selectOption({ label: (await option.innerText()).trim() });
    await button.click();
    await expect(holders).toBeVisible({ timeout: 30_000 });
  }
  expect(await holds(), `${person} ${held ? 'must hold' : 'must no longer hold'} the system role ${rolename}`).toBe(held);
}

/**
 * Take somebody out of a course on Moodle's participants page.
 *
 * @param page The page, logged in as somebody who may unenrol.
 * @param courseid The course.
 * @param person A text that identifies the person's row.
 */
export async function unenrolFromCourse(page: Page, courseid: string, person: string): Promise<void> {
  await page.goto(`/user/index.php?id=${courseid}&perpage=5000`);
  const row = page.locator('table#participants tbody tr').filter({ hasText: person });
  await expect(row, `${person} must be listed in course ${courseid}`).toHaveCount(1, { timeout: 30_000 });
  await page.waitForLoadState('networkidle');
  await row.locator('a[data-action="unenrol"]').first().click();
  const dialog = page.locator('.modal.show');
  await expect(dialog).toBeVisible({ timeout: 15_000 });
  await dialog.getByRole('button', { name: /^(Unenrol|Abmelden)$/ }).click();
  await expect(page.locator('table#participants tbody tr').filter({ hasText: person }),
    `${person} must be gone from the participants of course ${courseid}`).toHaveCount(0, { timeout: 30_000 });
}
