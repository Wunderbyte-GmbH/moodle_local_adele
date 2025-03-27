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
/**
 * PHPUnit test case for the 'modquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Christian Badusch <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquiz_test extends advanced_testcase {

    /**
     * Mock database object used for simulating database interactions.
     *
     * @var \moodle_database
     */
    private $dbmock;

    /**
     * Instance of the catquiz class under test.
     *
     * @var catquiz
     */
    private $catquiz;

    /**
     * Node data loaded from fixtures for use in tests.
     *
     * @var array
     */
    private $node;

    /**
     * Mock quiz attempts data loaded from JSON fixture.
     *
     * @var array|object
     */
    private $mockquizattempts;

    /**
     * Best attempt IDs data representing quiz attempts.
     *
     * @var array
     */
    private $bestids;

    /**
     * Course module data loaded from JSON fixture.
     *
     * @var object
     */
    private $coursemoduledata;

    /**
     * Last response data loaded from JSON fixture.
     *
     * @var object
     */
    private $lastresponse;

    /**
     * Mock quiz details including name and course ID.
     *
     * @var object
     */
    private $mockquiz;

    /**
     * Mock progress data for quiz attempts loaded from JSON fixture.
     *
     * @var object
     */
    private $mockattemptsprogress;


    /**
     * Set up the test environment before each test method.
     *
     * This method:
     * - Resets the test environment
     * - Initializes a catquiz instance
     * - Creates a mock database object
     * - Loads test fixture data from JSON files including:
     *   - Quiz progress data
     *   - Node configuration
     *   - Quiz attempts
     *   - Course module data
     *   - Last response data
     * - Sets up mock quiz details
     * - Configures database mock expectations for various method calls
     *
     * @return void
     */
    protected function setUp(): void {

        $this->resetAfterTest(true);

        // Initialize catquiz object.
        $this->catquiz = new catquiz();

        // Create a mock for the $DB object.
        $this->dbmock = $this->createMock(\moodle_database::class);

        // Load data from fixtures.
        $this->mockattemptsprogress =
        (object)json_decode(file_get_contents(__DIR__ . '/../../fixtures/' . 'catquiz_progress.json'), true);
        $this->node =
        json_decode(file_get_contents(__DIR__ . '/../../fixtures/' . 'catquiz_node.json'), true);
        $this->mockquizattempts =
        json_decode(file_get_contents(__DIR__ . '/../../fixtures/' . 'catquiz_attempt.json'), false);
        $this->coursemoduledata =
        (object)json_decode(file_get_contents(__DIR__ . '/../../fixtures/' . 'catquiz_coursemodule.json'), true);
        $this->lastresponse =
        (object)json_decode(file_get_contents(__DIR__ . '/../../fixtures/' . 'catquiz_lastresponse.json'), true);

        // Mock quiz details.
        $this->bestids = [1 => (object)[
            'attemptid' => "1",
            'instanceid' => "1",
            'endtime' => "0",
            'timemodified' => "1733215991",
        ]];

        $this->mockquiz = (object)[
            'name' => "CAT-Quiz",
            'course' => "9",
        ];

        // Set DB mock expectations.
        $this->dbmock->expects($this->any())
            ->method('get_records_menu')
            ->willReturn([
                'quizsettings' => 'all_quiz_global',
            ]);

        $this->dbmock->expects($this->any())
            ->method('get_record_sql')
            ->willReturnOnConsecutiveCalls($this->coursemoduledata, $this->lastresponse);

        $this->dbmock->expects($this->any())
            ->method('record_exists_sql')
            ->willReturn(false);

        $this->dbmock->expects($this->any())
            ->method('get_record')
            ->willReturnOnConsecutiveCalls($this->mockquiz, $this->mockattemptsprogress, (object)['uniqueid' => 7],
            (object)['uniqueid' => 7], (object)['uniqueid' => 7]);

    }

    /**
     * Tests that get_completion_status returns true when valid quiz attempts exist.
     *
     * This test verifies that when there are valid quiz attempts present in the mock data,
     * the get_completion_status method correctly returns an array with 'inbetween' and
     * 'completed' keys, where condition_2 is set to true. The test uses mock quiz attempts
     * and database interactions to simulate a real scenario.
     *
     * @covers \local_adele\course_completion\conditions\catquiz::get_completion_status
     * @return void
     */
    public function test_get_completion_status_is_true() {
        global $DB;

        $this->dbmock->expects($this->exactly(2))
            ->method('get_records_sql')
            ->willReturnOnConsecutiveCalls($this->mockquizattempts, $this->bestids);
        $DB = $this->dbmock;
        $status = $this->catquiz->get_completion_status($this->node, 2);

        // Perform assertions.
        $this->assertIsArray($status);
        $this->assertArrayHasKey('inbetween', $status);
        $this->assertArrayHasKey('completed', $status);

        $this->assertEquals(true, $status['inbetween']['condition_2']);

        $this->assertArrayHasKey('condition_2', $status['completed']);
        $this->assertNotFalse($status['completed']['condition_2']);
    }

    /**
     * Tests that get_completion_status returns false when no valid quiz attempts exist.
     *
     * This test verifies that when there are no valid quiz attempts present in the mock data,
     * the get_completion_status method correctly returns an array with 'inbetween' and
     * 'completed' keys, where condition_2 is set to false. The test uses empty mock quiz attempts
     * and database interactions to simulate a scenario with no attempts.
     *
     * @covers \local_adele\course_completion\conditions\catquiz::get_completion_status
     * @return void
     */
    public function test_get_completion_status_is_false() {
        global $DB;

        $this->mockquizattempts = [];
        $this->dbmock->expects($this->any())
            ->method('get_records_sql')
            ->willReturnOnConsecutiveCalls($this->mockquizattempts, $this->bestids);

        $DB = $this->dbmock;
        $status = $this->catquiz->get_completion_status($this->node, 2);

        // Perform assertions.
        $this->assertIsArray($status);
        $this->assertArrayHasKey('inbetween', $status);
        $this->assertArrayHasKey('completed', $status);
        $this->assertEquals(false, $status['inbetween']['condition_2']);

        $this->assertArrayHasKey('condition_2', $status['completed']);
        $this->assertFalse($status['completed']['condition_2']);
    }
}

