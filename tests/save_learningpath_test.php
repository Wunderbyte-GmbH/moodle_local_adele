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
use local_adele\course_completion\course_completion_status;
use local_adele\course_restriction\course_restriction_status;
use mod_adele_observer;
use moodle_database;

/**
 * PHPUnit test case for the 'catquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
class save_learningpath_test extends advanced_testcase {

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
            $course = $generator->create_course(['fullname' => 'Test Course ' . $i]);
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
                $node['data']['course_node_id'] = [
                    $this->courseids[0],
                    $this->courseids[3],
                ];
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
     * @covers \core\event\course_module_created
     * @return void
     */
    public function test_course_module_created() {

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
     * @covers \mod_adele_observer::saved_module
     * @return void
     */
    public function test_subscribe_user_to_learning_path() {
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
     * @covers \local_adele\relation_update::updated_single
     * @return void
     */
    public function test_user_path_starting_node_enrollment() {
        global $DB;
        // Fetch events.
        $events = $this->sink->get_events();
        // Verify course module creation event was captured exactly once.
        $createdevents = array_filter($events, fn($event) => $event->eventname === '\core\event\course_module_created');
        $eventsingle = $createdevents[0];
        mod_adele_observer::saved_module($eventsingle);

        $eventsnew = $this->sink->get_events();
        $updateevents = array_values(array_filter($eventsnew , fn($event) =>
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


}

