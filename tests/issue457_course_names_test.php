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
 * Regression test for GitHub issue #457:
 *   expanded stack cards showed "Subcourse" instead of the real course name.
 *
 * The frontend resolved a course's name from the (server-filtered)
 * availablecourses pool. For students, courses they were not enrolled in were
 * filtered out (activefilter=only_subscribed), so the name could not be resolved
 * and the card fell back to "Subcourse". The fix resolves the names server-side
 * from a plain course-id lookup (not the filtered pool) and attaches them to the
 * node data, so any user assigned to the path sees the real names.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_paths::attachcoursenames
 */
final class issue457_course_names_test extends advanced_testcase {
    /**
     * The course full name is attached to the node regardless of enrolment /
     * availablecourses filtering, so views never need to fall back to "Subcourse".
     *
     * @return void
     */
    public function test_attachcoursenames_resolves_name_regardless_of_filter(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Repro 457 Real Name']);

        // Same plain id-based lookup addnodemanualcondition() uses — NOT the
        // filtered availablecourses pool.
        $coursenames = $DB->get_records_list('course', 'id', [(int) $course->id], '', 'id, fullname');

        // A node referencing that course (course ids are stored as strings).
        $node = (object) [
            'data' => (object) [
                'course_node_id' => [(string) $course->id],
            ],
        ];

        learning_paths::attachcoursenames($node, $coursenames);

        $key = (string) $course->id;
        $this->assertTrue(
            isset($node->data->course_node_id_description->{$key}->fullname),
            'A course name must be attached to the node data (issue #457).'
        );
        $this->assertSame('Repro 457 Real Name', $node->data->course_node_id_description->{$key}->fullname);
    }

    /**
     * An existing teacher-given name is never overwritten by the resolved name.
     *
     * @return void
     */
    public function test_attachcoursenames_keeps_existing_name(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Real Name']);
        $coursenames = $DB->get_records_list('course', 'id', [(int) $course->id], '', 'id, fullname');
        $key = (string) $course->id;

        $node = (object) [
            'data' => (object) [
                'course_node_id' => [(string) $course->id],
                'course_node_id_description' => (object) [
                    $key => (object) ['fullname' => 'Teacher Override'],
                ],
            ],
        ];

        learning_paths::attachcoursenames($node, $coursenames);

        $this->assertSame('Teacher Override', $node->data->course_node_id_description->{$key}->fullname);
    }
}
