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
 * Regression test for GitHub issue #444:
 *   "[BUG] Nutzer ohne erkennbare Logik automatisch in andere Kurse eingeschrieben".
 *
 * A brand-new user is manually enrolled into ONE course ("Testkurs 12"). The
 * adele enrolment observer chain then silently enrolled that user into other,
 * unrelated courses.
 *
 * Mechanism: enrollment::buildsqlquerypath() used to scan the learning-path JSON
 * and match ANY course referenced inside it. So enrolling into a course that is
 * merely a *node course* of a learning path subscribed the user to that path,
 * and relation_update::subscribe_user_starting_node() then enrolled them into
 * every starting-node course of the path.
 *
 * The fix (aligned with origin/dev_chris's fix for the related #433) only
 * subscribes a user to a learning path when they enrol into the course that
 * HOSTS the learning path as a mod_adele activity (the "home course"). Enrolling
 * into an individual node course must not subscribe or cascade.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * Reproduces the spurious cross-course enrolment from issue #444.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\enrollment::buildsqlquerypath
 */
final class issue444_spurious_enrolment_test extends advanced_testcase {
    /**
     * Build a learning path with two genuine tree course nodes: a starting node
     * (the cascade target) and a second node = the trigger course. Both are real
     * node courses, so the OLD JSON-scanning code matched the trigger course and
     * cascaded; the fixed code only matches the LP's home course.
     *
     * @param int $startingcourse Course id of the starting node (cascade target).
     * @param int $triggercourse  Course id of a second node (what the user joins).
     * @return string Encoded learning-path JSON for the local_adele generator.
     */
    private function build_lp_json(int $startingcourse, int $triggercourse): string {
        $tree = [
            'nodes' => [
                [
                    'id' => 'dndnode_1',
                    'type' => 'circle',
                    'parentCourse' => ['starting_node'],
                    'childCourse' => ['dndnode_2'],
                    'data' => [
                        'course_node_id' => [(string) $startingcourse],
                        'fullname' => 'Starting node',
                        'label' => 'Start',
                    ],
                ],
                [
                    'id' => 'dndnode_2',
                    'type' => 'circle',
                    'parentCourse' => ['dndnode_1'],
                    'childCourse' => [],
                    'data' => [
                        'course_node_id' => [(string) $triggercourse],
                        'fullname' => 'Trigger node',
                        'label' => 'Trigger',
                    ],
                ],
            ],
        ];

        return json_encode([
            'name' => 'Issue 444 LP',
            'tree' => $tree,
        ]);
    }

    /**
     * Insert a mod_adele activity row linking a learning path to its home course,
     * without going through the module generator (which would fire the mod_adele
     * enrolment observers and the unrelated mod/adele lib.php implode() bug).
     *
     * @param int $courseid       Home course that hosts the activity.
     * @param int $learningpathid Learning path the activity points at.
     * @return void
     */
    private function attach_adele_activity(int $courseid, int $learningpathid): void {
        global $DB;
        $DB->insert_record('adele', (object) [
            'course' => $courseid,
            'name' => 'Adele activity',
            'learningpathid' => $learningpathid,
        ]);
    }

    /**
     * Enrolling a fresh user into a course that is only a *node course* of a
     * learning path (not the course hosting the adele activity) must not
     * subscribe them to that path nor cascade-enrol them into its other courses.
     *
     * @return void
     */
    public function test_enrolling_into_node_course_does_not_cascade(): void {
        $this->resetAfterTest(true);
        $generator = self::getDataGenerator();

        // The course that HOSTS the learning path as a mod_adele activity.
        $homecourse = $generator->create_course(['shortname' => 'HOME']);
        // The LP starting node = the unrelated course the user must NOT land in.
        $startingcourse = $generator->create_course(['shortname' => 'UNRELATED']);
        // The course the user is actually enrolled into ("Testkurs 12"); it is a
        // genuine node course of the LP but does NOT host the activity.
        $triggercourse = $generator->create_course(['shortname' => 'TRIGGER']);

        $lpjson = $this->build_lp_json((int) $startingcourse->id, (int) $triggercourse->id);
        $lpid = $generator->get_plugin_generator('local_adele')->create_adele_learningpaths([
            'name' => 'Issue 444 LP',
            'json' => $lpjson,
        ]);
        // The learning path is embedded as an activity in the home course only.
        $this->attach_adele_activity((int) $homecourse->id, (int) $lpid);

        // END-TO-END reproduction of issue #444, through the real
        // \core\event\user_enrolment_created observer chain. This is the assertion
        // that fails on the buggy code: enrolling into a node course cascades into
        // the LP's starting-node courses.
        $user = $generator->create_user();
        $startingcontext = \context_course::instance($startingcourse->id);
        $this->assertFalse(
            is_enrolled($startingcontext, $user->id),
            'Precondition: fresh user must not be enrolled in the unrelated starting course.'
        );

        // Manually enrol the user into ONLY the trigger node course.
        $generator->enrol_user($user->id, $triggercourse->id, null, 'manual');

        $this->assertTrue(
            is_enrolled(\context_course::instance($triggercourse->id), $user->id),
            'User should be enrolled in the trigger course they were added to.'
        );
        $this->assertFalse(
            is_enrolled($startingcontext, $user->id),
            'BUG #444: user was silently auto-enrolled into an unrelated course.'
        );

        // ROOT-CAUSE ASSERTIONS on buildsqlquerypath(), which decides which paths
        // an enrolment subscribes the user to.
        // Enrolling into the home course DOES subscribe (correct behaviour).
        $this->assertNotEmpty(
            enrollment::buildsqlquerypath((int) $homecourse->id),
            'buildsqlquerypath() must match the learning path for its home (activity) course.'
        );
        // Enrolling into a mere node course must NOT match (issue #444).
        $this->assertEmpty(
            enrollment::buildsqlquerypath((int) $triggercourse->id),
            'buildsqlquerypath() must NOT match a learning path just because the course '
            . 'is a node inside it (issue #444).'
        );
    }
}
