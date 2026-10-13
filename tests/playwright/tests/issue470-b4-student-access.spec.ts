import { test, expect } from '@playwright/test';
import { env, loginAs } from '../support/env';

/**
 * ADELE-PW-470-B4 — a student's access: no "Learning Paths" entry in the
 * navigation, no administration by direct URL, and the path embedded in a
 * course activity read-only (local_adele PR #8 from the fork).
 *
 * The seed (seed.php) gives the student a host course with an ADELE activity
 * that embeds the seeded path (ADELE_B4_ACTIVITY_CMID).
 */
test.describe('ADELE-PW-470-B4 — Student access', () => {
  test('student does not see the Learning Paths navigation entry', async ({ page }) => {
    await loginAs(page, env.studentUsername, env.fixturePassword);

    // loginAs() authenticates via HTTP but does not navigate the browser page.
    // Open a real Moodle page so the absence assertion cannot pass on about:blank.
    await page.goto('/my/');
    await expect(page.locator('body#page-my-index')).toHaveCount(1);

    await expect(
      page.getByRole('link', { name: /^(Lernpfade|Learning Paths)$/i }),
    ).toHaveCount(0);
  });

  test('student is denied Learning Paths administration by direct URL', async ({ page }) => {
    await loginAs(page, env.studentUsername, env.fixturePassword);

    await page.goto('/local/adele/index.php#/learningpaths');

    const permissionDialog = page.getByRole('dialog', { name: 'nopermissions' });
    await expect(permissionDialog).toBeVisible();
    await expect(permissionDialog).toContainText(
      /Sorry, but you do not currently have permissions to do that/i,
    );
  });

  test('student sees the course learning path read-only', async ({ page }) => {
    await loginAs(page, env.studentUsername, env.fixturePassword);

    await page.goto(`/mod/adele/view.php?id=${env.b4ActivityCmid}`);

    // First prove that the embedded learning path really rendered.
    await expect(page.locator('[id^="local-adele-app"]')).toBeVisible();

    // B4: a student may view the path, but must not get editing controls.
    await expect(page.locator('#save-learning-path')).toHaveCount(0);
  });
});
