<?php
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
 * UC-08 — Modquiz completion: dndnode_1's completion condition is a mod_quiz
 * grade threshold.  The condition fires when a quiz_attempts row exists with
 * sumgrades >= the configured grade threshold.
 *
 * Three tests:
 *   a) test_no_quiz_attempt_gives_before
 *      — no quiz_attempts rows → status_completion = 'before', status = 'accessible'
 *   b) test_below_threshold_quiz_attempt_gives_inbetween
 *      — quiz_attempts row inserted with sumgrades (1.0) below the threshold (5.0) →
 *        status_completion = 'inbetween', status = 'accessible'
 *   c) test_passing_quiz_attempt_gives_after
 *      — quiz_attempts row inserted with sumgrades equal to the threshold →
 *        status_completion = 'after', status = 'completed'
 *
 * Infrastructure:
 *   patch_node_ids() uses the mod_quiz generator to create a real quiz instance
 *   in courseids[0] so that modquiz::get_completion_status() can look up the
 *   quiz metadata (name, sumgrades, grade, cmid) without returning null.
 *   The quiz id is stored in $this->quizid and written into the completion
 *   condition node's data.value.quizid.
 *
 *   insert_quiz_attempt_in_db() creates a question_usages row (required by the
 *   foreign-key constraint on quiz_attempts.uniqueid) then inserts a finished
 *   quiz_attempts row with the requested sumgrades value.
 *
 * Grade threshold: 5.0  (quiz max grade: 10.0)
 *
 * Flow (test b):
 *   1. Subscribe + initial evaluation (creates user_path_relation, no attempts
 *      yet → before).
 *   2. Insert quiz_attempts row with sumgrades = 5.0 (= threshold) for every
 *      enrolled user.
 *   3. Re-evaluate with fresh user_path_updated events.
 *   4. Assert status_completion = 'after', status = 'completed'.
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use context_course;
use context_system;
use local_adele\event\user_path_updated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Modquiz completion: before and after grade threshold tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_completion\course_completion_status::class)]
final class uc08_modquiz_completion_test extends adele_learningpath_testcase {
    /**
     * The grade threshold set on the modquiz completion condition (5.0 out of 10.0).
     */
    private const GRADE_THRESHOLD = 5.0;

    /**
     * ID of the quiz instance created in courseids[0] by patch_node_ids().
     *
     * @var int
     */
    protected int $quizid = 0;

