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
 * UC-21 — Adhoc task scheduling for timed restrictions.
 *
 * Covers all meaningful combinations of date direction and user enrolment state:
 *
 * A. Unit — direct set_scheduled_adhoc_tasks() calls:
 *      A1  future start date           → 1 task
 *      A2  past start date             → 0 tasks (synchronous path handles access grant)
 *      A3  both start + end future     → 2 tasks
 *      A4  start past, end future      → 1 task (start skipped, end scheduled)
 *
 * B. Integration — user NOT yet enrolled (fresh path creation):
 *      B1  LP has future restriction   → task created on first enrollment
 *      B2  LP has past restriction     → no task (synchronous updated_single grants access)
 *
 * C. Integration — user already enrolled (first_enrolled stamped), LP date changes:
 *      C1  date was past, moved to future  → new task scheduled
 *      C2  date was future+7d, moved to future+14d (later)   → new task at new time
 *      C3  date was future+14d, moved to future+3d (sooner)  → new task at earlier time
 *      C4  date was future, moved to past  → no task (synchronous updated_single grants access)
 *      C5  date changes future→future      → existing task rescheduled (count stays 1, not 2)
 *
 * D. Integration — child (non-starting) node date changes:
 *      D1  child node gets a future restriction after LP update  → task scheduled
 *      D2  child node restriction was past (user had access), moved to future
 *          → task must be re-scheduled (regression: only starting nodes were
 *             previously covered by subscribe_user_starting_node)
 *
 * Fixture: alise_zugangs_lp_einfach.json
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use local_adele\helper\adhoc_task_helper;
use local_adele\learning_path_update;
use stdClass;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Adhoc task scheduling for timed restrictions.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(adhoc_task_helper::class)]
#[CoversClass(learning_path_update::class)]
#[CoversClass(relation_update::class)]
final class uc21_adhoc_timed_restriction_test extends adele_learningpath_testcase {

    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (isset($node['data']['course_node_id'])) {
                $node['data']['course_node_id'] = [$this->courseids[0]];
            }

            // Initialize every field that passnodevalues() reads from nodes so
            // that processing LP-date changes does not produce undefined-key
            // PHP warnings from fields absent in the original fixture.
            $node['data'] += [
                'manualcompletion'       => null,
                'manualcompletionvalue'  => null,
                'manualrestriction'      => null,
                'manualrestrictionvalue' => null,
                'first_enrolled'         => null,
            ];
            if (!isset($node['data']['completion'])) {
                $node['data']['completion'] = [];
            }
            if (!isset($node['data']['completion']['master'])) {
                $node['data']['completion']['master'] = ['completion' => false, 'restriction' => false];
            } else {
                $node['data']['completion']['master'] += ['completion' => false, 'restriction' => false];
            }

            // Fix completion condition min_courses to match the single patched
            // course so checknodeprogression() does not access $sortedcourses[1].
            if (isset($node['completion']['nodes'])) {
                foreach ($node['completion']['nodes'] as &$condnode) {
                    if (isset($condnode['data']['value']['min_courses'])) {
                        $condnode['data']['value']['min_courses'] = 1;
                    }
                }
            }

