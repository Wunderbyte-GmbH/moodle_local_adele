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

namespace local_adele\course_completion\conditions;

use advanced_testcase;
use stdClass;

/**
 * PHPUnit test case for the 'modquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
class course_completed_test extends advanced_testcase {


    /**
     * Set up function to reset all database changes after each test.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test the get_completion_priority function.
     * @covers \local_adele\course_completion\conditions\modquiz::get_completion_priority
     */
    public function test_get_completion_priority() {
        $coursecompleted = new course_completed();
        $priority = $coursecompleted->get_completion_priority();
        $this->assertEquals($coursecompleted->priority, $priority);
    }

    /**
     * Test the get_description function.
     * @covers \local_adele\course_completion\conditions\modquiz::get_description
     */
    public function test_get_description() {
        $coursecompleted = new course_completed();
        $description = $coursecompleted->get_description();

        $this->assertIsArray($description);
        $this->assertArrayHasKey('id', $description);
        $this->assertArrayHasKey('name', $description);
        $this->assertArrayHasKey('description', $description);
        $this->assertEquals($coursecompleted->id, $description['id']);
        $this->assertEquals($coursecompleted->label, $description['label']);
    }

    /**
     * Test the get_node_progress function.
     * @covers \local_adele\course_completion\conditions\course_completed::get_node_progress
     */
    public function test_get_node_progress() {
        $coursecompleted = new course_completed();
        $progresses = [100, 90, 80];

        $minvalue = 2;
        $result = $coursecompleted->get_node_progress($progresses, $minvalue);
        $this->assertEquals(95, $result);

        $minvalue = 1;
        $result = $coursecompleted->get_node_progress($progresses, $minvalue);
        $this->assertEquals(100, $result);

        $minvalue = 3;
        $result = $coursecompleted->get_node_progress($progresses, $minvalue);
        $this->assertEquals(90, $result);
    }

    /**
     * Test the get_completion_status function with mocked progress.
     * @covers \local_adele\course_completion\conditions\course_completed::get_completion_status
     */
    public function test_get_completion_status() {
        // Reset the database after the test.
        $this->resetAfterTest(true);
         // Insert mock courses into the database.
        $course1 = $this->create_course('Course 1', true);
        $course2 = $this->create_course('Course 2', true);
        $course3 = $this->create_course('Course 3', true);

        // Create mock node data using the inserted course IDs.
        $node = [
            'data' => [
                'course_node_id' => [$course1->id, $course2->id, $course3->id],
            ],
            'completion' => [
                'nodes' => [
                    [
                        'id' => 1,
                        'data' => [
                            'label' => 'course_completed',
                            'value' => ['min_courses' => 2],
                        ],
                    ],
                ],
            ],
        ];

        // Mock course completion info.
        $this->set_course_completion($course1->id, 1, true);
        $this->set_course_completion($course2->id, 1, false);
        $this->set_course_completion($course3->id, 1, true);

        // Create an instance of the course_completed condition class.
        $coursecompleted = new course_completed();

        // Call get_completion_status to test the logic.
        $status = $coursecompleted->get_completion_status($node, 1);

        // Assert the completion status.
        $this->assertTrue($status['completed'][$course1->id]);
        $this->assertFalse($status['completed'][$course2->id]);
        $this->assertTrue($status['completed'][$course3->id]);

        // Assert that the placeholders are correct.
        $this->assertArrayHasKey('numb_courses', $status[1]['placeholders']);
        $this->assertEquals(2, $status[1]['placeholders']['numb_courses']);

    }

    /**
     * Helper function to create a course in the database.
     *
     * @param string $name The full name of the course.
     * @param bool $enablecompletion Whether completion tracking is enabled.
     * @return stdClass The course record.
     */
    private function create_course($name, $enablecompletion) {
        global $DB;

        // Create the course record.
        $course = new stdClass();
        $course->fullname = $name;
        $course->shortname = strtolower(str_replace(' ', '_', $name));
        $course->category = 1;
        $course->enablecompletion = $enablecompletion ? 1 : 0;
        $course->startdate = time();
        $course->timecreated = time();
        $course->timemodified = time();

        // Insert the course into the database.
        $course->id = $DB->insert_record('course', $course);

        return $course;
    }

    /**
     * Helper function to set course completion for a user.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @param bool $completed Whether the course is completed.
     */
    private function set_course_completion($courseid, $userid, $completed) {
        global $DB;

        // Create or update completion status.
        $completion = new stdClass();
        $completion->course = $courseid;
        $completion->userid = $userid;
        $completion->timecompleted = $completed ? time() : null;

        // Insert or update the completion record.
        if ($completed) {
            $DB->insert_record('course_completions', $completion);
        } else {
            $DB->delete_records('course_completions', ['course' => $courseid, 'userid' => $userid]);
        }
    }
}
