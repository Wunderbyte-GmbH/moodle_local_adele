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
 * Coverage-gap tests for the subscription / enrolment observer flow.
 *
 * These complement issue448_empty_lp_subscribe_test (which only asserts that an
 * EMPTY learning path does not crash) and issue444_spurious_enrolment_test (which
 * only asserts buildsqlquerypath() matching). They cover the parts that are NOT
 * yet asserted anywhere:
 *   - the SHAPE of the local_adele_path_user snapshot created by
 *     enrollment::subscribe_user_to_learning_path() (valid JSON, a 'tree' key,
 *     status 'active', createdby, course id) for both an empty AND a populated LP;
 *   - that the recompute triggered by the fired user_path_updated event runs
 *     without raising debugging;
 *   - that enrollment::enrolled() on a real user_enrolment_created event
 *     subscribes exactly one snapshot for the LP's home course (issue #444
 *     behaviour) end-to-end, for a POPULATED path.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Coverage-gap tests for the subscription / enrolment observer flow.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\enrollment::subscribe_user_to_learning_path
 * @covers \local_adele\enrollment::enrolled
 * @covers \local_adele\enrollment::buildsqlqueryuserpath
 */
final class subscription_flow_test extends advanced_testcase {
    /**
     * A minimal but populated learning-path tree: one starting node with a
     * single course and no completion/restriction criteria (so recompute has a
     * real node to walk without triggering enrolment cascades).
     *
     * @param int $courseid
     * @return string encoded learning-path json
     */
    private function populated_json(int $courseid): string {
        return json_encode([
            'name' => 'Populated LP',
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1',
                        'type' => 'circle',
                        'parentCourse' => ['starting_node'],
                        'childCourse' => [],
                        'data' => [
                            'course_node_id' => [(string) $courseid],
                            'fullname' => 'Start node',
                            'label' => 'Start',
                        ],
                    ],
                ],
                'edges' => [],
            ],
            'modules' => null,
        ]);
    }

    /**
     * Insert a learning-path row and return the freshly loaded record.
     *
     * @param string $json
     * @param int $creatorid
     * @return \stdClass
     */
    private function make_lp(string $json, int $creatorid): \stdClass {
        global $DB;
        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP',
            'json' => $json,
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creatorid,
        ]);
        return $DB->get_record('local_adele_learning_paths', ['id' => $lpid]);
    }

    /**
     * Subscribing to an EMPTY learning path must create an active snapshot whose
     * json is valid and carries a (defaulted) empty tree — the #448 guarantee,
     * asserted here at the snapshot-shape level.
     *
     * @return void
     */
    public function test_empty_lp_snapshot_shape(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $creator = $gen->create_user();
        $this->setUser($creator);

        // Empty LP: json with no 'tree' key at all.
        $lp = $this->make_lp(json_encode(['name' => 'Empty LP']), (int) $creator->id);
        $params = (object) ['relateduserid' => $user->id, 'userid' => $creator->id];

        enrollment::subscribe_user_to_learning_path($lp, $params, (int) $course->id);

        $snapshot = $DB->get_record('local_adele_path_user', [
            'learning_path_id' => $lp->id,
            'user_id' => $user->id,
        ]);
        $this->assertNotFalse($snapshot, 'A snapshot row must be created for the empty LP.');
        $this->assertSame('active', $snapshot->status);
        $this->assertSame((int) $course->id, (int) $snapshot->course_id);
        $this->assertSame((int) $creator->id, (int) $snapshot->createdby);

        $decoded = json_decode($snapshot->json, true);
        $this->assertNotNull($decoded, 'Snapshot json must be valid JSON.');
        $this->assertArrayHasKey('tree', $decoded, 'Snapshot json must carry a defaulted tree (#448).');
        $this->assertSame([], $decoded['tree']['nodes']);
    }

    /**
     * Subscribing to a POPULATED learning path must snapshot the real tree
     * (nodes preserved) with status active, and the recompute fired by the
     * user_path_updated event must run without raising debugging.
     *
     * @return void
     */
    public function test_populated_lp_snapshot_and_recompute(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $user = $gen->create_user();
        $creator = $gen->create_user();
        $gen->enrol_user($user->id, $course->id);
        $this->setUser($creator);

        $lp = $this->make_lp($this->populated_json((int) $course->id), (int) $creator->id);
        $params = (object) ['relateduserid' => $user->id, 'userid' => $creator->id];

        // The event triggered inside subscribe_user_to_learning_path() drives
        // relation_update::updated_single() (the recompute) via the observer.
        enrollment::subscribe_user_to_learning_path($lp, $params, (int) $course->id);

        $snapshot = $DB->get_record('local_adele_path_user', [
            'learning_path_id' => $lp->id,
            'user_id' => $user->id,
        ]);
        $this->assertNotFalse($snapshot);
        $this->assertSame('active', $snapshot->status);

        $decoded = json_decode($snapshot->json, true);
        $this->assertNotNull($decoded, 'Snapshot json must be valid JSON.');
        $this->assertArrayHasKey('tree', $decoded);
        $this->assertCount(1, $decoded['tree']['nodes'], 'The single populated node must be preserved.');
        $this->assertSame('dndnode_1', $decoded['tree']['nodes'][0]['id']);

        // Recompute ran on the fired event; no debugging must have been emitted.
        $this->assertDebuggingNotCalled();
    }

    /**
     * Subscribing the same user twice to the same LP/course must reuse the
     * existing active snapshot instead of creating a duplicate row
     * (buildsqlqueryuserpath dedup).
     *
     * @return void
     */
    public function test_second_subscribe_reuses_snapshot(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $creator = $gen->create_user();
        $this->setUser($creator);

        $lp = $this->make_lp($this->populated_json((int) $course->id), (int) $creator->id);
        $params = (object) ['relateduserid' => $user->id, 'userid' => $creator->id];

        enrollment::subscribe_user_to_learning_path($lp, $params, (int) $course->id);
        // Re-load: subscribe mutated $lp->json to an array in place.
        $lp = $DB->get_record('local_adele_learning_paths', ['id' => $lp->id]);
        enrollment::subscribe_user_to_learning_path($lp, $params, (int) $course->id);

        $count = $DB->count_records('local_adele_path_user', [
            'learning_path_id' => $lp->id,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertSame(1, $count, 'A repeat subscribe must not create a duplicate active snapshot.');
    }

    /**
     * End-to-end: enrolment::enrolled() on a user_enrolment_created-shaped event
     * for the LP's HOME course subscribes exactly one active snapshot for a
     * POPULATED path (issue #444: only the home course subscribes).
     *
     * @return void
     */
    public function test_enrolled_on_home_course_subscribes_populated_path(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $homecourse = $gen->create_course();
        $nodecourse = $gen->create_course();
        $user = $gen->create_user();
        $creator = $gen->create_user();
        $this->setUser($creator);

        $lp = $this->make_lp($this->populated_json((int) $nodecourse->id), (int) $creator->id);
        // Attach the LP to its home course as a mod_adele activity row.
        $DB->insert_record('adele', (object) [
            'course' => $homecourse->id,
            'name' => 'Adele activity',
            'learningpathid' => $lp->id,
        ]);

        // The user takes part in the home course: being enrolled alone no
        // longer subscribes anybody (#587) - a teacher is enrolled too.
        $gen->enrol_user($user->id, $homecourse->id, 'student');
        // Shape of the payload enrollment::enrolled() reads off the event.
        $event = (object) [
            'courseid' => (int) $homecourse->id,
            'relateduserid' => (int) $user->id,
            'userid' => (int) $creator->id,
        ];
        enrollment::enrolled($event);

        $snapshots = $DB->get_records('local_adele_path_user', [
            'learning_path_id' => $lp->id,
            'user_id' => $user->id,
        ]);
        $this->assertCount(1, $snapshots, 'Enrolling into the home course must create exactly one snapshot.');
        $snapshot = reset($snapshots);
        $this->assertSame('active', $snapshot->status);
        $this->assertSame((int) $homecourse->id, (int) $snapshot->course_id);
    }

    /**
     * Control for #444 at the enrolled() level: enrolling into a mere node course
     * (not the home course) must create NO snapshot.
     *
     * @return void
     */
    public function test_enrolled_on_node_course_creates_no_snapshot(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $homecourse = $gen->create_course();
        $nodecourse = $gen->create_course();
        $user = $gen->create_user();
        $creator = $gen->create_user();
        $this->setUser($creator);

        $lp = $this->make_lp($this->populated_json((int) $nodecourse->id), (int) $creator->id);
        $DB->insert_record('adele', (object) [
            'course' => $homecourse->id,
            'name' => 'Adele activity',
            'learningpathid' => $lp->id,
        ]);

        $event = (object) [
            'courseid' => (int) $nodecourse->id,
            'relateduserid' => (int) $user->id,
            'userid' => (int) $creator->id,
        ];
        enrollment::enrolled($event);

        $this->assertSame(
            0,
            $DB->count_records('local_adele_path_user', ['learning_path_id' => $lp->id]),
            'Enrolling into a mere node course must not subscribe the user (#444).'
        );
    }
}
