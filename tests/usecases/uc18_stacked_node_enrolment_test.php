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
 * UC-18 — Stacked (orcourses) node: enrolment in all 3 child courses on
 * parent node completion.
 *
 * Fixture topology (stackedorcourses.json):
 *   dndnode_1  (type=custom):
 *     course_node_id = [courseids[0]]
 *     completion = OR(course_completed, manual)   ← two independent OR columns
 *     restriction = none
 *     childCourse = ['dndnode_2']
 *
 *   dndnode_2  (type=orcourses):
 *     course_node_id = [courseids[1], courseids[2], courseids[3]]  ← 3 courses
 *     completion = course_completed (min_courses=1)
 *     restriction = parent_courses (dndnode_1 must be 'completed')
 *     childCourse = []
 *
 * Trigger chain under test:
 *   1. updated_single() evaluates dndnode_1 completion.
 *   2. dndnode_1 transitions to 'after'/'completed' → node_finished event fires.
 *   3. observer::node_finished() → node_completion::enrol_child_courses():
 *      a. Resolves dndnode_1.childCourse = ['dndnode_2'].
 *      b. For each course in dndnode_2.data.course_node_id (3 courses):
 *         − enrols user via manual enrol plugin.
 *         − stamps first_enrolled on dndnode_2.data if not yet set.
 *      c. Fires user_path_updated so re-evaluation includes the new enrolments.
 *   4. Second updated_single() cycle evaluates dndnode_2 restriction:
 *      − parent_courses: dndnode_1 is 'completed' → satisfied → 'accessible'.
 *
 * Two tests:
 *
 *   a) test_stacked_node_not_accessible_when_parent_not_done
 *      Before dndnode_1 is completed, dndnode_2's parent_courses restriction
 *      fails → status='not_accessible'.  No enrolments in the 3 child courses.
 *
 *   b) test_stacked_node_all_courses_enrolled_after_parent_done
 *      After completing dndnode_1's course, all 3 courses of dndnode_2 must be
 *      enrolled for each active path user, first_enrolled must be stamped on
 *      dndnode_2, and the node must become 'accessible'.
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use context_system;
use local_adele\event\user_path_updated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Stacked orcourses node: enrolment in all child courses on parent completion.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(node_completion::class)]
final class uc18_stacked_node_enrolment_test extends adele_learningpath_testcase {
    /**
     * Uses the stacked orcourses fixture (2-node chain, dndnode_2 has 3 courses).
     */
    protected function fixturefile(): string {
        return 'stackedorcourses.json';
    }

