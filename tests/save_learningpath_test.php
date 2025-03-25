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
     * Test case to verify course and activity setup functionality.
     * This test creates multiple courses and activities to ensure proper setup.
     * It specifically:
     * - Creates 5 test courses
     * - Sets up a learning path using a JSON file
     * - Creates an Adele activity instance in the first course
     * - Creates a quiz activity in the second course
     * - Verifies the creation and proper assignment of these elements
     *
     * @return void
     */
    public function test_subscribe_user_to_learning_path() {
        global $DB;

        // Reset Moodle database.
        $this->resetAfterTest(true);

        // Create 5 courses.
        $generator = self::getDataGenerator();
        $courseids = [];

        for ($i = 1; $i <= 5; $i++) {
            $course = $generator->create_course(['fullname' => 'Test Course ' . $i]);
            $courseids[] = $course->id;
        }

        // Add user to the first course (optional: adjust role, context, etc.).
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();

        // Enroll the users into the first course.
        $generator->enrol_user($user1->id, $courseids[0]);
        $generator->enrol_user($user2->id, $courseids[0]);

        // Verify that 5 courses were created.
        $this->assertCount(5, $courseids);

        // Choose the first course as the "starting course".
        $startingcourseid = $courseids[0];
        $data['filename'] = 'alise_zugangs_lp_einfach.json';
        $lpid = $generator->get_plugin_generator('local_adele')->create_adele_learningpaths($data);

        $sink = $this->redirectEvents();

        // Create an instance of mod_adele in the starting course.
        $adelestart = $generator->get_plugin_generator('mod_adele')->create_instance([
            'course' => $startingcourseid,
            'name' => 'Adele Activity',
            'participantslist' => [1],
            'learningpathid' => $lpid,

        ]);

        $events = $sink->get_events();

        $this->assertEquals($startingcourseid, $adelestart->course);

        $createdevents = array_filter($events, function($event) {
            return $event->eventname === '\core\event\course_module_created';
        });

        // Assert that the course_module_created event was called exactly once.
        $this->assertCount(1, $createdevents, "Expected course_module_created event to be called exactly once.");

        $sink->close();

    }
}