    /**
     * Uses the main fixture.  patch_node_ids() replaces dndnode_1's
     * course_completed condition with a modquiz condition referencing the
     * real quiz created during setUp().
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Create a quiz in courseids[0], store its id, then re-wire dndnode_1's
     * condition_1 from course_completed to modquiz.
     *
     * dndnode_2 receives courseids[2] (unchanged restriction structure).
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    protected function patch_node_ids(array &$nodes): void {
        // Create a real quiz instance so the metadata look-up in
        // modquiz::get_completion_status() returns a valid record.
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgen->create_instance([
            'course'     => $this->courseids[0],
            'name'       => 'UC08 Quiz',
            'sumgrades'  => 10,
            'grade'      => 10,
        ]);
        $this->quizid = (int)$quiz->id;

        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_1') {
                $node['data']['course_node_id'] = [$this->courseids[0]];
                // Replace course_completed → modquiz on condition_1.
                foreach ($node['completion']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['data']['label'] = 'modquiz';
                        $cn['data']['value'] = [
                            'quizid' => $this->quizid,
                            'grade'  => self::GRADE_THRESHOLD,
                        ];
                    }
                }
                unset($cn);
            } else {
                $node['data']['course_node_id'] = [$this->courseids[2]];
            }
        }
        unset($node);
    }

    // -------------------------------------------------------------------------
    // DB helper.

    /**
     * Insert a finished quiz_attempts row for the given user.
     *
     * A question_usages row is created first to satisfy the foreign-key
     * constraint on quiz_attempts.uniqueid.
     *
     * @param int   $quizid    The quiz instance ID (quiz table PK).
     * @param int   $userid    The user to record the attempt for.
     * @param float $sumgrades The raw score to store.
     */
    private function insert_quiz_attempt_in_db(int $quizid, int $userid, float $sumgrades): void {
        global $DB;

        // Question_usages row is required by the FK on quiz_attempts.uniqueid.
        $usageid = $DB->insert_record('question_usages', (object)[
            'contextid'          => context_course::instance($this->courseids[0])->id,
            'component'          => 'mod_quiz',
            'preferredbehaviour' => 'deferredfeedback',
        ]);

        $DB->insert_record('quiz_attempts', (object)[
            'quiz'          => $quizid,
            'userid'        => $userid,
            'attempt'       => 1,
            'uniqueid'      => $usageid,
            'layout'        => '',
            'currentpage'   => 0,
            'preview'       => 0,
            'state'         => 'finished',
            'timestart'     => time(),
            'timefinish'    => time(),
            'timemodified'  => time(),
            'sumgrades'     => $sumgrades,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests.

    /**
     * BEFORE: no quiz_attempts rows exist for the enrolled users.
     *
     * modquiz::get_modquiz_records() returns an empty result set.
     * $data is empty → inbetween = false, completed = false.
     * getnodestatusforcompletion() finds no inbetween flags → 'before'.
     * getnodestatus() → dndnode_1 has no restriction (null) → 'accessible'.
     *
     * Expected:
     *   status_completion = 'before'
     *   status            = 'accessible'
     *
     * @return void
     */
    public function test_no_quiz_attempt_gives_before(): void {
        global $DB;

        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after enrollment.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_1']['feedback'];

            $this->assertEquals(
                'before',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'before' when no quiz attempts exist."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' when dndnode_1 has no restriction."
            );
        }

        $this->sink->close();
    }

    /**
     * INBETWEEN: quiz_attempts row exists but sumgrades is below the grade threshold.
     *
     * Flow:
     *   1. Subscribe + initial evaluation (before — no attempts yet).
     *   2. Insert quiz_attempts row with sumgrades = 1.0 (< GRADE_THRESHOLD = 5.0)
     *      for each enrolled user.
     *   3. Re-evaluate with fresh events so modquiz::get_completion_status() picks
     *      up the new rows.
     *
     * modquiz::get_completion_status():
     *   get_records_select() with fields='sumgrades' keys results by sumgrades value.
     *   $data = ['1' => record]   (key = the sumgrades value '1')
     *   count($data) > 0  → inbetween['condition_1'] = true
     *   (float)'1' = 1.0 < 5.0 → completed['condition_1'] stays false
     *
     * getnodestatusforcompletion() → inbetween flag set → 'inbetween'
     * getnodestatus()              → dndnode_1 has no restriction → 'accessible'
     *
     * Expected:
     *   status_completion = 'inbetween'
     *   status            = 'accessible'
     *
     * @return void
     */
    public function test_below_threshold_quiz_attempt_gives_inbetween(): void {
        global $DB;

        // Step 1: Subscribe + initial evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Insert a failing quiz attempt (sumgrades < threshold) for each user.
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $this->insert_quiz_attempt_in_db(
                $this->quizid,
                (int)$record->user_id,
                1.0 // Below GRADE_THRESHOLD (5.0).
            );
        }

        // Step 3: Re-evaluate with fresh events so modquiz reads the new rows.
        $freshrecords = $DB->get_records('local_adele_path_user');
        foreach ($freshrecords as $freshrecord) {
            $freshrecord->json = json_decode($freshrecord->json, true);
            $event = user_path_updated::create([
                'objectid' => $freshrecord->id,
                'context'  => context_system::instance(),
                'other'    => ['userpath' => $freshrecord],
            ]);
            relation_update::updated_single($event);
        }

        // Step 4: Assert dndnode_1 is inbetween (attempt present but below threshold).
        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after quiz attempt.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_1']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'inbetween' when quiz attempt is below grade threshold."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' when dndnode_1 has no restriction."
            );
        }

        $this->sink->close();
    }

    /**
     * AFTER: quiz_attempts row with sumgrades = GRADE_THRESHOLD fires the completion.
     *
     * Flow:
     *   1. Subscribe + initial evaluation (before).
     *   2. Insert quiz_attempts row with sumgrades = 5.0 (= threshold) for each user.
     *   3. Re-evaluate with fresh events so get_modquiz_records() picks up the new rows.
     *
     * modquiz::get_completion_status():
     *   $data = [{5.0 => record}]   (get_records_select keyed by sumgrades)
     *   $bestgrade = 5.0 >= 5.0 → $validcatquiz = true
     *   completed['condition_1'] = true
     *
     * validatenodecompletion():
     *   $validationcondition = true → path fires → completionnodepaths populated
     *
     * getnodestatusforcompletion() → 'after'
     * getnodestatus()              → 'completed' (feedback['completion']['after'] truthy)
     *
     * Expected:
     *   status_completion = 'after'
     *   status            = 'completed'
     *
     * @return void
     */
    public function test_passing_quiz_attempt_gives_after(): void {
        global $DB;

        // Step 1: Subscribe + initial evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Insert a passing quiz attempt for each enrolled user.
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $this->insert_quiz_attempt_in_db(
                $this->quizid,
                (int)$record->user_id,
                self::GRADE_THRESHOLD
            );
        }

        // Step 3: Re-evaluate with fresh events so modquiz reads the new rows.
        $freshrecords = $DB->get_records('local_adele_path_user');
        foreach ($freshrecords as $freshrecord) {
            $freshrecord->json = json_decode($freshrecord->json, true);
            $event = user_path_updated::create([
                'objectid' => $freshrecord->id,
                'context'  => context_system::instance(),
                'other'    => ['userpath' => $freshrecord],
            ]);
            relation_update::updated_single($event);
        }

        // Step 4: Assert dndnode_1 is now completed.
        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after quiz attempt.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_1']['feedback'];

            $this->assertEquals(
                'after',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'after' when quiz attempt grade meets threshold."
            );
            $this->assertEquals(
                'completed',
                $fb['status'],
                "User {$record->user_id}: expected 'completed' when quiz attempt grade meets threshold."
            );
        }

        $this->sink->close();
    }
}