    /**
     * Assign real course IDs to both nodes.
     *
     * dndnode_1 → courseids[0]  (the starting course users are enrolled in)
     * dndnode_2 → courseids[1], courseids[2], courseids[3]  (3 child courses)
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_1') {
                $node['data']['course_node_id'] = [$this->courseids[0]];
            } else {
                // dndnode_2 carries 3 courses.
                $node['data']['course_node_id'] = [
                    $this->courseids[1],
                    $this->courseids[2],
                    $this->courseids[3],
                ];
            }
        }
        unset($node);
    }

    // -------------------------------------------------------------------------
    // Helpers.

    /**
     * Ensure a manual enrol instance exists for $courseid.
     * node_completion::enrol_child_courses() requires this record to be present
     * before it can call $enrol->enrol_user().
     *
     * @param int $courseid
     * @return object
     */
    private function ensure_manual_enrol_instance(int $courseid): object {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);
        if (!$instance) {
            $enrol = enrol_get_plugin('manual');
            $enrolid = $enrol->add_instance(get_course($courseid));
            $instance = $DB->get_record('enrol', ['id' => $enrolid]);
        }
        return $instance;
    }

    /**
     * Full trigger chain:
     *   1. Subscribe users → initial evaluation (user_path_relation rows created).
     *   2. Ensure manual enrol instances exist on the 3 child courses.
     *   3. Mark courseids[0] complete for every active path user.
     *   4. Re-evaluate via user_path_updated → updated_single() fires
     *      node_finished when dndnode_1 transitions to 'after'.
     *   5. Dispatch node_finished events → enrol_child_courses() runs, enrolls
     *      users in all 3 courses, stamps first_enrolled on dndnode_2.
     *
     * @return object[] Active local_adele_path_user records after the chain.
     */
    private function complete_parent_and_trigger_chain(): array {
        global $DB;

        // Step 1: subscribe users, create initial user_path_relation rows.
        $this->subscribe_users_to_lp();

        // Step 2: ensure manual enrol instances on all 3 child courses.
        foreach ([$this->courseids[1], $this->courseids[2], $this->courseids[3]] as $cid) {
            $this->ensure_manual_enrol_instance((int)$cid);
        }

        // Step 3: mark dndnode_1's course complete for every active path user.
        $records = $DB->get_records('local_adele_path_user', ['status' => 'active']);
        foreach ($records as $record) {
            $this->mark_course_complete_in_db((int)$this->courseids[0], (int)$record->user_id);
        }

        // Step 4: re-evaluate — updated_single() detects dndnode_1 as 'after'
        // and fires node_finished.
        $updateevents = $this->get_update_events();
        foreach ($updateevents as $ev) {
            \local_adele_observer::user_path_updated($ev);
        }

        // Step 5: dispatch node_finished events → enrol_child_courses() runs.
        $nodefinishedevents = $this->get_node_finished_events();
        foreach ($nodefinishedevents as $ev) {
            \local_adele_observer::node_finished($ev);
        }

        return $DB->get_records('local_adele_path_user', ['status' => 'active']);
    }

    // -------------------------------------------------------------------------
    // Tests.

    /**
     * BEFORE: dndnode_1 not yet completed → dndnode_2 restriction (parent_courses)
     * fails → status='not_accessible', no enrolments in the 3 child courses.
     *
     * @return void
     */
    public function test_stacked_node_not_accessible_when_parent_not_done(): void {
        global $DB;

        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: dndnode_2 must be 'not_accessible' before parent is done."
            );

            // No enrolments in any of the 3 child courses.
            foreach ([$this->courseids[1], $this->courseids[2], $this->courseids[3]] as $cid) {
                $context = \context_course::instance((int)$cid);
                $this->assertFalse(
                    is_enrolled($context, $record->user_id),
                    "User {$record->user_id} must NOT be enrolled in course {$cid} before parent completes."
                );
            }
        }

        $this->sink->close();
    }

    /**
     * AFTER: completing dndnode_1 triggers enrol_child_courses(), which must:
     *   1. Enrol each active path user in ALL THREE courses of dndnode_2
     *      (courseids[1], courseids[2], courseids[3]).
     *   2. Stamp first_enrolled on dndnode_2.data inside the persisted JSON.
     *   3. After a final re-evaluation, dndnode_2 must be 'accessible'.
     *
     * @return void
     */
    public function test_stacked_node_all_courses_enrolled_after_parent_done(): void {
        global $DB;

        $records = $this->complete_parent_and_trigger_chain();
        $this->assertNotEmpty($records, 'Expected active user path records.');

        // ---- Enrolment check ------------------------------------------------
        foreach ($records as $record) {
            foreach ([$this->courseids[1], $this->courseids[2], $this->courseids[3]] as $cid) {
                $context = \context_course::instance((int)$cid);
                $this->assertTrue(
                    is_enrolled($context, $record->user_id),
                    "User {$record->user_id} must be enrolled in course {$cid} after parent completes."
                );
            }
        }

        // ---- first_enrolled stamp check -------------------------------------
        // enrol_child_courses() fires a second user_path_updated event after
        // stamping first_enrolled.  Drain new events in rounds (max 3) to
        // ensure the persisted JSON is updated before we read it.
        $dispatched = 0;
        for ($round = 0; $round < 3; $round++) {
            $updateevents = $this->get_update_events();
            if (count($updateevents) <= $dispatched) {
                break;
            }
            $newevents = array_slice($updateevents, $dispatched);
            $dispatched += count($newevents);
            foreach ($newevents as $ev) {
                \local_adele_observer::user_path_updated($ev);
            }
        }

        $records = $DB->get_records('local_adele_path_user', ['status' => 'active']);
        foreach ($records as $record) {
            $json = is_string($record->json) ? json_decode($record->json, true) : $record->json;

            $dndnode2 = null;
            foreach ($json['tree']['nodes'] as $n) {
                if ($n['id'] === 'dndnode_2') {
                    $dndnode2 = $n;
                    break;
                }
            }
            $this->assertNotNull($dndnode2, 'dndnode_2 must exist in tree nodes.');
            $this->assertArrayHasKey(
                'first_enrolled',
                $dndnode2['data'],
                "User {$record->user_id}: dndnode_2 data must contain first_enrolled key."
            );
            $this->assertGreaterThan(
                0,
                $dndnode2['data']['first_enrolled'],
                "User {$record->user_id}: first_enrolled must be a positive timestamp."
            );

            // ---- Accessibility check ----------------------------------------
            $fb = $json['user_path_relation']['dndnode_2']['feedback'];
            $this->assertEquals(
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: dndnode_2 restriction must be 'inbetween' after parent completes."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: dndnode_2 must be 'accessible' after parent completes."
            );
        }

        $this->sink->close();
    }
}
