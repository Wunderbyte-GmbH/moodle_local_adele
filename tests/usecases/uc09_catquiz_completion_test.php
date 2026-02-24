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
 * UC-09 — Catquiz completion: dndnode_1's completion condition is a
 * local_catquiz personability (IRT ability score) threshold.  The condition
 * fires when local_catquiz_attempts rows exist with a JSON personability value
 * that meets or exceeds the configured scale threshold.
 *
 * Two tests:
 *   a) test_no_catquiz_attempt_gives_before
 *      — no local_catquiz_attempts rows → status_completion = 'before'
 *   b) test_passing_catquiz_attempt_gives_after
 *      — local_catquiz_attempts row with personability 3.0 (≥ threshold 2.5) →
 *        status_completion = 'after', status = 'completed'
 *
 * Scale configuration (single-scale, no percentage threshold):
 *   scales = {
 *     parent: { id: '1', scale: 2.5, name: 'Test Scale', attempts: '' },
 *     sub: []
 *   }
 *   With attempts = '' (not numeric), $scaleids = [] so
 *   get_percentage_of_right_answers_by_scale() is a no-op (empty scaleids array
 *   means the foreach loop doesn't run; progress::load() IS still called but
 *   its return value is unused when $catscaleids is empty).
 *
 * Quiz-settings mode: 'single_quiz'
 *   In this mode catquiz::get_completion_status() marks an attempt as
 *   completed when no scale check fails ($invalidattempt = false), placing
 *   the record in $allpassedrecords['single'].  The test sets this config
 *   before re-evaluation so the threshold logic executes correctly.
 *
 * Why not 'all_quiz_global' (the default admin setting)?
 *   With 'all_quiz_global' a scale-only condition never completes because the
 *   final check requires both $partialpassedrecords['scale'] AND
 *   $partialpassedrecords['percentage'] to be set; the latter is never
 *   populated when attempts = '' (not numeric).  'single_quiz' uses the
 *   $allpassedrecords path which avoids this limitation.
 *
 * Infrastructure:
 *   The catquiz condition needs componentid (adaptivequiz instance id) and
 *   testid_courseid (course id) to query local_catquiz_attempts via
 *   return_data_from_attemptstable().  Rather than creating a full adaptivequiz
 *   instance, a synthetic componentid (99999) is used because there is no
 *   foreign-key constraint on local_catquiz_attempts.instanceid.  The
 *   adaptivequiz metadata look-up ($DB->get_record('adaptivequiz', ...)) will
 *   return null for a fake id, which is handled gracefully by using the
 *   fallback placeholder 'Test' — this does not affect completion evaluation.
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use context_system;
use local_adele\event\user_path_updated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Catquiz completion: before and after personability threshold tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_completion\course_completion_status::class)]
final class uc09_catquiz_completion_test extends adele_learningpath_testcase {
    /**
     * Synthetic adaptivequiz instance ID used as the catquiz componentid.
     * No real adaptivequiz record is needed; the metadata look-up simply
     * returns null and the condition still evaluates correctly.
     */
    private const CAT_COMPONENT_ID = 99999;

    /**
     * Personability scale threshold (logit value).
     */
    private const SCALE_THRESHOLD = 2.5;

    /**
     * Scale ID string used both in the condition node data and in the
     * personabilities JSON of the attempt record.
     */
    private const SCALE_ID = '1';

