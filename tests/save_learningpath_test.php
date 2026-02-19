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

namespace local_adele;

use advanced_testcase;
use core\event\course_module_created;
use local_adele\course_completion\course_completion_status;
use local_adele\course_restriction\course_restriction_status;
use local_adele\event\user_path_updated;
use local_adele\node_completion;
use mod_adele_observer;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
// Coverage is declared via PHP 8 attributes on the class instead of @covers docblock annotations.
/**
 * PHPUnit test case for the 'catquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_module_created::class)]
#[CoversClass(relation_update::class)]
#[CoversClass(enrollment::class)]
#[CoversMethod(mod_adele_observer::class, 'saved_module')]
#[CoversMethod(relation_update::class, 'validatenodecompletion')]
#[CoversMethod(node_completion::class, 'enrol_child_courses')]
final class save_learningpath_test extends advanced_testcase {
    /**
     * @var array Array of course IDs created during test setup, storing IDs for 5 test courses.
     */
    private $courseids;
    /**
     * @var \phpunit_event_sink Event sink for capturing and testing events during test execution.
     */
    private $sink;
    /**
     * @var int The ID of the course designated as the starting point for the learning path.
     */
    private $startingcourseid;
    /**
     * @var object The instance of the Adele activity created in the starting course during setup.
     */
    private $adelestart;
    protected function setUp(): void {
        global $DB;
        parent::setUp();

        // Reset Moodle database.
        $this->resetAfterTest(true);

        // Create 5 courses.
        $generator = self::getDataGenerator();
        $this->courseids = [];
        for ($i = 1; $i <= 5; $i++) {
            $course = $generator->create_course(['fullname' => 'Test Course ' . $i, 'enablecompletion' => 1]);
            $this->courseids[] = $course->id;
        }

        // Add users to the first course.
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $generator->enrol_user($user1->id, $this->courseids[0]);
        $generator->enrol_user($user2->id, $this->courseids[0]);

        // Choose the first course as the "starting course".
        $this->startingcourseid = $this->courseids[0];

        $jsonstring = file_get_contents(__DIR__ . '/fixtures/' . 'alise_zugangs_lp_einfach.json');

        $jsonarray = json_decode($jsonstring, true);

        $nodedata = json_decode($jsonarray['json'], true);

        foreach ($nodedata['tree']['nodes'] as &$node) {
            if (isset($node['data']['course_node_id'])) {
                // Dndnode_1 (starting node A): two courses, to satisfy min_courses=2 completion.
                // Dndnode_2 (child node B): one distinct course for unambiguous enrollment assertions.
                if ($node['id'] === 'dndnode_2') {
                    $node['data']['course_node_id'] = [$this->courseids[2]];
                } else {
                    $node['data']['course_node_id'] = [
                        $this->courseids[0],
                        $this->courseids[3],
                    ];
                }
            }
        }
        $jsonarray['json'] = json_encode($nodedata);

        $lpid = $generator->get_plugin_generator('local_adele')->create_adele_learningpaths($jsonarray);

        // Redirect events to an event sink.
        $this->sink = $this->redirectEvents();

        // Create an instance of mod_adele in the starting course.
        $this->adelestart = $generator->get_plugin_generator('mod_adele')->create_instance([
            'course' => $this->startingcourseid,
            'name' => 'Adele Activity',
            'participantslist' => [1],
            'learningpathid' => $lpid,
        ]);
    }

    /**
     * Test case to verify course and activity setup functionality.
     * This test creates multiple courses and activities to ensure proper setup.
     * It specifically:
     * - Creates 5 test courses
     * - Sets up a learning path using a JSON file
     * - Creates an Adele activity instance in the first course
     * - Creates a quiz activity in the second course
     * - Verifies the creation and proper assignment of these elements
     * @return void
     */
    public function test_course_module_created(): void {

        // Fetch events.
        $events = $this->sink->get_events();

        // Verify that 5 courses were created.
        $this->assertCount(5, $this->courseids);

        // Verify course module creation event was captured exactly once.
        $createdevents = array_filter($events, fn($event) => $event->eventname === '\core\event\course_module_created');
        $this->assertCount(1, $createdevents, "Expected course_module_created event to be called exactly once.");

        // Clean up the event sink.
        $this->sink->close();
    }

    /**
     * Test case to verify the subscription of users to a learning path.
     * This test ensures that:
     * - Users can be successfully subscribed to a learning path
     * - The appropriate events are triggered during subscription
     * - The subscription data is correctly stored in the database
     * - The event observer handles the module creation correctly
     * - The expected number of path user entries are created
     *
     * @return void
     */
    public function test_subscribe_user_to_learning_path(): void {
        global $DB;
        // Fetch events.
        $events = $this->sink->get_events();
        // Verify course module creation event was captured exactly once.
        $createdevents = array_filter($events, fn($event) => $event->eventname === '\core\event\course_module_created');
        $eventsingle = $createdevents[0];
        mod_adele_observer::saved_module($eventsingle);

        $getlps = $DB->get_records('local_adele_path_user');
        $this->assertCount(2, $getlps, "Expected local_adele_path_user' to have 2 entries.");
        // Clean up the event sink.
        $this->sink->close();
    }


    /**
     * Test case to verify user enrollment functionality when a learning path is initiated.
     * This test specifically:
     * - Checks if users are correctly enrolled when a learning path starts
     * - Verifies event handling for module creation and user path updates
     * - Confirms proper enrollment of users in subsequent courses
     * - Validates the creation of adhoc tasks
     *
     *
     * @return void
     */
    public function test_user_path_starting_node_enrollment(): void {
        global $DB;
        // Fetch events.
        $events = $this->sink->get_events();
        // Verify course module creation event was captured exactly once.
        $createdevents = array_filter($events, fn($event) => $event->eventname === '\core\event\course_module_created');
        $eventsingle = $createdevents[0];
        mod_adele_observer::saved_module($eventsingle);

        $eventsnew = $this->sink->get_events();
        $updateevents = array_values(array_filter($eventsnew, fn($event) =>
            $event->eventname === '\local_adele\event\user_path_updated'));

        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);
        $contextcourseb = \context_course::instance($this->courseids[3]);
        $enrolledusers = get_enrolled_users($contextcourseb);

        // Assert that the course has 2 enrolled users.
        $this->assertCount(2, $enrolledusers, "Expected course with ID {$this->courseids[3]} to have 2 enrolled users.");

        $adhoctasks = $DB->get_records('task_adhoc');
        // Clean up the event sink.
        $this->sink->close();
    }

    /**
     * Test that satisfying node A's completion criteria grants users access to node B.
     *
     * Full scenario:
     * - Users are subscribed to the learning path and enrolled in node A's starting courses.
     * - Both courses in node A are marked as complete for every user, satisfying the
     *   course_completed criterion (min_courses = 2).
     * - Re-evaluating the path fires a node_finished event for each user.
     * - Processing those events via node_completion::enrol_child_courses enrolls
     *   users in node B's course (courseids[2], distinct from node A's courses).
     *
     * setUp assigns courseids[0,3] to dndnode_1 (node A) and courseids[2] to dndnode_2 (node B),
     * so the enrollment assertion is unambiguous.
     *
     * @return void
     */
    public function test_node_completion_grants_access_to_child_node(): void {
        global $DB;

        // Step 1: Subscribe users to the learning path.
        $events = $this->sink->get_events();
        $createdevents = array_values(array_filter(
            $events,
            fn($event) => $event->eventname === '\\core\\event\\course_module_created'
        ));
        mod_adele_observer::saved_module($createdevents[0]);

        // Step 2: Enroll users in node A (starting node, dndnode_1).
        // The first two user_path_updated events (indices 0,1) were fired by saved_module.
        // Calling updated_single on them saves user_path_relation to the DB and fires two
        // more "creation" user_path_updated events (indices 2,3) that carry the persisted
        // userpaths - those are the ones we'll reuse in step 4.
        $allevents = $this->sink->get_events();
        $updateevents = array_values(array_filter(
            $allevents,
            fn($event) => $event->eventname === '\\local_adele\\event\\user_path_updated'
        ));
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 3: Mark node A's courses (courseids[0] and courseids[3]) complete.
        // Both courses must be complete to satisfy the course_completed criterion (min_courses=2).
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            foreach ([$this->courseids[0], $this->courseids[3]] as $courseid) {
                $this->mark_course_complete_in_db((int)$courseid, (int)$record->user_id);
            }
        }

        // Step 4: Re-evaluate paths using the persisted "creation" events from step 2.
        // Those events (at indices 2 and 3) hold userpaths that already have user_path_relation
        // set (creation = false), so updated_single will detect completion and fire node_finished.
        $alleventsnew = $this->sink->get_events();
        $allupdateevents = array_values(array_filter(
            $alleventsnew,
            fn($event) => $event->eventname === '\\local_adele\\event\\user_path_updated'
        ));
        // Indices 0,1 = original enrollment events; 2,3 = creation events from step 2.
        relation_update::updated_single($allupdateevents[2]);
        relation_update::updated_single($allupdateevents[3]);

        // Step 5: Verify node_finished was fired once per user.
        $latestevents = $this->sink->get_events();
        $nodefinishedevents = array_values(array_filter(
            $latestevents,
            fn($event) => $event->eventname === '\\local_adele\\event\\node_finished'
        ));
        $this->assertCount(
            2,
            $nodefinishedevents,
            'Expected a node_finished event for each user when node A completion criteria are met.'
        );

        // Step 6: Enroll users in node B by processing node_finished events.
        foreach ($nodefinishedevents as $nfevent) {
            node_completion::enrol_child_courses($nfevent);
        }

        // Step 7: Assert both users are now enrolled in node B's course (courseids[2]).
        $contextb = \context_course::instance($this->courseids[2]);
        $enrolledusers = get_enrolled_users($contextb);
        $this->assertCount(
            2,
            $enrolledusers,
            "Expected both users to be enrolled in node B's course (courseids[2]) after node A completion."
        );

        $this->sink->close();
    }

    /**
     * Insert a course_completions record to simulate a user completing a course.
     * The course must already have enablecompletion = 1 (set in setUp via the generator).
     *
     * @param int $courseid
     * @param int $userid
     */
    private function mark_course_complete_in_db(int $courseid, int $userid): void {
        global $DB;
        if (!$DB->record_exists('course_completions', ['course' => $courseid, 'userid' => $userid])) {
            $DB->insert_record('course_completions', (object)[
                'course'        => $courseid,
                'userid'        => $userid,
                'timeenrolled'  => time(),
                'timestarted'   => time(),
                'timecompleted' => time(),
                'reaggregate'   => 0,
            ]);
        } else {
            $DB->set_field(
                'course_completions',
                'timecompleted',
                time(),
                ['course' => $courseid, 'userid' => $userid]
            );
        }
        // Purge the MUC coursecompletion cache so completion_completion::fetch()
        // reads the freshly inserted record instead of a stale false value.
        $cache = \cache::make('core', 'coursecompletion');
        $cache->delete($userid . '_' . $courseid);
    }
}
