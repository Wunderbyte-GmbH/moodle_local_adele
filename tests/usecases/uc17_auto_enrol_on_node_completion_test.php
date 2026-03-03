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
 * UC17 — Auto-enrolment + first_enrolled stamp on node completion.
 *
 * Trigger chain under test:
 *   1. updated_single() evaluates dndnode_1 completion.
 *   2. All conditions pass → status_completion = 'after' → node_finished event
 *      fires with $other['node'] = [dndnode_1 data] and $other['userpath'].
 *   3. observer::node_finished() → node_completion::enrol_child_courses():
 *      a. Finds dndnode_1.childCourse = ['dndnode_2'].
 *      b. For each course in dndnode_2.data.course_node_id:
 *         — if not yet enrolled → enrol_user() via manual plugin.
 *         — if first_enrolled not yet set → stamp it + trigger
 *           user_path_updated so the window opens.
 *   4. A second updated_single() cycle then evaluates dndnode_2:
 *      — restriction: parent_courses satisfied (dndnode_1 is 'after').
 *      — status → 'inbetween' / 'accessible'.
 *
 * Fixture: learning_plan3_courses.json
 *   dndnode_1 (course_completed) → dndnode_2 (parent_courses + course_completed)
 *                                → dndnode_3 (parent_courses + course_completed)
 *
 * Test scenarios
 * ──────────────
 * UC17a  test_child_course_enrolment_after_node1_completion
 *   After completing dndnode_1's course, the user must be enrolled in
 *   dndnode_2's course (courseids[1]).
 *
 * UC17b  test_first_enrolled_stamped_after_node1_completion
 *   After the trigger chain, dndnode_2's node data inside the persisted
 *   local_adele_path_user JSON must contain a non-null/non-zero first_enrolled
 *   timestamp.
 *
 * UC17c  test_dndnode2_becomes_accessible_after_node1_completion
 *   After the full trigger chain (enrol + re-eval), dndnode_2 must be
 *   'inbetween' / 'accessible' for the completing user.
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use local_adele\adele_learningpath_testcase;
use local_adele\event\node_finished;
use local_adele\event\user_path_updated;
use local_adele_observer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../adele_learningpath_testcase.php');

/**
 * UC17 — Auto-enrolment and first_enrolled stamp after node completion.
 *
 * @covers \local_adele\node_completion::enrol_child_courses
 * @covers \local_adele\relation_update::updated_single
 */
class uc17_auto_enrol_on_node_completion_test extends adele_learningpath_testcase {

    // -------------------------------------------------------------------------
    // Fixture wiring.

    /**
     * {@inheritdoc}
     */
    protected function fixturefile(): string {
        return 'learning_plan3_courses.json';
    }

