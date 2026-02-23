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

use core\event\course_module_created;
use local_adele\course_completion\course_completion_status;
use local_adele\course_restriction\course_restriction_status;
use local_adele\event\user_path_updated;
use local_adele\node_completion;
use mod_adele_observer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
// Coverage is declared via PHP 8 attributes on the class instead of @covers docblock annotations.
/**
 * PHPUnit test case for the 'catquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       Christian Badusch
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 *
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_module_created::class)]
#[CoversClass(relation_update::class)]
#[CoversClass(enrollment::class)]
#[CoversMethod(mod_adele_observer::class, 'saved_module')]
#[CoversMethod(relation_update::class, 'validatenodecompletion')]
#[CoversMethod(node_completion::class, 'enrol_child_courses')]
final class save_learningpath_test extends adele_learningpath_testcase {
    /**
     * Uses the main access-path fixture: two-node LP with course_completed +
     * parent_courses/manual restriction conditions.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Assign real course IDs to the fixture tree nodes.
     *
     * dndnode_1 (starting node A): courseids[0] + courseids[3] — two courses so
     *   that the min_courses=2 completion criterion can be tested.
     * dndnode_2 (child node B):    courseids[2] alone — unambiguous for enrollment
     *   assertions (distinct from node A's courses).
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (isset($node['data']['course_node_id'])) {
                if ($node['id'] === 'dndnode_2') {
                    $node['data']['course_node_id'] = [$this->courseids[2]];
                } else {
                    $node['data']['course_node_id'] = [
                        $this->courseids[0],
                        $this->courseids[3],
                    ];
                }
            }
        }
    }

    /**
     * Test case to verify course and activity setup functionality.
     * This test creates multiple courses and activities to ensure proper setup.
     * It specifically:
     * - Creates 5 test courses
     * - Sets up a learning path using a JSON file
     * - Creates an Adele activity instance in the first course
     * - Creates a quiz activity in the second course
     * - Verifies the creation and proper assignment of these elements
     * @return void
     */
    public function test_course_module_created(): void {

        // Fetch events.
        $events = $this->sink->get_events();

        // Verify that 5 courses were created.
        $this->assertCount(5, $this->courseids);

        // Verify course module creation event was captured exactly once.
        $createdevents = array_filter($events, fn($event) => $event->eventname === '\core\event\course_module_created');
        $this->assertCount(1, $createdevents, "Expected course_module_created event to be called exactly once.");

        // Clean up the event sink.
        $this->sink->close();
    }

    /**
     * Test case to verify the subscription of users to a learning path.
     * This test ensures that:
     * - Users can be successfully subscribed to a learning path
     * - The appropriate events are triggered during subscription
     * - The subscription data is correctly stored in the database
     * - The event observer handles the module creation correctly
     * - The expected number of path user entries are created
     *
     * @return void
     */
    public function test_subscribe_user_to_learning_path(): void {
        global $DB;
        // Fetch events.
        $events = $this->sink->get_events();
        // Verify course module creation event was captured exactly once.
        $createdevents = array_filter($events, fn($event) => $event->eventname === '\core\event\course_module_created');
        $eventsingle = $createdevents[0];
        mod_adele_observer::saved_module($eventsingle);

        $getlps = $DB->get_records('local_adele_path_user');
        $this->assertCount(2, $getlps, "Expected local_adele_path_user' to have 2 entries.");
        // Clean up the event sink.
        $this->sink->close();
    }


    /**
     * Test case to verify user enrollment functionality when a learning path is initiated.
     * This test specifically:
     * - Checks if users are correctly enrolled when a learning path starts
     * - Verifies event handling for module creation and user path updates
     * - Confirms proper enrollment of users in subsequent courses
     * - Validates the creation of adhoc tasks
     *
     *
     * @return void
     */
    public function test_user_path_starting_node_enrollment(): void {
        global $DB;
        $this->subscribe_users_to_lp();

        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        $contextcourseb = \context_course::instance($this->courseids[3]);
        $enrolledusers = get_enrolled_users($contextcourseb);

        // Assert that the course has 2 enrolled users.
        $this->assertCount(2, $enrolledusers, "Expected course with ID {$this->courseids[3]} to have 2 enrolled users.");

        $adhoctasks = $DB->get_records('task_adhoc');
        // Clean up the event sink.
        $this->sink->close();
    }

    /**
     * Test that satisfying node A's completion criteria grants users access to node B.
     *
     * Full scenario:
     * - Users are subscribed to the learning path and enrolled in node A's starting courses.
     * - Both courses in node A are marked as complete for every user, satisfying the
     *   course_completed criterion (min_courses = 2).
     * - Re-evaluating the path fires a node_finished event for each user.
     * - Processing those events via node_completion::enrol_child_courses enrolls
     *   users in node B's course (courseids[2], distinct from node A's courses).
     *
     * setUp assigns courseids[0,3] to dndnode_1 (node A) and courseids[2] to dndnode_2 (node B),
     * so the enrollment assertion is unambiguous.
     *
     * @return void
     */
    public function test_node_completion_grants_access_to_child_node(): void {
        global $DB;

        // Step 1: Subscribe users to the learning path.
        $this->subscribe_users_to_lp();

        // Step 2: Enroll users in node A (starting node, dndnode_1).
        // The first two user_path_updated events (indices 0,1) were fired by saved_module.
        // Calling updated_single on them saves user_path_relation to the DB and fires two
        // more "creation" user_path_updated events (indices 2,3) that carry the persisted
        // userpaths - those are the ones we'll reuse in step 4.
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 3: Mark node A's courses (courseids[0] and courseids[3]) complete.
        // Both courses must be complete to satisfy the course_completed criterion (min_courses=2).
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            foreach ([$this->courseids[0], $this->courseids[3]] as $courseid) {
                $this->mark_course_complete_in_db((int)$courseid, (int)$record->user_id);
            }
        }

        // Step 4: Re-evaluate paths using the persisted "creation" events from step 2.
        // Those events (at indices 2 and 3) hold userpaths that already have user_path_relation
        // set (creation = false), so updated_single will detect completion and fire node_finished.
        $allupdateevents = $this->get_update_events();
        // Indices 0,1 = original enrollment events; 2,3 = creation events from step 2.
        relation_update::updated_single($allupdateevents[2]);
        relation_update::updated_single($allupdateevents[3]);

        // Step 5: Verify node_finished was fired once per user.
        $nodefinishedevents = $this->get_node_finished_events();
        $this->assertCount(
            2,
            $nodefinishedevents,
            'Expected a node_finished event for each user when node A completion criteria are met.'
        );

        // Step 6: Enroll users in node B by processing node_finished events.
        foreach ($nodefinishedevents as $nfevent) {
            node_completion::enrol_child_courses($nfevent);
        }

        // Step 7: Assert both users are now enrolled in node B's course (courseids[2]).
        $contextb = \context_course::instance($this->courseids[2]);
        $enrolledusers = get_enrolled_users($contextb);
        $this->assertCount(
            2,
            $enrolledusers,
            "Expected both users to be enrolled in node B's course (courseids[2]) after node A completion."
        );

        // Step 8: Verify dndnode_2 restriction state flipped to 'inbetween' (accessible).
        // When dndnode_1 is complete, parent_courses.get_restriction_status() sees
        // $usernode['data']['completion']['feedback']['status'] == 'completed' for dndnode_1,
        // which satisfies the parent_courses restriction (min_courses=1).
        // getnodestatusforrestriciton then returns 'inbetween' because restrictionnodepaths
        // is non-empty (the OR-path through condition_1 is now satisfied).
        //
        // UserInformation.vue renders:
        // store.state.strings['node_access_restriction_inbetween']
        // = 'Der Kurs/Der Stapel ist freigeschaltet:'.
        $finalrecords = $DB->get_records('local_adele_path_user');
        foreach ($finalrecords as $record) {
            $json = json_decode($record->json, true);
            $fb2 = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb2['status_restriction'],
                "Expected dndnode_2 status_restriction='inbetween' for user {$record->user_id} after parent completion."
            );
            $this->assertEquals(
                'accessible',
                $fb2['status'],
                "Expected dndnode_2 status='accessible' for user {$record->user_id} after parent completion."
            );
        }

        $this->sink->close();
    }

    /**
     * Test that completing only one of two required courses puts node A into
     * the "inbetween" feedback state, which is what NodeInformation.vue renders
     * via data.completion.feedback.completion.inbetween.
     *
     * setUp assigns courseids[0,3] to dndnode_1 with min_courses=2.
     * Only courseids[0] is marked complete here, so the criterion is partially
     * met: completed=false but inbetween=true (at least one course has progress).
     *
     * Expected DB state after re-evaluation:
     *   user_path_relation['dndnode_1']['feedback']['status_completion'] === 'inbetween'
     *   user_path_relation['dndnode_1']['feedback']['completion']['inbetween']  is non-empty
     *   user_path_relation['dndnode_1']['feedback']['completion']['after']      is empty/null
     *
     * @return void
     */
    public function test_partial_course_completion_produces_inbetween_feedback(): void {
        global $DB;

        // Step 1: Subscribe users to the learning path.
        $this->subscribe_users_to_lp();

        // Step 2: Enroll users in node A — triggers two "creation" events (indices 2,3)
        // that carry the persisted userpaths used in step 4.
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 3: Mark ONLY courseids[0] complete — courseids[3] is intentionally left incomplete.
        // With min_courses=2, this satisfies 1 of 2: completed=false, inbetween=true.
        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $this->mark_course_complete_in_db((int)$this->courseids[0], (int)$record->user_id);
        }

        // Step 4: Re-evaluate paths using the "creation" events from step 2 (indices 2,3).
        $allupdateevents = $this->get_update_events();
        relation_update::updated_single($allupdateevents[2]);
        relation_update::updated_single($allupdateevents[3]);

        // Step 5: Verify no node_finished events were fired — node A is not fully complete.
        $nodefinishedevents = $this->get_node_finished_events();
        $this->assertCount(
            0,
            $nodefinishedevents,
            'Expected no node_finished events when only one of two required courses is complete.'
        );

        // Step 6: Read back DB and assert the feedback structure for dndnode_1.
        // This is the data NodeInformation.vue consumes via data.completion.feedback.
        //
        // The fixture stores these templates in condition_1_feedback:
        // feedback_before:    "[EN_215]{item} erfolgreich bearbeiten "
        // feedback_inbetween: "[EN_216]{item} erfolgreich bearbeiten"
        // feedback_after:     "[EN_217]{item} erfolgreich bearbeitet haben"
        //
        // {item} is assembled in course_completed.php for the inbetween branch:
        // $counttodo (=1) . ' ' . get_string('course_restricition_before_condition_from')
        // . $numbcourses (=2) . ' '
        // . get_string('course_description_before_condition_course_completed_kursen')
        // => "1 from 2 Kursen".
        $finished   = 1;
        $minvalue   = 2;
        $numbcourses = 2; // Courseids[0] and courseids[3] assigned to dndnode_1.
        $counttodo  = $minvalue - $finished; // 1
        $expecteditem = $counttodo . ' '
            . get_string('course_restricition_before_condition_from', 'local_adele')
            . $numbcourses . ' '
            . get_string('course_description_before_condition_course_completed_kursen', 'local_adele');
        // Result: "1 from 2 Kursen".

        $expectedbefore    = '[EN_215]' . $expecteditem . ' erfolgreich bearbeiten ';
        $expectedinbetween = '[EN_216]' . $expecteditem . ' erfolgreich bearbeiten';
        $expectedafter     = '[EN_217]' . $expecteditem . ' erfolgreich bearbeitet haben';

        $updatedrecords = $DB->get_records('local_adele_path_user');
        foreach ($updatedrecords as $record) {
            $json = json_decode($record->json, true);
            $nodefeedback = $json['user_path_relation']['dndnode_1']['feedback'];

            // Status_completion must be 'inbetween': one course done, not enough yet.
            $this->assertEquals(
                'inbetween',
                $nodefeedback['status_completion'],
                "Expected status_completion 'inbetween' for user {$record->user_id} when 1 of 2 courses complete."
            );

            // The inbetween feedback slot (shown in NodeInformation.vue via
            // data.completion.feedback.completion.inbetween) must contain the
            // fully rendered string — {item} replaced with the course-count phrase.
            $this->assertEquals(
                $expectedinbetween,
                $nodefeedback['completion']['inbetween'][0],
                "Expected rendered inbetween string for user {$record->user_id}: '{$expectedinbetween}'."
            );

            // Before[0] is also rendered with {item} (the "you still need to do X" prompt).
            $this->assertEquals(
                $expectedbefore,
                $nodefeedback['completion']['before'][0],
                "Expected rendered before string for user {$record->user_id}: '{$expectedbefore}'."
            );

            // After_all['condition_1'] holds the rendered "when complete" label;
            // it is always set regardless of completion state (not the same as 'after').
            $this->assertEquals(
                $expectedafter,
                $nodefeedback['completion']['after_all']['condition_1'],
                "Expected rendered after_all string for condition_1 for user {$record->user_id}: '{$expectedafter}'."
            );

            // The 'after' slot must remain null — it is only set when the node
            // is fully complete or the master flag overrides completion.
            $this->assertNull(
                $nodefeedback['completion']['after'],
                "Expected 'after' to be null when node A is only partially complete."
            );
        }

        $this->sink->close();
    }

    /**
     * Test that dndnode_2 is locked with status_restriction='before' right after enrollment,
     * before its parent node (dndnode_1) has been completed.
     *
     * dndnode_2 has two OR-linked restrictions:
     *   condition_1 (parent_courses)  — requires dndnode_1 to be complete
     *   condition_2 (manual)          — requires a teacher's manual unlock
     *
     * Neither is satisfied after enrollment, so:
     *   status_restriction = 'before'   (locked, restrictions not yet met)
     *   status             = 'not_accessible'
     *
     * The rendered restriction.before strings come from the *_feedback nodes:
     *   condition_1_feedback.feedback_before:
     *     template: "[EN_233]Sie {node_name} abgeschlossen haben"
     *     {node_name} = dndnode_1.data.fullname = '[EN_386]Collection' (fixture)
     *     rendered:  "[EN_233]Sie [EN_386]Collection abgeschlossen haben"
     *   condition_2_feedback.feedback_before:
     *     "[EN_232]eine manuelle Freigabe durch den Lehrenden stattgefunden hat"
     *     (no placeholders — literal string)
     *
     * The UserInformation.vue component shows:
     *   store.state.strings['node_access_restriction_before']
     *   = 'Sie haben keinen Zugang zu diesem Kurs/diesem Stapel. Eine Freischaltung erfolgt, wenn:'
     * followed by UserFeedbackBlock rendering Object.values(restriction.before_active).
     *
     * @return void
     */
    public function test_child_node_is_locked_before_parent_completes(): void {
        global $DB;

        // Step 1: Subscribe users to the learning path.
        $this->subscribe_users_to_lp();

        // Step 2: Enroll users in the starting node — triggers the initial path evaluation
        // for all nodes, including dndnode_2 whose restriction is not yet met.
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        // Step 3: Assert dndnode_2 is locked with 'before' restriction state.
        $expectedparent = '[EN_233]Sie [EN_386]Collection abgeschlossen haben';
        $expectedmanual = '[EN_232]eine manuelle Freigabe durch den Lehrenden stattgefunden hat';

        $userpathrecords = $DB->get_records('local_adele_path_user');
        foreach ($userpathrecords as $record) {
            $json = json_decode($record->json, true);
            $fb = $json['user_path_relation']['dndnode_2']['feedback'];

            // Neither restriction column is satisfied yet.
            $this->assertEquals(
                'before',
                $fb['status_restriction'],
                "Expected dndnode_2 status_restriction='before' for user {$record->user_id}."
            );

            // No restriction path is satisfied and no timed expiry → not_accessible.
            // UserInformation.vue gates all course content on status != 'not_accessible'.
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "Expected dndnode_2 status='not_accessible' for user {$record->user_id}."
            );

            // Before_valid holds restriction columns still reachable (no time expiry).
            // Both OR-columns are reachable so before_valid must be non-empty.
            $this->assertNotEmpty(
                $fb['restriction']['before_valid'],
                "Expected before_valid non-empty for user {$record->user_id}."
            );

            // Parent_courses column: {node_name} replaced with dndnode_1's fullname.
            $this->assertEquals(
                $expectedparent,
                $fb['restriction']['before']['condition_1_feedback'],
                "Expected rendered parent_courses restriction string for user {$record->user_id}."
            );

            // Manual column: no placeholder — exact fixture string passes through unchanged.
            $this->assertEquals(
                $expectedmanual,
                $fb['restriction']['before']['condition_2_feedback'],
                "Expected rendered manual restriction string for user {$record->user_id}."
            );
        }

        $this->sink->close();
    }

}
