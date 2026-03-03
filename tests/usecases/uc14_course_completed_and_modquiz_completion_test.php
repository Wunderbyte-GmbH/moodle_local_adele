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
 * UC-14 — Completion AND chain: dndnode_1's completion is a single column
 * with two conditions in sequence:
 *   condition_1 = course_completed  (at least 1 course done)
 *   condition_2 = modquiz           (quiz grade >= threshold)
 *
 * Both conditions must pass for the completion column to fire.  The AND wiring:
 *   condition_1.childCondition  = ['condition_1_feedback', 'condition_2']
 *   condition_2.parentCondition = ['condition_1']        ← NOT starting_condition
 *
 * Three tests:
 *   a) test_and_chain_inbetween_with_no_attempt
 *      — No quiz attempt, course not completed.
 *        course_completed always sets inbetween=true for enrolled users (by
 *        design: "enrolled = working on it"), so status_completion = 'inbetween'
 *        even though neither condition has been met.
 *        Secondary: completioncriteria.modquiz.inbetween.condition_2 = false
 *                   (no attempt → modquiz inbetween not yet triggered).
 *        Expected: status_completion='inbetween', status='accessible'.
 *
 *   b) test_and_chain_inbetween_with_attempt_below_threshold
 *      — Quiz attempt exists (sumgrades=1.0) but below threshold (5.0); course
 *        not completed.  The AND column still does not fire (both conditions must
 *        pass), but modquiz now raises its own inbetween flag.
 *        Secondary: completioncriteria.modquiz.inbetween.condition_2 = true.
 *        Expected: status_completion='inbetween', status='accessible'.
 *
 *   c) test_and_chain_after_when_both_conditions_pass
 *      — Course completed AND quiz attempt at threshold.  AND walk: both
 *        validationcondition=true, failedcompletion=false → column pushed.
 *        Expected: status_completion='after', status='completed'.
 *
 * Note on 'before' state:
 *   course_completed always sets inbetween=true for enrolled users regardless
 *   of progress (design intention: accessible = working on it).
 *   getnodestatusforcompletion() scans ALL criteria for any truthy inbetween
 *   flag, so status_completion never reaches 'before' for this AND chain as
 *   long as the user is enrolled.  Tests 14a and 14b are both 'inbetween' but
 *   are distinguished via secondary assertions on the stored completioncriteria.
 *
 * Fixture: simpleconcatlp.json
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
 * Completion AND chain: course_completed AND modquiz tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_completion\course_completion_status::class)]
final class uc14_course_completed_and_modquiz_completion_test extends adele_learningpath_testcase {
    /**
     * Grade threshold set on the modquiz completion condition (5.0 out of 10.0).
     */
    private const GRADE_THRESHOLD = 5.0;

    /**
     * ID of the quiz instance created in courseids[0] by patch_node_ids().
     *
     * @var int
     */
    protected int $quizid = 0;

    /**
     * Uses the AND-chain fixture where dndnode_1.completion is
     * course_completed → modquiz in a single column.
     */
    protected function fixturefile(): string {
        return 'simpleconcatlp.json';
    }