            // Add a label key to any restriction/completion nodes that lack one
            // so passnodevalues()'s "label == 'manual'" checks don't warn.
            foreach (['restriction', 'completion'] as $section) {
                if (isset($node[$section]['nodes'])) {
                    foreach ($node[$section]['nodes'] as &$rnode) {
                        if (!isset($rnode['data']['label'])) {
                            $rnode['data']['label'] = null;
                        }
                    }
                }
            }
        }
    }

    // =========================================================================
    // Private helpers.
    // =========================================================================

    /**
     * Return the count of queued update_user_path adhoc tasks.
     */
    private function count_adhoc_tasks(): int {
        global $DB;
        return (int) $DB->count_records(
            'task_adhoc',
            ['classname' => '\\local_adele\\task\\update_user_path']
        );
    }

    /**
     * Build a minimal userpath stdClass for unit-level set_scheduled_adhoc_tasks() calls.
     * Uses a fake id (999) — enough for dedup key construction but not a real DB row.
     */
    private function make_userpath(int $userid): stdClass {
        $up = new stdClass();
        $up->id               = 999;
        $up->user_id          = $userid;
        $up->learning_path_id = $this->adelestart->learningpathid;
        $up->course_id        = $this->startingcourseid;
        return $up;
    }

    /**
     * Build a minimal restriction node array for unit-level tests.
     * Contains one timed restriction with the given start / end dates.
     *
     * @param string $startdate  Format 'Y-m-d\TH:i', empty string = no start
     * @param string $enddate    Format 'Y-m-d\TH:i', empty string = no end
     */
    private function make_timed_node(string $startdate, string $enddate = ''): array {
        return [
            'id'          => 'dndnode_1',
            'type'        => 'orcourses',
            'parentCourse' => ['starting_node'],
            'data'        => [
                'course_node_id' => [$this->courseids[0]],
                'first_enrolled' => time(),
            ],
            'restriction' => [
                'nodes' => [
                    [
                        'id'   => 'timed_condition_1',
                        'data' => [
                            'label' => 'timed',
                            'value' => ['start' => $startdate, 'end' => $enddate],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Read the LP's tree from the DB, inject a timed restriction with $startdate
     * into every starting node, fill in the fields that passnodevalues() requires
     * so no undefined-key warnings occur, optionally stamp first_enrolled, save
     * the LP back to the DB, and return the saved JSON string.
     *
     * @param string $startdate       Format 'Y-m-d\TH:i'
     * @param bool   $setfirstenrolled  If true the starting node will have
     *                                  first_enrolled = time() - 3600 (1 h ago).
     */
    private function set_lp_restriction_date(string $startdate, bool $setfirstenrolled = false): string {
        global $DB;
        $lp     = $DB->get_record('local_adele_learning_paths',
                    ['id' => $this->adelestart->learningpathid]);
        $lpjson = json_decode($lp->json, true);
        foreach ($lpjson['tree']['nodes'] as &$node) {
            if (isset($node['parentCourse']) && in_array('starting_node', $node['parentCourse'])) {
                $node = $this->inject_timed_restriction($node, $startdate, $setfirstenrolled);
            }
        }
        $lp->json = json_encode($lpjson);
        $DB->update_record('local_adele_learning_paths', $lp);
        return $lp->json;
    }

    /**
     * Replace the restriction section of $node with a single timed restriction
     * and ensure every field that passnodevalues() reads is initialised.
     *
     * @param array  $node          Full LP node array (from fixture).
     * @param string $startdate     Format 'Y-m-d\TH:i'
     * @param bool   $setfirstenrolled
     */
    private function inject_timed_restriction(array $node, string $startdate, bool $setfirstenrolled = false): array {
        // The restriction must include a linked feedback node so that
        // relation_update.php's searchnestedarray / childCondition[0] lookups
        // resolve correctly without generating undefined-key warnings.
        $node['restriction'] = [
            'nodes' => [
                [
                    'id'               => 'timed_condition_1',
                    'data'             => [
                        'label'                  => 'timed',
                        'value'                  => ['start' => $startdate, 'end' => ''],
                        // Required by render_placeholders_single_restriction() when
                        // the restriction is not yet satisfied (date still in future).
                        'description_before'     => '',
                        'description_after'      => '',
                        'description_inbetween'  => '',
                    ],
                    'parentCondition'  => ['starting_condition'],
                    // Points to the feedback node so childCondition[0] resolves
                    // and getnodestatusforrestriciton() has a valid feedback id.
                    'childCondition'   => ['timed_condition_1_feedback'],
                ],
                [
                    'id'             => 'timed_condition_1_feedback',
                    'data'           => [
                        'label'                     => null,
                        'feedback_before'           => '',
                        'feedback_before_checkmark' => true,
                        'visibility'                => true,
                    ],
                    'parentCondition' => null,
                    'childCondition'  => [],
                ],
            ],
        ];
        // Ensure passnodevalues() can safely read these fields without undefined-key warnings.
        foreach (['manualcompletion', 'manualcompletionvalue',
                  'manualrestriction', 'manualrestrictionvalue'] as $key) {
            if (!isset($node['data'][$key])) {
                $node['data'][$key] = null;
            }
        }
        if (!isset($node['data']['completion'])) {
            $node['data']['completion'] = [];
        }
        if (!isset($node['data']['completion']['master'])) {
            $node['data']['completion']['master'] = ['completion' => false, 'restriction' => false];
        } else {
            $node['data']['completion']['master'] += ['completion' => false, 'restriction' => false];
        }
        $node['data']['first_enrolled'] = $setfirstenrolled ? (time() - 3600) : ($node['data']['first_enrolled'] ?? null);
        return $node;
    }

    /**
     * Insert a local_adele_path_user record that represents a user who was
     * already enrolled in the LP (first_enrolled is set) and whose starting
     * node had $restrictiondate as the timed restriction when they enrolled.
     *
     * This simulates the "user enrolled before" state without going through
     * the full enrollment event chain.
     *
     * @return int  The new record's id.
     */
    private function insert_enrolled_user_path(int $userid, string $restrictiondate): int {
        global $DB;
        $lp     = $DB->get_record('local_adele_learning_paths',
                    ['id' => $this->adelestart->learningpathid]);
        $lpjson = json_decode($lp->json, true);
        foreach ($lpjson['tree']['nodes'] as &$node) {
            if (isset($node['parentCourse']) && in_array('starting_node', $node['parentCourse'])) {
                $node = $this->inject_timed_restriction($node, $restrictiondate, true);
            }
        }
        return $DB->insert_record('local_adele_path_user', [
            'user_id'          => $userid,
            'course_id'        => $this->startingcourseid,
            'learning_path_id' => $this->adelestart->learningpathid,
            'status'           => 'active',
            'timecreated'      => time(),
            'timemodified'     => time(),
            'createdby'        => get_admin()->id,
            'json'             => json_encode([
                'tree'               => $lpjson['tree'],
                'modules'            => $lpjson['modules'] ?? null,
                // user_path_relation would be populated by updated_single on
                // the first evaluation; include an empty value here so
                // passnodevalues() does not warn about a missing key.
                'user_path_relation' => null,
            ]),
        ]);
    }

    /**
     * Simulate an admin saving the LP (date already updated in DB) and fully
     * process all affected user paths.
     *
     * Production flow:
     *   learnpath_updated (trigger) → observer → updated_learning_path()
     *   → user_path_updated (trigger, one per user) → observer → updated_single()
     *   → subscribe_user_starting_node() → set_scheduled_adhoc_tasks()
     *
     * In PHPUnit with redirectEvents() active, Moodle's trigger() intercepts ALL
     * events before observer dispatch (see base.php: "if PHPUNIT_TEST and
     * is_redirecting_events() { return; }").  We therefore:
     *   1. Call updated_learning_path() directly (bypasses trigger interception).
     *   2. updated_learning_path fires user_path_updated events via trigger(),
     *      which are captured in the sink (observers still skipped).
     *   3. We collect those events and drive updated_single() manually.
     */
    private function fire_lp_updated_and_process(): void {
        global $DB;
        $lp = $DB->get_record('local_adele_learning_paths',
                ['id' => $this->adelestart->learningpathid]);

        $event = \local_adele\event\learnpath_updated::create([
            'objectid' => $lp->id,
            'context'  => \context_system::instance(),
            'other'    => [
                'learningpathname' => $lp->name,
                'learningpathid'   => $lp->id,
                'userid'           => get_admin()->id,
                'json'             => $lp->json,
            ],
        ]);

        // Step 1: invoke the LP-update handler directly so user_path_updated
        // events end up in the sink without needing observer dispatch.
        learning_path_update::updated_learning_path($event);

        // Step 2: drive updated_single() for each captured user_path_updated event.
        foreach ($this->get_update_events() as $ev) {
            relation_update::updated_single($ev);
        }
    }

    /**
     * Calculate the expected adhoc task nextruntime for a given date string.
     * The helper adds 120 s (2-minute buffer) to the restriction timestamp.
     */
    private function expected_runtime(string $datestring): int {
        return strtotime($datestring) + 120;
    }

    // =========================================================================
    // A. Unit tests — direct set_scheduled_adhoc_tasks() calls.
    // =========================================================================

    /**
     * A1: A single future start date must produce exactly one queued adhoc task.
     */
    public function test_future_start_date_creates_one_task(): void {
        $user   = self::getDataGenerator()->create_user();
        $future = date('Y-m-d\TH:i', strtotime('+7 days'));

        adhoc_task_helper::set_scheduled_adhoc_tasks(
            $this->make_timed_node($future),
            $this->make_userpath($user->id)
        );

        $this->assertEquals(1, $this->count_adhoc_tasks(),
            'A future start date must schedule exactly one adhoc task.');
        $this->sink->close();
    }

    /**
     * A2: A past start date must NOT produce any adhoc task — the restriction
     * has already elapsed, so updated_single() grants access synchronously.
     * Scheduling a task here would create a perpetual loop: the task fires,
     * updated_single() runs, set_scheduled_adhoc_tasks() sees past date,
     * schedules another task, repeat every 60 seconds indefinitely.
     */
    public function test_past_start_date_creates_no_task(): void {
        $user = self::getDataGenerator()->create_user();
        $past = date('Y-m-d\TH:i', strtotime('-7 days'));

        adhoc_task_helper::set_scheduled_adhoc_tasks(
            $this->make_timed_node($past),
            $this->make_userpath($user->id)
        );

        $this->assertEquals(0, $this->count_adhoc_tasks(),
            'A past start date must not schedule any adhoc task.');
        $this->sink->close();
    }

    /**
     * A3: When BOTH start and end dates are in the future, two tasks must be
     * queued (one fires when the window opens, the other when it closes).
     */
    public function test_both_start_and_end_future_creates_two_tasks(): void {
        $user      = self::getDataGenerator()->create_user();
        $start     = date('Y-m-d\TH:i', strtotime('+3 days'));
        $end       = date('Y-m-d\TH:i', strtotime('+10 days'));
        $userpath  = $this->make_userpath($user->id);

        $node = $this->make_timed_node($start, $end);

        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);

        $this->assertEquals(2, $this->count_adhoc_tasks(),
            'Both a future start and a future end date must each schedule one adhoc task.');
        $this->sink->close();
    }

    /**
     * A4: When start date is past but end date is future, only the end-date
     * task is scheduled (start is skipped because it has already elapsed;
     * updated_single() handles access synchronously).
     */
    public function test_past_start_future_end_creates_one_task(): void {
        $user     = self::getDataGenerator()->create_user();
        $start    = date('Y-m-d\TH:i', strtotime('-3 days'));
        $end      = date('Y-m-d\TH:i', strtotime('+7 days'));
        $userpath = $this->make_userpath($user->id);

        $node = $this->make_timed_node($start, $end);

        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);

        $this->assertEquals(1, $this->count_adhoc_tasks(),
            'A past start + future end must schedule exactly one task (for the end date only).');
        $this->sink->close();
    }

    // =========================================================================
    // B. Integration — user NOT yet enrolled (fresh path creation).
    // =========================================================================

    /**
     * B1: When a user is enrolled for the first time in an LP whose starting
     * node has a future timed restriction, an adhoc task must be scheduled.
     */
    public function test_fresh_enrollment_future_restriction_creates_task(): void {
        // Modify LP in DB to have a future restriction before subscribe_users_to_lp()
        // creates the path records; the records will carry this restriction.
        $future = date('Y-m-d\TH:i', strtotime('+7 days'));
        $this->set_lp_restriction_date($future);

        // Create path records for the two users enrolled in the starting course.
        $this->subscribe_users_to_lp();

        // Drive the user_path_updated → updated_single chain.
        $updateevents = $this->get_update_events();
        $this->assertNotEmpty($updateevents,
            'Fresh enrollment must fire at least one user_path_updated event.');
        foreach ($updateevents as $ev) {
            relation_update::updated_single($ev);
        }

        $this->assertGreaterThanOrEqual(1, $this->count_adhoc_tasks(),
            'Fresh enrollment with a future timed restriction must schedule at least one adhoc task.');
        $this->sink->close();
    }

    /**
     * B2: When a user is enrolled for the first time and the LP's timed
     * restriction date is already in the past, no adhoc task should be created.
     * Access is granted synchronously by updated_single() evaluating the restriction;
     * a task here would create a perpetual 60-second re-run loop.
     */
    public function test_fresh_enrollment_past_restriction_creates_no_task(): void {
        $past = date('Y-m-d\TH:i', strtotime('-7 days'));
        $this->set_lp_restriction_date($past);

        $this->subscribe_users_to_lp();

        $updateevents = $this->get_update_events();
        foreach ($updateevents as $ev) {
            relation_update::updated_single($ev);
        }

        $this->assertEquals(0, $this->count_adhoc_tasks(),
            'Fresh enrollment with a past timed restriction must not schedule any adhoc task.');
        $this->sink->close();
    }

    // =========================================================================
    // C. Integration — user already enrolled, LP date changes.
    // =========================================================================

    /**
     * C1: The restriction date was in the past (user had access).
     * Admin moves the date to the future (access window reopens).
     * → A new adhoc task must be scheduled for the new future date.
     */
    public function test_enrolled_date_moved_from_past_to_future_reschedules_task(): void {
        global $DB;

        $user       = self::getDataGenerator()->create_user();
        $olddate    = date('Y-m-d\TH:i', strtotime('-1 day'));
        $newdate    = date('Y-m-d\TH:i', strtotime('+7 days'));

        // Simulate state: user path stored with the old (past) restriction.
        $this->insert_enrolled_user_path($user->id, $olddate);
        $this->assertEquals(0, $this->count_adhoc_tasks(), 'Precondition: no tasks yet.');

        // Admin saves LP with new future restriction date.
        $this->set_lp_restriction_date($newdate);
        $this->fire_lp_updated_and_process();

        $this->assertGreaterThanOrEqual(1, $this->count_adhoc_tasks(),
            'Moving a past restriction date back into the future must re-schedule an adhoc task.');

        // Verify the task fires at the expected time (LP date + 2-minute buffer).
        $task = $DB->get_record('task_adhoc', ['classname' => '\\local_adele\\task\\update_user_path']);
        $this->assertEqualsWithDelta(
            $this->expected_runtime($newdate),
            (int) $task->nextruntime,
            5,
            'Task nextruntime must match the new restriction date (± 5 s).'
        );
        $this->sink->close();
    }

    /**
     * C2: The restriction date was already in the future, but the admin moves it
     * even further into the future (the window opens later than originally).
     * → A new adhoc task must be scheduled for the updated (later) date.
     */
    public function test_enrolled_date_moved_further_into_future_reschedules_task(): void {
        global $DB;

        $user    = self::getDataGenerator()->create_user();
        $olddate = date('Y-m-d\TH:i', strtotime('+7 days'));
        $newdate = date('Y-m-d\TH:i', strtotime('+14 days'));

        // User already enrolled with a future restriction (no task yet in this test scenario
        // because we insert the path directly, bypassing the enrollment event chain).
        $this->insert_enrolled_user_path($user->id, $olddate);
        $this->assertEquals(0, $this->count_adhoc_tasks(), 'Precondition: no tasks yet.');

        // Admin pushes the date further into the future.
        $this->set_lp_restriction_date($newdate);
        $this->fire_lp_updated_and_process();

        $this->assertGreaterThanOrEqual(1, $this->count_adhoc_tasks(),
            'After moving the date further into the future, a new task must be scheduled.');

        // Confirm the task targets the new (later) date, not the old one.
        $task = $DB->get_record('task_adhoc', ['classname' => '\\local_adele\\task\\update_user_path']);
        $this->assertEqualsWithDelta(
            $this->expected_runtime($newdate),
            (int) $task->nextruntime,
            5,
            'Task nextruntime must match the new (later) restriction date (± 5 s).'
        );
        $this->sink->close();
    }

    /**
     * C3: The restriction date was in the future, but the admin moves it
     * closer (the window opens sooner than originally planned).
     * → A new adhoc task must be scheduled for the earlier date.
     */
    public function test_enrolled_date_moved_earlier_but_still_future_reschedules_task(): void {
        global $DB;

        $user    = self::getDataGenerator()->create_user();
        $olddate = date('Y-m-d\TH:i', strtotime('+14 days'));
        $newdate = date('Y-m-d\TH:i', strtotime('+3 days'));

        $this->insert_enrolled_user_path($user->id, $olddate);
        $this->assertEquals(0, $this->count_adhoc_tasks(), 'Precondition: no tasks yet.');

        // Admin pulls the date sooner (still future).
        $this->set_lp_restriction_date($newdate);
        $this->fire_lp_updated_and_process();

        $this->assertGreaterThanOrEqual(1, $this->count_adhoc_tasks(),
            'After moving the date to a sooner (still future) date, a new task must be scheduled.');

        // Confirm the task targets the new (sooner) date.
        $task = $DB->get_record('task_adhoc', ['classname' => '\\local_adele\\task\\update_user_path']);
        $this->assertEqualsWithDelta(
            $this->expected_runtime($newdate),
            (int) $task->nextruntime,
            5,
            'Task nextruntime must match the new (sooner) restriction date (± 5 s).'
        );
        $this->sink->close();
    }

    /**
     * C4: The restriction date was in the future, but the admin moves it to the
     * past (effectively removing the restriction immediately).
     * → No new adhoc task must be scheduled. updated_single() already re-evaluates
     * all nodes synchronously (via reschedule_timed_restrictions_for_all_nodes)
     * and grants access immediately. A task for a past date would create a
     * perpetual 60-second re-run loop.
     */
    public function test_enrolled_date_moved_from_future_to_past_creates_no_task(): void {
        $user    = self::getDataGenerator()->create_user();
        $olddate = date('Y-m-d\TH:i', strtotime('+7 days'));
        $newdate = date('Y-m-d\TH:i', strtotime('-1 day'));

        $this->insert_enrolled_user_path($user->id, $olddate);
        $this->assertEquals(0, $this->count_adhoc_tasks(), 'Precondition: no tasks yet.');

        // Admin moves the date to the past.
        $this->set_lp_restriction_date($newdate);
        $this->fire_lp_updated_and_process();

        $this->assertEquals(0, $this->count_adhoc_tasks(),
            'After moving the restriction date to the past, no adhoc task must be scheduled ' .
            '(the condition is immediately satisfied by the synchronous updated_single call).');
        $this->sink->close();
    }

    /**
     * C5: When an admin changes a restriction date from one future date to another,
     * the existing adhoc task must be rescheduled (nextruntime updated) rather than
     * a second task being inserted. The task count must stay at 1.
     *
     * This verifies that the dedup key in set_scheduled_adhoc_tasks() is stable
     * across date changes (slot-based: node-id + start|end + userpath-id), so
     * reschedule_or_queue_adhoc_task() can always find and update the existing row.
     */
    public function test_date_change_reschedules_existing_task_not_duplicate(): void {
        global $DB;
        $user     = self::getDataGenerator()->create_user();
        $olddate  = date('Y-m-d\TH:i', strtotime('+7 days'));
        $newdate  = date('Y-m-d\TH:i', strtotime('+14 days'));
        $userpath = $this->make_userpath($user->id);

        // First call: creates the task.
        adhoc_task_helper::set_scheduled_adhoc_tasks($this->make_timed_node($olddate), $userpath);
        $this->assertEquals(1, $this->count_adhoc_tasks(), 'Precondition: 1 task after first schedule.');

        // Second call with a different date: must update the existing row, not add one.
        adhoc_task_helper::set_scheduled_adhoc_tasks($this->make_timed_node($newdate), $userpath);
        $this->assertEquals(1, $this->count_adhoc_tasks(),
            'Changing the date must reschedule the existing task, not create a duplicate.');

        $task = $DB->get_record('task_adhoc', ['classname' => '\\local_adele\\task\\update_user_path']);
        $this->assertEqualsWithDelta(
            $this->expected_runtime($newdate),
            (int) $task->nextruntime,
            5,
            'Task nextruntime must reflect the new date after rescheduling.'
        );
        $this->sink->close();
    }

    // =========================================================================
    // D. Integration — child (non-starting) node date changes.
    // =========================================================================

    /**
     * Inject a timed restriction into a specific LP node identified by $nodeid,
     * then persist the LP back to the database.
     *
     * @param string $nodeid    e.g. 'dndnode_2'
     * @param string $startdate Format 'Y-m-d\TH:i'
     */
    private function set_node_restriction_date(string $nodeid, string $startdate): void {
        global $DB;
        $lp     = $DB->get_record('local_adele_learning_paths',
                    ['id' => $this->adelestart->learningpathid]);
        $lpjson = json_decode($lp->json, true);
        foreach ($lpjson['tree']['nodes'] as &$node) {
            if ($node['id'] === $nodeid) {
                $node = $this->inject_timed_restriction($node, $startdate);
            }
        }
        $lp->json = json_encode($lpjson);
        $DB->update_record('local_adele_learning_paths', $lp);
    }

    /**
     * Insert a user-path record whose starting node (`dndnode_1`) carries the
     * given $startdate restriction and whose child node (`dndnode_2`) carries
     * the given $childdate restriction.  Both nodes have first_enrolled set so
     * they look like an already-enrolled user.
     *
     * @param int    $userid
     * @param string $startdate  Restriction for dndnode_1 (e.g. past date)
     * @param string $childdate  Restriction for dndnode_2 (e.g. past or future date)
     * @return int  The new record id.
     */
    private function insert_enrolled_user_path_with_child(
        int $userid,
        string $startdate,
        string $childdate
    ): int {
        global $DB;
        $lp     = $DB->get_record('local_adele_learning_paths',
                    ['id' => $this->adelestart->learningpathid]);
        $lpjson = json_decode($lp->json, true);
        foreach ($lpjson['tree']['nodes'] as &$node) {
            if (in_array('starting_node', $node['parentCourse'] ?? [])) {
                $node = $this->inject_timed_restriction($node, $startdate, true);
            } else if ($node['type'] !== 'dropzone') {
                $node = $this->inject_timed_restriction($node, $childdate, true);
            }
        }
        return $DB->insert_record('local_adele_path_user', [
            'user_id'          => $userid,
            'course_id'        => $this->startingcourseid,
            'learning_path_id' => $this->adelestart->learningpathid,
            'status'           => 'active',
            'timecreated'      => time(),
            'timemodified'     => time(),
            'createdby'        => get_admin()->id,
            'json'             => json_encode([
                'tree'               => $lpjson['tree'],
                'modules'            => $lpjson['modules'] ?? null,
                'user_path_relation' => null,
            ]),
        ]);
    }

    /**
     * D1: A future timed restriction is added to a child node (not a starting
     * node) and the LP is saved.
     * → An adhoc task must be scheduled for the child node's restriction.
     *
     * Previously only starting nodes were processed by subscribe_user_starting_node;
     * reschedule_timed_restrictions_for_all_nodes() covers the gap.
     */
    public function test_child_node_future_restriction_on_lp_update_creates_task(): void {
        $user    = self::getDataGenerator()->create_user();
        $past    = date('Y-m-d\TH:i', strtotime('-7 days'));
        $future  = date('Y-m-d\TH:i', strtotime('+7 days'));

        // User is enrolled: starting node restriction is in the past (user has access),
        // child node currently has a past restriction (also accessible).
        $this->insert_enrolled_user_path_with_child($user->id, $past, $past);
        $this->assertEquals(0, $this->count_adhoc_tasks(), 'Precondition: no tasks yet.');

        // Admin adds / moves the child node restriction to the future.
        $this->set_node_restriction_date('dndnode_2', $future);
        $this->fire_lp_updated_and_process();

        $this->assertGreaterThanOrEqual(1, $this->count_adhoc_tasks(),
            'After adding a future restriction to a child node, an adhoc task must be scheduled ' .
            '(reschedule_timed_restrictions_for_all_nodes must process non-starting nodes).');
        $this->sink->close();
    }

    /**
     * D2 (core regression): User had access to a child node (restriction date
     * was in the past).  Admin moves that restriction date into the future
     * (access window closes again).
     * → A new adhoc task must be scheduled for the new future date.
     *
     * This is the scenario that was completely missed before: node_finished does
     * not fire again (the parent node was already complete), so enrol_child_courses
     * never ran.  Only reschedule_timed_restrictions_for_all_nodes() now ensures
     * the task gets created on every LP update.
     */
    public function test_child_node_date_moved_from_past_to_future_reschedules_task(): void {
        global $DB;

        $user       = self::getDataGenerator()->create_user();
        $past       = date('Y-m-d\TH:i', strtotime('-1 day'));   // both nodes accessible
        $newfuture  = date('Y-m-d\TH:i', strtotime('+7 days'));  // child access revoked

        // User already enrolled, both starting and child restrictions are in the past.
        $this->insert_enrolled_user_path_with_child($user->id, $past, $past);
        $this->assertEquals(0, $this->count_adhoc_tasks(), 'Precondition: no tasks yet.');

        // Admin moves only the child node restriction to the future.
        $this->set_node_restriction_date('dndnode_2', $newfuture);
        $this->fire_lp_updated_and_process();

        $this->assertGreaterThanOrEqual(1, $this->count_adhoc_tasks(),
            'Moving a past child-node restriction back into the future must schedule a new ' .
            'adhoc task even though node_finished never fires again for that path.');

        // Optionally verify the task targets the correct time.
        $task = $DB->get_record('task_adhoc', ['classname' => '\\local_adele\\task\\update_user_path']);
        $this->assertEqualsWithDelta(
            $this->expected_runtime($newfuture),
            (int) $task->nextruntime,
            5,
            'Task nextruntime must match the new child-node restriction date (± 5 s).'
        );
        $this->sink->close();
    }
}
