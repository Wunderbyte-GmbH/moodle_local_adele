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
 * feedback-texts - chain F of the E2E plan (section 10), the part on the real
 * clock: what a learner READS on a node, state by state.
 *
 *   F1  the information card behind the i symbol
 *   F2  the feedback area behind the speech bubble, and the bubble's colour
 *
 * for the conditions whose EFFECT other chains test: predecessor (1 of 1,
 * 2 of 2), manual release, manual completion - each before it is met, met,
 * and, where it can be taken back, withdrawn. Plus the author's own text and
 * a learner whose language is German. The conditions on time, and with them
 * F3 (AND / OR), are in zz-time-feedback: they move the server clock.
 *
 * Every text asserted here was measured first (session 006, part 39). What
 * was measured and is WRONG is not written down as an expectation: it becomes
 * a "known defect" test at the end, which states what the learner should get
 * instead, fails, and is skipped in a normal run (support/feedback.ts:
 * knownDefect) - until the defect is fixed and the test moves up. So far:
 * mixed languages and the lost author's text (local_adele #589), the wording
 * of manual completion (#590) - all fixed and asserted above.
 *
 * Not covered: nested combinations (they need the editor's brackets, E2).
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect } from '@playwright/test';
import { loginAs } from '../support/env';
import { fixture, fixturePassword, nodeStatus } from '../support/fixtures';
import {
  COLOUR,
  DE,
  EN,
  expectNoRawPlaceholders,
  knownDefect,
  learnerCompletes,
  learnerReads,
  seeded,
  teacherSets,
} from '../support/feedback';

const PREDECESSOR_1 = 'Completion of the predecessor „Testkurs 01“';
const PREDECESSOR_2 = 'Completion of the predecessor „Testkurs 02“';
const INFO_COMPLETION = 'Completion criteria: • Complete this course.';

test.describe('ADELE-E2E-F — what a learner reads on a node, state by state', () => {
  test('F1/F2 predecessor, 1 of 1: before and after the predecessor is completed', async ({ page }) => {
    await test.step('before: node 2 names its predecessor, node 3 names its own', async () => {
      const first = await learnerReads(page, 'LINEAR', 'dndnode_1');
      expect(first.status).toBe('accessible');
      expect(first.info).toBe(`Access criteria: Freely accessible ${INFO_COMPLETION}`);
      expect(first.feedback).toBe(`${EN.unlocked} ${EN.todo} ${EN.completeCourse}`);

      const second = await learnerReads(page, 'LINEAR', 'dndnode_2');
      expect(second.status).toBe('not_accessible');
      expect(second.info).toBe(`Access criteria: - ${PREDECESSOR_1} ${INFO_COMPLETION}`);
      expect(second.feedback).toBe(`${EN.locked} ${PREDECESSOR_1}. ${EN.todo} ${EN.completeCourse}`);

      const third = await learnerReads(page, 'LINEAR', 'dndnode_3');
      expect(third.status).toBe('not_accessible');
      expect(third.info, 'the right node: node 3 waits for Testkurs 02').toBe(`Access criteria: - ${PREDECESSOR_2} ${INFO_COMPLETION}`);
      expect(third.feedback).toBe(`${EN.locked} ${PREDECESSOR_2}. ${EN.todo} ${EN.completeCourse}`);
      for (const [texts, where] of [[first, 'node 1'], [second, 'node 2'], [third, 'node 3']] as const) {
        expectNoRawPlaceholders(texts, `before, ${where}`);
      }
    });

    await test.step('the learner completes Testkurs 01', async () => {
      await learnerCompletes(page, 'LINEAR', 'T01');
    });

    await test.step('after: node 1 says why it counts as completed, node 2 is unlocked, node 3 unchanged', async () => {
      const second = await learnerReads(page, 'LINEAR', 'dndnode_2', 'accessible');
      expect(second.feedback, 'the text follows the change of state').toBe(`${EN.unlocked} ${EN.todo} ${EN.completeCourse}`);
      expect(second.info, 'the information card describes the criteria, whatever the state').toBe(
        `Access criteria: - ${PREDECESSOR_1} ${INFO_COMPLETION}`
      );

      const first = await learnerReads(page, 'LINEAR', 'dndnode_1');
      expect(first.status).toBe('completed');
      expect(first.feedback).toBe(`${EN.unlocked} ${EN.done} ${EN.completedCourse}`);
      expect(first.colour, 'the speech bubble shows the completion').toBe(COLOUR.completed);
      expect(second.colour, 'an unlocked node is not shown as completed').not.toBe(COLOUR.completed);

      const third = await learnerReads(page, 'LINEAR', 'dndnode_3');
      expect(third.status).toBe('not_accessible');
      expect(third.feedback).toBe(`${EN.locked} ${PREDECESSOR_2}. ${EN.todo} ${EN.completeCourse}`);
      for (const [texts, where] of [[first, 'node 1'], [second, 'node 2'], [third, 'node 3']] as const) {
        expectNoRawPlaceholders(texts, `after, ${where}`);
      }
    });
  });

  test('F1 predecessors, 2 of 2: before, partly met, met', async ({ page }) => {
    const both = 'Completion of the predecessors „Testkurs 01“ and „Testkurs 02“';

    await test.step('before: node 3 names both predecessors', async () => {
      const node = await learnerReads(page, 'ALL', 'dndnode_3');
      expect(node.status).toBe('not_accessible');
      expect(node.info).toBe(`Access criteria: - ${both} ${INFO_COMPLETION}`);
      expect(node.feedback).toBe(`${EN.locked} ${both}. ${EN.todo} ${EN.completeCourse}`);
    });

    await test.step('partly met: one of two completed - still locked, the missing one is named', async () => {
      await learnerCompletes(page, 'ALL', 'T01');
      expect((await learnerReads(page, 'ALL', 'dndnode_1', 'completed')).feedback).toBe(
        `${EN.unlocked} ${EN.done} ${EN.completedCourse}`
      );
      const node = await learnerReads(page, 'ALL', 'dndnode_3');
      expect(node.status).toBe('not_accessible');
      // Measured: the text still names BOTH predecessors, also the completed
      // one. Whether it should name only what is missing is an open question
      // (issue draft); asserted is what holds either way.
      expect(node.feedback).toContain(EN.locked);
      expect(node.feedback, 'the predecessor that is still missing must be named').toContain('„Testkurs 02“');
      expectNoRawPlaceholders(node, 'partly met, node 3');
    });

    await test.step('met: node 3 is unlocked', async () => {
      await learnerCompletes(page, 'ALL', 'T02');
      const node = await learnerReads(page, 'ALL', 'dndnode_3', 'accessible');
      expect(node.feedback).toBe(`${EN.unlocked} ${EN.todo} ${EN.completeCourse}`);
      expectNoRawPlaceholders(node, 'met, node 3');
    });
  });

  test('F1/F2 manual release: before, released, withdrawn', async ({ page }) => {
    const locked = `${EN.locked} manual release by the teacher. ${EN.todo} ${EN.completeCourse}`;

    await test.step('before: node 2 waits for the teacher', async () => {
      const node = await learnerReads(page, 'ACCESS', 'dndnode_2');
      expect(node.status).toBe('not_accessible');
      expect(node.info).toBe(`Access criteria: - Access will be granted manually ${INFO_COMPLETION}`);
      expect(node.feedback).toBe(locked);
      expectNoRawPlaceholders(node, 'before');
    });

    await test.step('released: the text says so at once', async () => {
      await teacherSets(page, 'ACCESS', 'dndnode_2', 'Unlock node manually', true);
      const node = await learnerReads(page, 'ACCESS', 'dndnode_2', 'accessible');
      expect(node.feedback).toBe(`${EN.unlocked} ${EN.todo} ${EN.completeCourse}`);
    });

    await test.step('withdrawn: the text is back to waiting for the teacher', async () => {
      await teacherSets(page, 'ACCESS', 'dndnode_2', 'Unlock node manually', false);
      const node = await learnerReads(page, 'ACCESS', 'dndnode_2', 'not_accessible');
      expect(node.feedback).toBe(locked);
      expectNoRawPlaceholders(node, 'withdrawn');
    });
  });

  test('F1/F2 manual completion: before, recorded, withdrawn', async ({ page }) => {
    const waiting = `${EN.unlocked} ${EN.todo} a manual completion is recorded by the teacher.`;

    await test.step('before: node 1 waits for the teacher to record a completion', async () => {
      const node = await learnerReads(page, 'MANUAL', 'dndnode_1');
      expect(node.status).toBe('accessible');
      expect(node.feedback).toBe(waiting);
      expect(node.info, 'a criterion, not a fact nobody recorded (#590)').toBe(
        'Access criteria: Freely accessible Completion criteria: • Manual completion by the teacher'
      );
      expectNoRawPlaceholders(node, 'before');
    });

    await test.step('recorded: node 1 counts as completed, node 2 is unlocked', async () => {
      await teacherSets(page, 'MANUAL', 'dndnode_1', 'Finish node manually', true);
      const first = await learnerReads(page, 'MANUAL', 'dndnode_1', 'completed');
      expect(first.feedback).toBe(`${EN.unlocked} ${EN.done} completed it successfully.`);
      expect(first.colour).toBe(COLOUR.completed);
      const second = await learnerReads(page, 'MANUAL', 'dndnode_2', 'accessible');
      expect(second.feedback).toBe(waiting);
    });

    await test.step('withdrawn: node 1 waits again', async () => {
      await teacherSets(page, 'MANUAL', 'dndnode_1', 'Finish node manually', false);
      const first = await learnerReads(page, 'MANUAL', 'dndnode_1', 'accessible');
      expect(first.feedback).toBe(waiting);
      expect(first.colour).not.toBe(COLOUR.completed);
      expectNoRawPlaceholders(first, 'withdrawn');
    });
  });

  test('F1 the author\'s own text is what the learner reads', async ({ page }) => {
    // Node 2 of this path: the author unticked "use default" and wrote a text.
    const node = await learnerReads(page, 'CUSTOM', 'dndnode_2');
    expect(node.status).toBe('not_accessible');
    expect(node.feedback).toBe(`${EN.locked} ${fixture('ADELE_FIXTURE_F_CUSTOM_TEXT')}. ${EN.todo} ${EN.completeCourse}`);
    // The neighbouring node keeps the default.
    expect((await learnerReads(page, 'CUSTOM', 'dndnode_3')).feedback).toBe(
      `${EN.locked} ${PREDECESSOR_2}. ${EN.todo} ${EN.completeCourse}`
    );
  });

  test('F language: the author\'s own text reaches a learner with another language', async ({ page }) => {
    test.skip(!seeded('CUSTOM_DE'), 'needs the German language pack');
    // Was a defect (local_adele #589): the German learner read the default
    // text instead of what the author wrote.
    const node = await learnerReads(page, 'CUSTOM_DE', 'dndnode_2');
    expect(node.feedback).toBe(
      `${DE.locked} ${fixture('ADELE_FIXTURE_F_CUSTOM_TEXT')}. ${DE.todo} ${DE.completeCourse}`
    );
    // The neighbouring node keeps the default - in German.
    expect((await learnerReads(page, 'CUSTOM_DE', 'dndnode_3')).feedback).toBe(
      `${DE.locked} Abschluss des Vorgängers „Testkurs 02“. ${DE.todo} ${DE.completeCourse}`
    );
  });

  test('F language: a German learner reads German - before and after their own action', async ({ page }) => {
    test.skip(!seeded('DE'), 'needs the German language pack (seed_fixtures.php installs it where the network allows)');
    const info = 'Zugangskriterien: - Abschluss des Vorgängers „Testkurs 01“ Abschlusskriterien: • diesen Kurs bearbeiten.';

    await test.step('before: German', async () => {
      const second = await learnerReads(page, 'DE', 'dndnode_2');
      expect(second.status).toBe('not_accessible');
      expect(second.info).toBe(info);
      expect(second.feedback).toBe(`${DE.locked} Abschluss des Vorgängers „Testkurs 01“. ${DE.todo} ${DE.completeCourse}`);
      expectNoRawPlaceholders(second, 'German, node 2');
    });

    await test.step('after the learner completed Testkurs 01 themselves: still German, everywhere', async () => {
      // Was a defect (local_adele #589): their own click recomputed the path
      // from the author's English texts - "Complete diesen Kurs".
      await learnerCompletes(page, 'DE', 'T01');
      const second = await learnerReads(page, 'DE', 'dndnode_2', 'accessible');
      expect(second.info).toBe(info);
      expect(second.feedback).toBe(`${DE.unlocked} ${DE.todo} ${DE.completeCourse}`);
      const first = await learnerReads(page, 'DE', 'dndnode_1');
      expect(first.status).toBe('completed');
      for (const texts of [first, second, await learnerReads(page, 'DE', 'dndnode_3')]) {
        expect(`${texts.info} ${texts.feedback}`, 'no English in a German path').not.toMatch(
          /Complete|complete|successfully|predecessor|unlocked/
        );
      }
    });
  });
});

test.describe('ADELE-E2E-F — known defects (skipped; ADELE_RUN_KNOWN_DEFECTS=1 runs them)', () => {
  knownDefect('keyboard: the information card and the feedback area open without a mouse', async ({ page }) => {
    // Measured: both containers take the focus (tabindex="0"), but only a
    // mouse click on the icon opens them - no key does (#575 territory).
    await loginAs(page, fixture('ADELE_FIXTURE_F_LINEAR_LEARNER'), fixturePassword());
    await nodeStatus(page, fixture('ADELE_FIXTURE_F_LINEAR_HOST'), 'E2E F linear', 'dndnode_1');
    const wrapper = page.locator('.vue-flow__node[data-id="dndnode_1"]');
    await wrapper.locator('.dndnode_1_node_info_listener').focus();
    await page.keyboard.press('Enter');
    await expect(wrapper.locator('.additional-card'), 'Enter on the focused i symbol').toBeVisible({ timeout: 5_000 });
    await wrapper.locator('.dndnode_1_user_info_listener').focus();
    await page.keyboard.press('Enter');
    await expect(wrapper.locator('.selectable'), 'Enter on the focused speech bubble').toBeVisible({ timeout: 5_000 });
  });
});
