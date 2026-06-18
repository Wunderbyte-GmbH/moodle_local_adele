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
 * UC-20 — Enrollment: path-user record creation logic.
 *
 * Verifies that a local_adele_path_user record is created only when a user
 * enrols in the course that hosts the mod_adele activity (the "home course"),
 * and NOT when they enrol in individual node courses that belong to the LP.
 *
 * Four scenarios:
 *   a) Enrolling in a node course does NOT create a path_user record.
 *   b) Enrolling in the home course creates exactly one path_user record.
 *   c) Firing the enrollment event twice (same user + home course) does not
 *      create duplicate records (idempotency guard).
 *   d) Two different users enrolling in the home course each get their own
 *      path_user record.
 *
 * Fixture: alise_zugangs_lp_einfach.json
 *   Home course   → courseids[0]  (hosts the mod_adele activity, NOT a node)
 *   Node course A → courseids[1]  (dndnode_1)
 *   Node course B → courseids[2]  (dndnode_2)
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use stdClass;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Enrollment: path-user record creation on enrolment events.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(enrollment::class)]
final class uc20_enrollment_test extends adele_learningpath_testcase {

    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Place node courses on courseids[1] and courseids[2], keeping the home
     * course (courseids[0]) completely separate from any LP node.
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (isset($node['data']['course_node_id'])) {
                if ($node['id'] === 'dndnode_2') {
                    $node['data']['course_node_id'] = [$this->courseids[2]];
                } else {
                    $node['data']['course_node_id'] = [$this->courseids[1]];
                }
            }
        }
    }

    /**
     * Build a minimal stdClass that enrollment::enrolled() can consume.
     * We call the method directly so no real Moodle enrolment event is needed.
     *
     * @param int $courseid  The course the user is being enrolled in.
     * @param int $userid    The user being enrolled.
     * @return stdClass
     */
    private function make_enrolment_event(int $courseid, int $userid): stdClass {
        $event = new stdClass();
        $event->courseid       = $courseid;
        $event->relateduserid  = $userid;
        $event->userid         = get_admin()->id;
        return $event;
    }

    /**
     * Enrolling a user in a node course must NOT create a local_adele_path_user
     * record.
     *
     * Before the fix, enrollment::buildsqlquerypath() searched LP JSON blobs for
     * the enrolled course ID. Because both dndnode_1 and dndnode_2 reference node
     * courses, a user being auto-enrolled into those courses by the LP would
     * trigger spurious path_user creation. After the fix the query only matches
     * courses that host a mod_adele activity, so node courses are ignored.
     */
    public function test_enrollment_in_node_course_does_not_create_user_path(): void {
        global $DB;

        $user = self::getDataGenerator()->create_user();

        // Simulate enrollment in dndnode_1's node course (courseids[1]).
        enrollment::enrolled($this->make_enrolment_event($this->courseids[1], $user->id));

        $this->assertCount(
            0,
            $DB->get_records('local_adele_path_user'),
            'Enrolling in a node course (dndnode_1) must not create a local_adele_path_user record.'
        );

        // Also simulate enrollment in dndnode_2's node course (courseids[2]).
        enrollment::enrolled($this->make_enrolment_event($this->courseids[2], $user->id));

        $this->assertCount(
            0,
            $DB->get_records('local_adele_path_user'),
            'Enrolling in a node course (dndnode_2) must still not create any record.'
        );

        $this->sink->close();
    }

    /**
     * Enrolling a user in the home course (the one hosting the mod_adele activity)
     * must create exactly one local_adele_path_user record whose course_id points
     * to the home course, not to any node course.
     */
    public function test_enrollment_in_home_course_creates_single_user_path(): void {
        global $DB;

        $user = self::getDataGenerator()->create_user();

        enrollment::enrolled($this->make_enrolment_event($this->startingcourseid, $user->id));

        $records = $DB->get_records('local_adele_path_user');

        $this->assertCount(
            1,
            $records,
            'Enrolling in the home course must create exactly one local_adele_path_user record.'
        );

        $record = reset($records);

        $this->assertEquals(
            $this->startingcourseid,
            (int) $record->course_id,
            'The path_user record must reference the home course, not a node course.'
        );
        $this->assertEquals(
            $user->id,
            (int) $record->user_id,
            'The path_user record must belong to the enrolled user.'
        );
        $this->assertEquals(
            $this->adelestart->learningpathid,
            (int) $record->learning_path_id,
            'The path_user record must reference the correct learning path.'
        );

        $this->sink->close();
    }

    /**
     * Firing the enrollment event twice for the same user+home-course must not
     * create duplicate local_adele_path_user records.
     *
     * The buildsqlqueryuserpath() guard inside subscribe_user_to_learning_path()
     * is responsible for this idempotency.
     */
    public function test_duplicate_enrollment_event_does_not_duplicate_user_path(): void {
        global $DB;

        $user  = self::getDataGenerator()->create_user();
        $event = $this->make_enrolment_event($this->startingcourseid, $user->id);

        enrollment::enrolled($event);
        enrollment::enrolled($event);

        $this->assertCount(
            1,
            $DB->get_records('local_adele_path_user'),
            'Firing the enrollment event twice must not create duplicate records.'
        );

        $this->sink->close();
    }

    /**
     * When two different users each enrol in the home course, each gets their
     * own path_user record — both with course_id pointing to the home course.
     */
    public function test_two_users_enrolling_in_home_course_create_two_user_paths(): void {
        global $DB;

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        enrollment::enrolled($this->make_enrolment_event($this->startingcourseid, $user1->id));
        enrollment::enrolled($this->make_enrolment_event($this->startingcourseid, $user2->id));

        $records = $DB->get_records('local_adele_path_user');

        $this->assertCount(
            2,
            $records,
            'Two distinct users enrolling in the home course must produce exactly two records.'
        );

        foreach ($records as $record) {
            $this->assertEquals(
                $this->startingcourseid,
                (int) $record->course_id,
                'Every record must reference the home course.'
            );
        }

        $userids = array_map(fn($r) => (int) $r->user_id, $records);
        $this->assertContains((int) $user1->id, $userids);
        $this->assertContains((int) $user2->id, $userids);

        $this->sink->close();
    }
}