    /**
     * Assign real course IDs and ensure manual enrol instances exist.
     *
     * Node layout after patching:
     *   dndnode_1  → courseids[0]  (starting course, users already enrolled)
     *   dndnode_2  → courseids[1]  (child — NOT yet enrolled before UC17 fires)
     *   dndnode_3  → courseids[2]  (grandchild)
     *
     * min_courses = 1 on all course_completed conditions so a single enrolled
     * course is sufficient to satisfy the completion gate.
     *
     * {@inheritdoc}
     */
    protected function patch_node_ids(array &$nodes): void {
        $coursemap = [
            'dndnode_1' => $this->courseids[0],
            'dndnode_2' => $this->courseids[1],
            'dndnode_3' => $this->courseids[2],
        ];

        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            $courseid = $coursemap[$node['id']] ?? null;
            if ($courseid === null) {
                continue;
            }
            $node['data']['course_node_id'] = [$courseid];

            // Set min_courses = 1 for every course_completed condition so that
            // completing a single enrolled course is sufficient.
            foreach ($node['completion']['nodes'] as &$cn) {
                if (($cn['data']['label'] ?? '') === 'course_completed') {
                    $cn['data']['value']['min_courses'] = 1;
                }
            }
            unset($cn);
        }
        unset($node);
    }

    // -------------------------------------------------------------------------
    // Helpers.

    /**
     * Ensure a manual enrol instance exists for $courseid (creates one if
     * missing) and returns it.  node_completion::enrol_child_courses() queries
     * this table before enrolling.
     *
     * @param  int    $courseid
     * @return object The enrol record.
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
     *   1. Subscribe users → initial eval (user_path_relation created).
     *   2. Ensure manual enrol instances exist on courseids[1] and courseids[2].
     *   3. Mark courseids[0] complete for every active path user.
     *   4. Re-evaluate via user_path_updated event → updated_single() fires
     *      node_finished for dndnode_1.
     *   5. Dispatch all node_finished events → enrol_child_courses() runs,
     *      enrols users in courseids[1], stamps first_enrolled on dndnode_2.
     *
     * Returns the array of active user_path rows after the full chain.
     *
     * @return object[]
     */
    private function complete_node1_and_trigger_chain(): array {
        global $DB;

        // Step 1: subscribe users, create initial user_path_relation rows.
        $this->subscribe_users_to_lp();

        // Step 2: ensure manual enrol instances exist on the child courses.
        $this->ensure_manual_enrol_instance($this->courseids[1]);
        $this->ensure_manual_enrol_instance($this->courseids[2]);

        // Step 3: mark courseids[0] complete for every active path user.
        $records = $DB->get_records('local_adele_path_user', ['status' => 'active']);
        foreach ($records as $record) {
            $this->mark_course_complete_in_db($this->courseids[0], $record->user_id);
        }

        // Step 4: re-evaluate — fires user_path_updated which calls updated_single().
        // updated_single() detects dndnode_1 flipped to 'after' and fires node_finished.
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
     * UC17a — After completing dndnode_1, the user must be enrolled in
     * dndnode_2's course (courseids[1]).
     *
     * Asserts:
     *   — is_enrolled() returns true for every active path user in courseids[1].
     */
    public function test_child_course_enrolment_after_node1_completion(): void {
        $records = $this->complete_node1_and_trigger_chain();

        $this->assertNotEmpty($records, 'Expected active user path records.');

        foreach ($records as $record) {
            $context = \context_course::instance($this->courseids[1]);
            $this->assertTrue(
                is_enrolled($context, $record->user_id),
                "User {$record->user_id} must be enrolled in courseids[1] after dndnode_1 completion."
            );
        }
    }

    /**
     * UC17b — After the trigger chain, dndnode_2's node data in the persisted
     * JSON must carry a non-null, non-zero first_enrolled timestamp.
     *
     * Asserts:
     *   — $json['tree']['nodes'][dndnode_2]['data']['first_enrolled'] is set and > 0.
     */
    public function test_first_enrolled_stamped_after_node1_completion(): void {
        global $DB;

        $records = $this->complete_node1_and_trigger_chain();
        $this->assertNotEmpty($records, 'Expected active user path records.');

        // After enrol_child_courses() stamps first_enrolled it fires another
        // user_path_updated event (trigger_user_path_update_new_enrollments).
        // Dispatch all newly queued update events in rounds until the sink
        // produces no more new events (max 3 rounds).
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
            $this->assertNotNull($dndnode2, 'dndnode_2 must exist in tree.');
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
        }
    }

    /**
     * UC17c — After the full trigger chain (enrol + re-eval), dndnode_2 must
     * be 'inbetween' / 'accessible' for the completing user.
     *
     * The restriction on dndnode_2 is parent_courses (dndnode_1 must be
     * 'after').  Once dndnode_1 is 'after', the restriction is satisfied and
     * the node opens.
     *
     * Note on event ordering: enrol_child_courses() internally fires another
     * user_path_updated event (via trigger_user_path_update_new_enrollments()).
     * That nested event is captured by the sink but is not automatically
     * dispatched by the complete_node1_and_trigger_chain() helper because the
     * dispatch loop over the initial batch already finished.  We therefore
     * perform a final explicit re-evaluation pass here by dispatching all
     * remaining user_path_updated events from the sink.
     *
     * Asserts:
     *   — status_restriction = 'inbetween'
     *   — status             = 'accessible'
     */
    public function test_dndnode2_becomes_accessible_after_node1_completion(): void {
        global $DB;

        $records = $this->complete_node1_and_trigger_chain();
        $this->assertNotEmpty($records, 'Expected active user path records.');

        // enrol_child_courses() fires a second user_path_updated (for
        // first_enrolled) and observer re-enqueues it.  Keep dispatching until
        // no new update events are produced (max 3 rounds to avoid infinite
        // loops in unexpected situations).
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
            $rel  = $json['user_path_relation']['dndnode_2'] ?? null;
            $this->assertNotNull(
                $json['user_path_relation'] ?? null,
                "user_path_relation must exist. Keys in json: " . implode(', ', array_keys($json))
            );
            $this->assertNotNull($rel,
                "user_path_relation must have an entry for dndnode_2. "
                . "Keys present: " . implode(', ', array_keys($json['user_path_relation']))
            );
            $fb = $rel['feedback'];
            $this->assertSame(
                'inbetween',
                $fb['status_restriction'] ?? null,
                "User {$record->user_id}: dndnode_2 status_restriction must be 'inbetween'."
            );
            $this->assertSame(
                'accessible',
                $fb['status'] ?? null,
                "User {$record->user_id}: dndnode_2 status must be 'accessible'."
            );
        }
    }
}