    /**
     * Uses the main fixture.  patch_node_ids() replaces dndnode_1's
     * course_completed condition with a catquiz condition.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Re-wire dndnode_1's condition_1 from course_completed to catquiz.
     *
     * Scale: single parent scale (id='1', threshold=2.5, no percentage threshold).
     * Component ID: 99999 (synthetic; no real adaptivequiz row required).
     * Testid course ID: courseids[0].
     *
     * dndnode_2 receives courseids[2] (unchanged restriction structure).
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_1') {
                $node['data']['course_node_id'] = [$this->courseids[0]];
                // Replace course_completed → catquiz on condition_1.
                foreach ($node['completion']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['data']['label']        = 'catquiz';
                        $cn['data']['value']        = [
                            'testid'          => 1,
                            'testid_courseid' => $this->courseids[0],
                            'componentid'     => self::CAT_COMPONENT_ID,
                            'scales'          => [
                                'parent' => [
                                    'id'       => self::SCALE_ID,
                                    'scale'    => self::SCALE_THRESHOLD,
                                    'name'     => 'Test Scale',
                                    'attempts' => '',
                                ],
                                'sub' => [],
                            ],
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
     * Insert a local_catquiz_attempts row representing a completed catquiz
     * attempt with the given personability score for the parent scale.
     *
     * The json column carries the personabilities object that
     * get_personabilityresults_of_quizattempt() reads.  contextid is set to 1
     * (system context) to satisfy the property read in
     * get_percentage_of_right_answers_by_scale() — the function returns early
     * (empty $catscaleids) so the contextid value is never actually used.
     *
     * return_data_from_attemptstable() is called with userid as the 5th param
     * (the fixed production call passes 0 for attemptid and $userid for userid),
     * so the SQL filters WHERE userid = :userid.  The attemptid here is a
     * synthetic value used only to satisfy the local_catquiz_progress FK.
     *
     * @param int   $userid       The user this attempt belongs to.
     * @param float $personability Personability score to store in the JSON.
     */
    private function insert_catquiz_attempt_in_db(int $userid, float $personability): void {
        global $DB;

        $attemptid = 9000 + $userid;
        $DB->insert_record('local_catquiz_attempts', (object)[
            'userid'                   => $userid,
            'scaleid'                  => (int)self::SCALE_ID,
            'contextid'                => 1,
            'courseid'                 => $this->courseids[0],
            'attemptid'                => $attemptid,
            'component'                => 'mod_adaptivequiz',
            'instanceid'               => self::CAT_COMPONENT_ID,
            'teststrategy'             => 0,
            'status'                   => 0,
            'total_number_of_testitems' => 0,
            'number_of_testitems_used' => 0,
            'personability_before_attempt' => 0,
            'personability_after_attempt'  => $personability,
            'starttime'                => time(),
            'endtime'                  => time(),
            'json'                     => json_encode([
                'personabilities' => [(int)self::SCALE_ID => $personability],
                'contextid'       => 1,
            ]),
            'timecreated'              => time(),
            'timemodified'             => time(),
        ]);

        // get_percentage_of_right_answers_by_scale() always calls progress::load()
        // before iterating $catscaleids.  Without a local_catquiz_progress row
        // it falls through to create_new(null) which throws a TypeError.
        // Insert a minimal progress record keyed to the same synthetic attemptid.
        $DB->insert_record('local_catquiz_progress', (object)[
            'userid'       => $userid,
            'component'    => 'mod_adaptivequiz',
            'attemptid'    => $attemptid,
            'json'         => json_encode([
                'contextid'             => 1,
                'playedquestions'       => [],
                'playedquestionsbyscale' => (object)[],
                'isfirstquestion'       => true,
                'lastquestion'          => null,
                'breakend'              => null,
                'activescales'          => (object)[],
                'droppedscales'         => (object)[],
                'responses'             => (object)[],
                'abilities'             => (object)[],
                'forcedbreakend'        => 0,
                'lockedscales'          => (object)[],
                'usageid'               => null,
                'session'               => null,
                'excludedquestions'     => [],
                'gaveupquestions'       => [],
                'starttime'             => 0,
            ]),
            'quizsettings' => json_encode((object)[]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests.

    /**
     * BEFORE: no local_catquiz_attempts rows for the enrolled users.
     *
     * catquiz::get_completion_status():
     *   return_data_from_attemptstable(100, CAT_COMPONENT_ID, courseid, userid)
     *   → empty result set
     *   inbetween['condition_1'] = false (count = 0)
     *   completed['condition_1'] = false
     *
     * getnodestatusforcompletion() iterates $completioncriteria, finds no
     * inbetween = true → returns 'before'.
     * getnodestatus() → dndnode_1 has no restriction → 'accessible'.
     *
     * Expected:
     *   status_completion = 'before'
     *   status            = 'accessible'
     *
     * @return void
     */
    public function test_no_catquiz_attempt_gives_before(): void {
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
                "User {$record->user_id}: expected 'before' when no catquiz attempts exist."
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
     * AFTER: catquiz attempt with personability 3.0 (≥ threshold 2.5) fires completion.
     *
     * The quiz-settings mode is set to 'single_quiz' so the completion fires
     * through the $allpassedrecords path:
     *   check_scale: personabilityresults->{'1'} (3.0) >= scale (2.5) → passes
     *   $invalidattempt = false → $allpassedrecords['single'] = $record
     *   $validationtype == 'single_quiz' && !empty($allpassedrecords) → completed!
     *
     * catquiz::get_completion_status():
     *   completed['condition_1'] = $allpassedrecords (truthy array)
     *
     * validatenodecompletion():
     *   $validationcondition = $allpassedrecords (truthy) → path fires
     *
     * getnodestatusforcompletion() → 'after'
     * getnodestatus()              → 'completed'
     *
     * Expected:
     *   status_completion = 'after'
     *   status            = 'completed'
     *
     * @return void
     */
    public function test_passing_catquiz_attempt_gives_after(): void {
        global $DB;

        // Step 1: Subscribe + initial evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 2: Set quiz mode and insert passing catquiz attempts.
        set_config('quizsettings', 'single_quiz', 'local_adele');

        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $this->insert_catquiz_attempt_in_db(
                (int)$record->user_id,
                3.0  // Above the 2.5 threshold.
            );
        }

        // Step 3: Re-evaluate with fresh events so catquiz reads the new rows.
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
        $this->assertNotEmpty($records, 'Expected user path records after catquiz attempt.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_1']['feedback'];

            $this->assertEquals(
                'after',
                $fb['status_completion'],
                "User {$record->user_id}: expected 'after' when personability meets scale threshold."
            );
            $this->assertEquals(
                'completed',
                $fb['status'],
                "User {$record->user_id}: expected 'completed' when personability meets scale threshold."
            );
        }

        $this->sink->close();
    }
}
