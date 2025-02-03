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
use stdClass;

/**
 * PHPUnit test case for the 'catquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_learningpath extends advanced_testcase {

    public function test_course_and_activity_setup() {
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

        // Verify that 5 courses were created.
        $this->assertCount(5, $courseids);

        // Choose the first course as the "starting course".
        $startingcourseid = $courseids[0];
        $data['filename'] = 'alise_zugangs_lp_einfach.json';
        $generator->get_plugin_generator('local_adele')->create_adele_learningpaths($data);

        // Create an instance of mod_adele in the starting course.
        $adelestart = $generator->get_plugin_generator('mod_adele')->create_instance([
            'course' => $startingcourseid,
            'name' => 'Adele Activity',
        ]);

        // Create an instance of mod_adele in the starting course.
        $adele = $generator->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $courseids[1],
            'name' => 'quiz Activity',
        ]);

        // Verify the activity exists.
        $this->assertNotEmpty($adele);
        $this->assertEquals($startingcourseid, $adelestart->course);

        // Output course IDs for further use.
        //var_dump($courseids);
    }
}
