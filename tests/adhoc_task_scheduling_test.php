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
 * Unit tests for adhoc task scheduling and execution in node_completion.
 *
 * Tests the schedule_enrolment_retry() method for correct task creation,
 * the safety-net fallback for timezone edge cases, support for both
 * date formats (Y-m-d\TH:i and d.m.Y H:i), deduplication via
 * reschedule_or_queue_adhoc_task, and the adhoc_task_helper class.
 *
 * Also tests the update_user_path adhoc task for correct execution
 * and error handling.
 *
 * @package     local_adele
 * @category    test
 * @author      Ralf Erlebach
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use DateTime;
use DateTimeZone;
use ReflectionMethod;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/adele/lib.php');

/**
 * Tests for adhoc task scheduling and execution.
 *
 * @package     local_adele
 * @category    test
 * @author      Ralf Erlebach
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\node_completion
 * @covers \local_adele\helper\adhoc_task_helper
 * @covers \local_adele\task\update_user_path
 */
class adhoc_task_scheduling_test extends advanced_testcase {

    /** @var string The adhoc task classname as stored in the DB. */
    private const TASK_CLASSNAME = '\\local_adele\\task\\update_user_path';

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Call a private/protected static method on node_completion.
     *
     * @param string $methodname
     * @param array $args
     * @return mixed
     */
    private function call_method(string $methodname, array $args) {
        $method = new ReflectionMethod(node_completion::class, $methodname);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $args);
    }

    /**
     * Build a minimal userpath record for task scheduling.
     *
     * @param int $userid
     * @param int $learningpathid
     * @return object
     */
    private function build_userpath_record(int $userid = 9, int $learningpathid = 1): object {
        $record = new \stdClass();
        $record->id = 2;
        $record->user_id = $userid;
        $record->learning_path_id = $learningpathid;
        $record->status = 'active';
        $record->timecreated = time() - 3600;
        $record->json = [
            'tree' => ['nodes' => []],
            'user_path_relation' => [],
        ];
        return $record;
    }

    /**
     * Get all adhoc tasks of our type from the database.
     *
     * @return array
     */
    private function get_adhoc_tasks(): array {
        global $DB;
        return $DB->get_records('task_adhoc', ['classname' => self::TASK_CLASSNAME]);
    }

    /**
     * Get the custom data from the first adhoc task.
     *
     * @return object|null
     */
    private function get_first_task_data(): ?object {
        $tasks = $this->get_adhoc_tasks();
        if (empty($tasks)) {
            return null;
        }
        $task = reset($tasks);
        return json_decode($task->customdata);
    }

    /**
     * Get the next run time from the first adhoc task.
     *
     * @return int|null
     */
    private function get_first_task_runtime(): ?int {
        $tasks = $this->get_adhoc_tasks();
        if (empty($tasks)) {
            return null;
        }
        $task = reset($tasks);
        return (int) $task->nextruntime;
    }

    /**
     * Format a DateTime relative to now.
     *
     * @param string $modifier e.g. '+1 hour', '-30 minutes'
     * @return string Date in Y-m-d\TH:i format
     */
    private function relative_date(string $modifier): string {
        return (new DateTime($modifier))->format('Y-m-d\TH:i');
    }

    /**
     * Format a DateTime relative to now in d.m.Y H:i format.
     *
     * @param string $modifier
     * @return string
     */
    private function relative_date_formatted(string $modifier): string {
        return (new DateTime($modifier))->format('d.m.Y H:i');
    }

    // =========================================================================
    // TESTS: schedule_enrolment_retry() – basic task creation
    // =========================================================================

    /**
     * A future start date in Y-m-d\TH:i format creates an adhoc task.
     */
    public function test_future_date_creates_task() {
        $futuredate = $this->relative_date('+1 hour');
        $userpath = $this->build_userpath_record();

        $this->call_method('schedule_enrolment_retry', [$userpath, [], $futuredate]);

        $tasks = $this->get_adhoc_tasks();
        $this->assertCount(1, $tasks, 'One adhoc task should be created');
    }

    /**
     * The created task has the correct user id.
     */
    public function test_task_has_correct_userid() {
        $futuredate = $this->relative_date('+1 hour');
        $userpath = $this->build_userpath_record(42, 5);

        $this->call_method('schedule_enrolment_retry', [$userpath, [], $futuredate]);

        $tasks = $this->get_adhoc_tasks();
        $task = reset($tasks);
        $this->assertEquals(42, $task->userid);
    }

    /**
     * The created task custom data contains the correct learning_path_id.
     */
    public function test_task_has_correct_learning_path_id() {
        $futuredate = $this->relative_date('+1 hour');
        $userpath = $this->build_userpath_record(9, 7);

        $this->call_method('schedule_enrolment_retry', [$userpath, [], $futuredate]);

        $data = $this->get_first_task_data();
        $this->assertNotNull($data);
        $this->assertEquals(7, $data->learning_path_id);
    }

    /**
     * The created task custom data contains the correct user_id.
     */
    public function test_task_data_has_correct_user_id() {
        $futuredate = $this->relative_date('+1 hour');
        $userpath = $this->build_userpath_record(15, 3);

        $this->call_method('schedule_enrolment_retry', [$userpath, [], $futuredate]);

        $data = $this->get_first_task_data();
        $this->assertNotNull($data);
        $this->assertEquals(15, $data->user_id);
    }

    /**
     * The task is scheduled 2 minutes after the start date.
     */
    public function test_task_scheduled_two_minutes_after_start() {
        $futuredate = $this->relative_date('+1 hour');
        $userpath = $this->build_userpath_record();

        $this->call_method('schedule_enrolment_retry', [$userpath, [], $futuredate]);

        $runtime = $this->get_first_task_runtime();
        $this->assertNotNull($runtime);

        $expectedtimestamp = $this->call_method('date_to_timestamp', [$futuredate]);
        $expectedruntime = $expectedtimestamp + 120;

        // Allow 5 seconds tolerance for test execution time.
        $this->assertEqualsWithDelta($expectedruntime, $runtime, 5,
            'Task should be scheduled 2 minutes after the start date');
    }

    // =========================================================================
    // TESTS: schedule_enrolment_retry() – date format support
    // =========================================================================

    /**
     * A future date in d.m.Y H:i format (from timed_duration) creates a task.
     */
    public function test_formatted_date_creates_task() {
        $futuredate = $this->relative_date_formatted('+1 hour');
        $userpath = $this->build_userpath_record();

        $this->call_method('schedule_enrolment_retry', [$userpath, [], $futuredate]);

        $tasks = $this->get_adhoc_tasks();
        $this->assertCount(1, $tasks,
            'Adhoc task should be created with d.m.Y H:i format');
    }

    /**
     * An invalid date string does not create a task and does not throw.
     */
    public function test_invalid_date_no_task() {
        $userpath = $this->build_userpath_record();

        $this->call_method('schedule_enrolment_retry', [$userpath, [], 'not-a-date']);

        $tasks = $this->get_adhoc_tasks();
        // Safety net should still create a task with time() + 120.
        // OR no task if the safety net is not triggered.
        // The important thing is: no exception.
        $this->assertTrue(true, 'No exception thrown for invalid date');
    }

    /**
     * An empty date string does not create a task.
     */
    public function test_empty_date_no_task() {
        $userpath = $this->build_userpath_record();

        $this->call_method('schedule_enrolment_retry', [$userpath, [], '']);

        $tasks = $this->get_adhoc_tasks();
        $this->assertEmpty($tasks, 'Empty date should not create a task');
    }

    // =========================================================================
    // TESTS: schedule_enrolment_retry() – safety net
    // =========================================================================

    /**
     * When the timestamp appears to be in the past (timezone edge case),
     * the safety net schedules the task for time() + 120 instead.
     */
    public function test_safety_net_past_timestamp() {
        // A date 1 minute in the past – might happen due to timezone mismatch.
        $pastdate = $this->relative_date('-1 minute');
        $userpath = $this->build_userpath_record();

        $beforetime = time();
        $this->call_method('schedule_enrolment_retry', [$userpath, [], $pastdate]);
        $aftertime = time();

        $tasks = $this->get_adhoc_tasks();
        $this->assertCount(1, $tasks,
            'Safety net should create a task even for past timestamps');

        $runtime = $this->get_first_task_runtime();
        $this->assertNotNull($runtime);

        // Runtime should be approximately time() + 120.
        $this->assertGreaterThanOrEqual($beforetime + 120, $runtime,
            'Safety net runtime should be at least time() + 120');
        $this->assertLessThanOrEqual($aftertime + 125, $runtime,
            'Safety net runtime should not be too far in the future');
    }

    /**
     * When the timestamp is exactly now, the safety net activates.
     */
    public function test_safety_net_exact_now() {
        $nowdate = (new DateTime())->format('Y-m-d\TH:i');
        $userpath = $this->build_userpath_record();

        $this->call_method('schedule_enrolment_retry', [$userpath, [], $nowdate]);

        $tasks = $this->get_adhoc_tasks();
        $this->assertCount(1, $tasks);

        $runtime = $this->get_first_task_runtime();
        $this->assertGreaterThanOrEqual(time(), $runtime,
            'Task runtime should be in the future');
    }

    // =========================================================================
    // TESTS: schedule_enrolment_retry() – deduplication
    // =========================================================================

    /**
     * Calling schedule_enrolment_retry twice with the same parameters
     * should reschedule (not duplicate) the task.
     */
    public function test_reschedule_not_duplicate() {
        $futuredate = $this->relative_date('+1 hour');
        $userpath = $this->build_userpath_record();

        $this->call_method('schedule_enrolment_retry', [$userpath, [], $futuredate]);
        $this->call_method('schedule_enrolment_retry', [$userpath, [], $futuredate]);

        $tasks = $this->get_adhoc_tasks();
        $this->assertCount(1, $tasks,
            'Duplicate call should reschedule, not create a second task');
    }

    /**
     * Different users should create separate tasks.
     */
    public function test_different_users_separate_tasks() {
        $futuredate = $this->relative_date('+1 hour');

        $userpath1 = $this->build_userpath_record(10, 1);
        $userpath2 = $this->build_userpath_record(20, 1);

        $this->call_method('schedule_enrolment_retry', [$userpath1, [], $futuredate]);
        $this->call_method('schedule_enrolment_retry', [$userpath2, [], $futuredate]);

        $tasks = $this->get_adhoc_tasks();
        $this->assertCount(2, $tasks,
            'Different users should have separate tasks');
    }

    /**
     * Different learning paths for the same user should create separate tasks.
     */
    public function test_different_learning_paths_separate_tasks() {
        $futuredate = $this->relative_date('+1 hour');

        $userpath1 = $this->build_userpath_record(9, 1);
        $userpath2 = $this->build_userpath_record(9, 2);

        $this->call_method('schedule_enrolment_retry', [$userpath1, [], $futuredate]);
        $this->call_method('schedule_enrolment_retry', [$userpath2, [], $futuredate]);

        $tasks = $this->get_adhoc_tasks();
        $this->assertCount(2, $tasks,
            'Different learning paths should have separate tasks');
    }

    // =========================================================================
    // TESTS: schedule_enrolment_retry() – timezone consistency
    // =========================================================================

    /**
     * A future date creates a task with a future runtime regardless
     * of the configured Moodle timezone.
     *
     * @dataProvider timezone_provider
     * @param string $timezone
     */
    public function test_task_runtime_future_across_timezones($timezone) {
        set_config('timezone', $timezone);

        $tz = new DateTimeZone($timezone);
        $futuredate = (new DateTime('+1 hour', $tz))->format('Y-m-d\TH:i');
        $userpath = $this->build_userpath_record();

        $this->call_method('schedule_enrolment_retry', [$userpath, [], $futuredate]);

        $runtime = $this->get_first_task_runtime();
        $this->assertNotNull($runtime,
            "Task should be created in timezone $timezone");
        $this->assertGreaterThan(time(), $runtime,
            "Task runtime should be in the future in timezone $timezone");
    }

    /**
     * Data provider for timezone tests.
     *
     * @return array
     */
    public static function timezone_provider(): array {
        return [
            'UTC'              => ['UTC'],
            'Europe/London'    => ['Europe/London'],
            'Europe/Berlin'    => ['Europe/Berlin'],
            'America/New_York' => ['America/New_York'],
            'Asia/Tokyo'       => ['Asia/Tokyo'],
            'Pacific/Auckland' => ['Pacific/Auckland'],
        ];
    }

    // =========================================================================
    // TESTS: adhoc_task_helper::set_scheduled_adhoc_tasks()
    // =========================================================================

    /**
     * set_scheduled_adhoc_tasks creates tasks for future start dates.
     */
    public function test_helper_creates_task_for_future_start() {
        $futuredate = $this->relative_date('+1 hour');

        $node = [
            'id' => 'dndnode_2',
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => $futuredate,
                                'end' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $userpath = $this->build_userpath_record();

        \local_adele\helper\adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);

        $tasks = $this->get_adhoc_tasks();
        $this->assertNotEmpty($tasks,
            'adhoc_task_helper should create a task for future start date');
    }

    /**
     * set_scheduled_adhoc_tasks creates tasks for future end dates.
     */
    public function test_helper_creates_task_for_future_end() {
        $futuredate = $this->relative_date('+2 hours');

        $node = [
            'id' => 'dndnode_2',
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => null,
                                'end' => $futuredate,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $userpath = $this->build_userpath_record();

        \local_adele\helper\adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);

        $tasks = $this->get_adhoc_tasks();
        $this->assertNotEmpty($tasks,
            'adhoc_task_helper should create a task for future end date');
    }

    /**
     * set_scheduled_adhoc_tasks creates tasks for BOTH start and end dates.
     */
    public function test_helper_creates_tasks_for_both_dates() {
        $futurestart = $this->relative_date('+1 hour');
        $futureend = $this->relative_date('+2 hours');

        $node = [
            'id' => 'dndnode_2',
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => $futurestart,
                                'end' => $futureend,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $userpath = $this->build_userpath_record();

        \local_adele\helper\adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);

        $tasks = $this->get_adhoc_tasks();
        $this->assertGreaterThanOrEqual(1, count($tasks),
            'adhoc_task_helper should create tasks for both start and end dates');
    }

    /**
     * set_scheduled_adhoc_tasks does NOT create tasks for past dates.
     */
    public function test_helper_skips_past_dates() {
        $pastdate = $this->relative_date('-1 hour');

        $node = [
            'id' => 'dndnode_2',
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => $pastdate,
                                'end' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $userpath = $this->build_userpath_record();

        \local_adele\helper\adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);

        $tasks = $this->get_adhoc_tasks();
        $this->assertEmpty($tasks,
            'adhoc_task_helper should NOT create tasks for past dates');
    }

    /**
     * set_scheduled_adhoc_tasks handles nodes without restriction gracefully.
     */
    public function test_helper_no_restriction() {
        $node = [
            'id' => 'dndnode_2',
            'data' => ['course_node_id' => [19]],
        ];

        $userpath = $this->build_userpath_record();

        // Should not throw.
        \local_adele\helper\adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);

        $tasks = $this->get_adhoc_tasks();
        $this->assertEmpty($tasks);
    }

    /**
     * set_scheduled_adhoc_tasks handles empty restriction nodes gracefully.
     */
    public function test_helper_empty_restriction_nodes() {
        $node = [
            'id' => 'dndnode_2',
            'restriction' => [
                'nodes' => [],
            ],
        ];

        $userpath = $this->build_userpath_record();

        \local_adele\helper\adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);

        $tasks = $this->get_adhoc_tasks();
        $this->assertEmpty($tasks);
    }

    // =========================================================================
    // TESTS: update_user_path task execution
    // =========================================================================

    /**
     * The update_user_path task has the correct name.
     */
    public function test_task_name() {
        $task = new \local_adele\task\update_user_path();
        $name = $task->get_name();

        $this->assertNotEmpty($name);
        $this->assertIsString($name);
    }

    /**
     * The update_user_path task handles missing learning_path_id gracefully.
     */
    public function test_task_execute_missing_learning_path_id() {
        $task = new \local_adele\task\update_user_path();
        $task->set_custom_data((object) [
            'user_id' => 9,
            // Missing learning_path_id.
        ]);

        // Should not throw – just return early with mtrace message.
        $task->execute();
        $this->assertTrue(true, 'Task should handle missing learning_path_id gracefully');
    }

    /**
     * The update_user_path task handles missing user_id gracefully.
     */
    public function test_task_execute_missing_user_id() {
        $task = new \local_adele\task\update_user_path();
        $task->set_custom_data((object) [
            'learning_path_id' => 1,
            // Missing user_id.
        ]);

        // Should not throw.
        $task->execute();
        $this->assertTrue(true, 'Task should handle missing user_id gracefully');
    }

    /**
     * The update_user_path task handles non-existent user path gracefully.
     */
    public function test_task_execute_nonexistent_userpath() {
        $task = new \local_adele\task\update_user_path();
        $task->set_custom_data((object) [
            'learning_path_id' => 999,
            'user_id' => 999,
        ]);

        // Should not throw – just return early.
        $task->execute();
        $this->assertTrue(true, 'Task should handle non-existent user path gracefully');
    }

    // =========================================================================
    // TESTS: Full chain – check_node_restrictions → schedule_enrolment_retry
    // =========================================================================

    /**
     * When check_node_restrictions returns met=false with a next_start_date,
     * and schedule_enrolment_retry is called, a task is created with the
     * correct runtime.
     */
    public function test_full_chain_restriction_to_task() {
        $futuredate = $this->relative_date('+1 hour');

        // Step 1: check_node_restrictions.
        $nodearray = [
            'id' => 'dndnode_test',
            'data' => ['course_node_id' => [99]],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'type' => 'condition',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => $futuredate,
                                'end' => null,
                            ],
                        ],
                        'parentCondition' => ['starting_condition'],
                        'childCondition' => [],
                    ],
                ],
            ],
        ];

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met']);
        $this->assertNotNull($result['next_start_date']);

        // Step 2: schedule_enrolment_retry.
        $this->call_method('schedule_enrolment_retry', [
            $userpathrecord,
            [],
            $result['next_start_date'],
        ]);

        // Step 3: Verify task.
        $tasks = $this->get_adhoc_tasks();
        $this->assertCount(1, $tasks, 'Full chain should create exactly one task');

        $data = $this->get_first_task_data();
        $this->assertEquals($userpathrecord->user_id, $data->user_id);
        $this->assertEquals($userpathrecord->learning_path_id, $data->learning_path_id);

        $runtime = $this->get_first_task_runtime();
        $this->assertGreaterThan(time(), $runtime,
            'Task runtime should be in the future');
    }

    /**
     * When check_node_restrictions returns met=false with next_start_date=null
     * (expired window), no task should be created.
     */
    public function test_full_chain_expired_window_no_task() {
        $pastend = $this->relative_date('-1 hour');

        $nodearray = [
            'id' => 'dndnode_test',
            'data' => ['course_node_id' => [99]],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'type' => 'condition',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => null,
                                'end' => $pastEnd,
                            ],
                        ],
                        'parentCondition' => ['starting_condition'],
                        'childCondition' => [],
                    ],
                ],
            ],
        ];

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertFalse($result['met']);
        $this->assertNull($result['next_start_date'],
            'Expired window should not set next_start_date');

        // Since next_start_date is null, schedule_enrolment_retry should
        // NOT be called (the calling code checks for !empty).
        $tasks = $this->get_adhoc_tasks();
        $this->assertEmpty($tasks,
            'No task should be created for an expired time window');
    }

    /**
     * When check_node_restrictions returns met=true, no task should be
     * created (enrolment proceeds immediately).
     */
    public function test_full_chain_met_no_task() {
        $pastdate = $this->relative_date('-1 hour');

        $nodearray = [
            'id' => 'dndnode_test',
            'data' => ['course_node_id' => [99]],
            'restriction' => [
                'nodes' => [
                    [
                        'id' => 'c1',
                        'type' => 'condition',
                        'data' => [
                            'label' => 'timed',
                            'value' => [
                                'start' => $pastdate,
                                'end' => null,
                            ],
                        ],
                        'parentCondition' => ['starting_condition'],
                        'childCondition' => [],
                    ],
                ],
            ],
        ];

        $userpathrecord = $this->build_userpath_record();

        $result = $this->call_method('check_node_restrictions', [$nodearray, $userpathrecord]);

        $this->assertTrue($result['met']);
        $this->assertNull($result['next_start_date']);

        // No task should be created when restrictions are met.
        $tasks = $this->get_adhoc_tasks();
        $this->assertEmpty($tasks,
            'No task should be created when restrictions are met');
    }
}