    /**
     * Create a real quiz in courseids[0] and wire dndnode_1's AND-chain
     * completion so condition_2 (modquiz) references it.
     *
     * dndnode_1:
     *   - courseids[0]
     *   - condition_1 (course_completed): min_courses = 1
     *   - condition_2 (modquiz):          quizid = $this->quizid, grade = 5.0
     *
     * dndnode_2:
     *   - courseids[2]
     *   - restriction left as-is (parent_courses → timed from fixture); only
     *     dndnode_1 is asserted in these tests.
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    protected function patch_node_ids(array &$nodes): void {
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz    = $quizgen->create_instance([
            'course'    => $this->courseids[0],
            'name'      => 'UC14 Quiz',
            'sumgrades' => 10,
            'grade'     => 10,
        ]);
        $this->quizid = (int)$quiz->id;

        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_1') {
                $node['data']['course_node_id'] = [$this->courseids[0]];
                foreach ($node['completion']['nodes'] as &$cn) {
                    if (($cn['data']['label'] ?? '') === 'course_completed') {
                        $cn['data']['value']['min_courses'] = 1;
                    }
                    if (($cn['data']['label'] ?? '') === 'modquiz') {
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
     * A question_usages row is created first to satisfy the FK on
     * quiz_attempts.uniqueid.
     *
     * @param int   $quizid    Quiz instance ID.
     * @param int   $userid    User to record the attempt for.
     * @param float $sumgrades Raw score to store.
     */
    private function insert_quiz_attempt_in_db(int $quizid, int $userid, float $sumgrades): void {
        global $DB;

        $usageid = $DB->insert_record('question_usages', (object)[
            'contextid'          => context_course::instance($this->courseids[0])->id,
            'component'          => 'mod_quiz',
            'preferredbehaviour' => 'deferredfeedback',
        ]);

        $DB->insert_record('quiz_attempts', (object)[
            'quiz'         => $quizid,
            'userid'       => $userid,
            'attempt'      => 1,
            'uniqueid'     => $usageid,
            'layout'       => '',
            'currentpage'  => 0,
            'preview'      => 0,
            'state'        => 'finished',
            'timestart'    => time(),
            'timefinish'   => time(),
            'timemodified' => time(),
            'sumgrades'    => $sumgrades,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests.

    /**
     * INBETWEEN (no attempt): enrolled but neither AND condition is satisfied.
     *
     * validatenodecompletion() AND walk:
     *   condition_1 (course_completed): completed=false → failedcompletion=true
     *   condition_2 (modquiz):          completed=false, inbetween=false (no attempt)
     *   End: completionnodepaths not pushed
     *
     * getnodestatusforcompletion():
     *   Scans ALL criteria — course_completed.inbetween.condition_1=true (enrolled)
     *   → returns 'inbetween'
     *
     * getnodestatus(): restriction=null on dndnode_1 → 'accessible'
     *
     * Secondary assertion:
     *   completioncriteria.modquiz.inbetween.condition_2 = false
     *   (no quiz attempt → modquiz inbetween not yet triggered)
     *
     * Expected:
     *   status_completion = 'inbetween'
     *   status            = 'accessible'
     *
     * @return void
     */
    public function test_and_chain_inbetween_with_no_attempt(): void {
        global $DB;

        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after enrollment.');

        foreach ($records as $record) {
            $json     = json_decode($record->json, true);
            $fb       = $json['user_path_relation']['dndnode_1']['feedback'];
            $criteria = $json['user_path_relation']['dndnode_1']['completioncriteria'];

            $this->assertEquals(
                'inbetween',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'inbetween' — enrollment inbetween fires."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' — dndnode_1 has no restriction."
            );
            // No quiz attempt → modquiz inbetween must be false for condition_2.
            $this->assertFalse(
                $criteria['modquiz']['inbetween']['condition_2'] ?? true,
                "User {$record->user_id}: modquiz inbetween must be false when no attempt exists."
            );
        }

        $this->sink->close();
    }

    /**
     * INBETWEEN (attempt below threshold): a quiz attempt exists but does not
     * meet the grade threshold; course also not completed.
     *
     * modquiz::get_completion_status():
     *   get_records_select keyed by sumgrades → key '1'
     *   count($data) > 0  → inbetween['condition_2'] = true
     *   (float)'1' = 1.0 < 5.0 → completed['condition_2'] stays false
     *
     * validatenodecompletion() AND walk:
     *   condition_1 (course_completed): completed=false → failedcompletion=true
     *   condition_2 (modquiz):          completed=false, inbetween=true
     *   End: not pushed
     *
     * getnodestatusforcompletion():
     *   course_completed.inbetween.condition_1=true OR
     *   modquiz.inbetween.condition_2=true → 'inbetween'
     *
     * Secondary assertion:
     *   completioncriteria.modquiz.inbetween.condition_2 = true
     *   (attempt exists, even though below threshold)
     *
     * Expected:
     *   status_completion = 'inbetween'
     *   status            = 'accessible'
     *
     * @return void
     */
    public function test_and_chain_inbetween_with_attempt_below_threshold(): void {
        global $DB;

        // Step 1: Subscribe + initial evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Insert a below-threshold attempt for condition_2's quiz.
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $this->insert_quiz_attempt_in_db(
                $this->quizid,
                (int)$record->user_id,
                1.0 // Below GRADE_THRESHOLD (5.0).
            );
        }

        // Step 3: Re-evaluate so modquiz reads the new attempt.
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

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after quiz attempt.');

        foreach ($records as $record) {
            $json     = json_decode($record->json, true);
            $fb       = $json['user_path_relation']['dndnode_1']['feedback'];
            $criteria = $json['user_path_relation']['dndnode_1']['completioncriteria'];

            $this->assertEquals(
                'inbetween',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'inbetween' — attempt below threshold."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible'."
            );
            // Attempt exists → modquiz inbetween is now true even though completed=false.
            $this->assertTrue(
                $criteria['modquiz']['inbetween']['condition_2'] ?? false,
                "User {$record->user_id}: modquiz inbetween must be true when attempt exists."
            );
        }

        $this->sink->close();
    }

    /**
     * AFTER: course completed AND quiz attempt at threshold — both AND
     * conditions pass, completion column fires.
     *
     * validatenodecompletion() AND walk:
     *   condition_1 (course_completed): completed=true  → no failedcompletion
     *   condition_2 (modquiz):          completed=true  (5.0 >= 5.0)
     *   End: validationcondition=true && !failedcompletion=true → pushed
     *
     * getnodestatusforcompletion() → count(completionnodepaths) > 0 → 'after'
     * getnodestatus()              → feedback['completion']['after'] truthy → 'completed'
     *
     * Expected:
     *   status_completion = 'after'
     *   status            = 'completed'
     *
     * @return void
     */
    public function test_and_chain_after_when_both_conditions_pass(): void {
        global $DB;

        // Step 1: Subscribe + initial evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Satisfy both AND conditions for every enrolled user.
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $this->mark_course_complete_in_db((int)$this->courseids[0], (int)$record->user_id);
            $this->insert_quiz_attempt_in_db(
                $this->quizid,
                (int)$record->user_id,
                self::GRADE_THRESHOLD // 5.0 >= 5.0 → completed.
            );
        }

        // Step 3: Re-evaluate so both condition classes read the new DB state.
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

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records after completing both conditions.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_1']['feedback'];

            $this->assertEquals(
                'after',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'after' — both AND conditions satisfied."
            );
            $this->assertEquals(
                'completed',
                $fb['status'],
                "User {$record->user_id}: expected 'completed' — full AND chain passed."
            );
        }

        $this->sink->close();
    }
}
