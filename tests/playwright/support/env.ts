import { Page, expect } from '@playwright/test';

/**
 * Read a required environment variable.
 *
 * Throws rather than defaulting: every one of these comes from seed.php, and
 * a missing value means the seeding step did not run or its output was not
 * exported. Continuing with an empty string produces a browser test that
 * fails somewhere far from the real cause.
 */
export function required(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(
      `Environment variable ${name} is not set. It is produced by tests/playwright/seed.php; ` +
      `check that the seeding step ran and that its output reached the job environment.`
    );
  }
  return value;
}

export const env = {
  get baseUrl() { return required('ADELE_BASE_URL'); },
  get adminUser() { return required('ADELE_ADMIN_USER'); },
  get adminPassword() { return required('ADELE_ADMIN_PASSWORD'); },
  get learningPathName() { return required('ADELE_LP_NAME'); },
  get learningPathId() { return required('ADELE_LP_ID'); },

  // Fixture users and objects for the regression specs. All identifiers are
  // fixed, never randomised: several assertions are about the exact title.
  get fixturePassword() { return required('ADELE_FIXTURE_PASSWORD'); },
  get managerUsername() { return required('ADELE_MANAGER_USERNAME'); },
  get assistantUsername() { return required('ADELE_ASSISTANT_USERNAME'); },
  get visiblePathTitle() { return required('ADELE_VISIBLE_PATH_TITLE'); },
  get invisiblePathTitle() { return required('ADELE_INVISIBLE_PATH_TITLE'); },
  get visiblePathId() { return required('ADELE_VISIBLE_PATH_ID'); },
  get invisiblePathId() { return required('ADELE_INVISIBLE_PATH_ID'); },
  get visiblePathBTitle() { return required('ADELE_VISIBLE_PATH_B_TITLE'); },
  get invisiblePathBTitle() { return required('ADELE_INVISIBLE_PATH_B_TITLE'); },
  get collaboratorUsername() { return required('ADELE_COLLABORATOR_USERNAME'); },
  get t0Username() { return required('ADELE_T0_USERNAME'); },
  get navCourseUrl() { return required('ADELE_NAV_COURSE_URL'); },
};

/**
 * Authenticate the browser context as admin.
 *
 * Done over HTTP rather than by driving the login form, and that is a
 * correction of an earlier decision here. Driving the form looked appealing
 * as a free smoke test of the whole stack; in practice the page re-rendered
 * between filling the fields and submitting them, so Moodle received an empty
 * password and answered "Invalid login, please try again". Measured on this
 * environment: the form path failed in roughly three of four suite runs,
 * while the same credentials over HTTP succeeded eight times out of eight.
 *
 * A flaky step that tests nothing this suite is about is worse than no step,
 * so the login moved to the request API. page.context().request shares its
 * cookie jar with the context, so the page is authenticated afterwards.
 * Moodle's login form remains covered by core's own tests.
 */
export async function loginAsAdmin(page: Page): Promise<void> {
  await loginAs(page, env.adminUser, env.adminPassword);
}

/**
 * Authenticate the browser context as an arbitrary user.
 *
 * Each call starts from a clean cookie jar. A test that switches user must
 * not inherit the previous session: Moodle would keep serving the old one and
 * the assertion would silently describe the wrong person.
 *
 * @param page The page whose context is authenticated.
 * @param username The Moodle username.
 * @param password The password.
 */
export async function loginAs(page: Page, username: string, password: string): Promise<void> {
  // Leave the current page first: requests it still has in flight can set
  // the previous session's cookie after this login and silently undo it
  // (page and API share one cookie jar). See tests/e2e/support/env.ts.
  await page.goto('about:blank');
  const api = page.context().request;
  await page.context().clearCookies();

  const form = await api.get('/login/index.php');
  const token = /name="logintoken" value="([^"]+)"/.exec(await form.text())?.[1];
  if (!token) {
    throw new Error('No login token found on /login/index.php — is this a Moodle site?');
  }

  const response = await api.post('/login/index.php', {
    form: {
      anchor: '',
      logintoken: token,
      username,
      password,
    },
  });

  // A successful login redirects away from the login page; a rejected one
  // comes back to it with an error, so the final URL is the reliable signal.
  const landed = response.url();
  if (landed.includes('/login/index.php')) {
    throw new Error(`Login rejected for user "${username}". Moodle stayed on ${landed}.`);
  }
}
