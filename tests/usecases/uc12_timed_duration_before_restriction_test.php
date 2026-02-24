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
 * UC-12 — Timed-duration restriction: before state when selectedOption='1'
 * (first_enrolled-based duration) and first_enrolled is not yet stamped on
 * the node.
 *
 * One test:
 *   test_node_is_locked_before_first_enrolment_sets_window
 *     — The duration window for dndnode_2 is configured to start from
 *       node['data']['first_enrolled'] (selectedOption='1').  At the first
 *       evaluation, that timestamp has not been set because the user has not
 *       yet completed dndnode_1 (which would trigger the auto-enrolment and
 *       first_enrolled stamp via node_completion.php).
 *
 *       timed_duration::get_restriction_status() falls back to
 *         $starttime = get_string('course_condition_timed_duration_start', 'local_adele')
 *       which is a plain string, not a DateTime.  The block that computes
 *       $iscurrenttimeinrange / $isbeforerange / $isafterrange is skipped
 *       (guarded by !is_string($starttime)).  Consequently:
 *         iscurrenttimeinrange = false
 *         isbeforerange        = '' (undefined → null-coalesced to '')
 *         isafterrange         = '' (undefined → null-coalesced to '')
 *         completed            = false
 *         inbetween            = false
 *
 *       validatenoderstriction() sees completed=false → failedrestriction=true
 *       → restrictionnodepaths stays empty.
 *
 *       getnodestatusforrestriciton():
 *         restrictionnodepaths is empty AND node['restriction'] exists
 *         → does not short-circuit to 'inbetween'.
 *         istypetimedandcolumnvalid() returns true (isafter='' is falsy → not
 *         expired) → before_valid populated → returns 'before'.
 *
 *       getnodestatus():
 *         Restriction paths empty → restriction not satisfied.
 *         Walks restriction nodes: strpos('timed_duration', 'timed') = 0
 *         (the known falsy-zero cosmetic bug) so $hastimedcondition is never
 *         set, but the very first while-loop iteration still finds $reachablecolumn
 *         true → returns 'not_accessible' immediately.
 *
 *       Expected:
 *         status_restriction = 'before'
 *         status             = 'not_accessible'
 *
 * Why a separate class from UC-06:
 *   patch_node_ids() is called once per class during setUp().  UC-06 uses
 *   selectedOption='0' (timecreated-based); this file needs selectedOption='1'
 *   (first_enrolled-based) without setting node['data']['first_enrolled'],
 *   which requires its own patch_node_ids() override.
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Timed-duration restriction: before state when first_enrolled not yet set.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc12_timed_duration_before_restriction_test extends adele_learningpath_testcase {
    /**
     * Uses the main fixture; patch_node_ids() replaces the restriction on
     * dndnode_2 with a single timed_duration OR-column using selectedOption='1'
     * (first_enrolled-based).
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Replace dndnode_2's condition_1 with a timed_duration condition whose
     * window starts from the node's first_enrolled timestamp (selectedOption='1').
     *
     * node['data']['first_enrolled'] is intentionally NOT set here — it will
     * remain absent until the auto-enrolment logic in node_completion.php runs,
     * which only happens after dndnode_1's completion requirements are met.
     *
     * The manual OR-column (condition_2 + condition_2_feedback) is stripped
     * so that timed_duration is the only restriction path evaluated.
     *
     * dndnode_1 is given two courses so it remains structurally valid.
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_2') {
                $node['data']['course_node_id'] = [$this->courseids[2]];

                // Replace condition_1: parent_courses → timed_duration.
                // selectedOption='1' means start = node['data']['first_enrolled'].
                // first_enrolled is deliberately omitted from node['data'] so that
                // the condition resolves to the 'before' (not yet started) state.
                foreach ($node['restriction']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['data']['label'] = 'timed_duration';
                        $cn['data']['description_before'] =
                            '[EN_placeholder]Enrollment window has not started yet.';
                        $cn['data']['value'] = [
                            'selectedOption'   => '1', // Start from first_enrolled.
                            'durationValue'    => '0', // Days.
                            'selectedDuration' => 7, // 7-day window once enrolled.
                        ];
                    }
                }
                unset($cn);

                // Strip condition_2 (manual) and condition_2_feedback so that
                // timed_duration is the sole restriction OR-column.
                $node['restriction']['nodes'] = array_values(array_filter(
                    $node['restriction']['nodes'],
                    fn($cn) => !in_array($cn['id'], ['condition_2', 'condition_2_feedback'])
                ));

                // Ensure first_enrolled is absent from node data so the
                // timed_duration code falls back to the lang-string placeholder.
                unset($node['data']['first_enrolled']);
            } else {
                $node['data']['course_node_id'] = [
                    $this->courseids[0],
                    $this->courseids[3],
                ];
            }
        }
        unset($node);
    }

    // -------------------------------------------------------------------------
    // Tests.

    /**
     * BEFORE: first_enrolled is not yet set on dndnode_2.
     *
     * The 7-day duration window cannot start because there is no enrolment
     * timestamp.  timed_duration::get_restriction_status() falls back to a
     * lang-string $starttime and skips the time-range calculation entirely.
     *
     * timed_duration result:
     *   completed = false   (iscurrenttimeinrange was never set to true)
     *   inbetween = false
     *   isbefore  = ''      ($isbeforerange undefined → null-coalesced)
     *   isafter   = ''      ($isafterrange  undefined → null-coalesced)
     *
     * getnodestatusforrestriciton():
     *   istypetimedandcolumnvalid → true (isafter='' is falsy → column not expired)
     *   → before_valid populated → returns 'before'
     *
     * getnodestatus():
     *   strpos('timed_duration', 'timed') = 0 (falsy) → $hastimedcondition not
     *   set; $reachablecolumn stays true → returns 'not_accessible' immediately.
     *   (Same cosmetic strpos bug documented in UC-06 class docblock.)
     *
     * Expected:
     *   status_restriction = 'before'
     *   status             = 'not_accessible'
     *
     * @return void
     */
    public function test_node_is_locked_before_first_enrolment_sets_window(): void {
        global $DB;

        // Subscribe users and run the initial evaluation pass.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist after enrollment.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);

            // Sanity: first_enrolled must not be stamped on dndnode_2 yet,
            // because the user hasn't completed dndnode_1.
            $this->assertArrayNotHasKey(
                'first_enrolled',
                $json['user_path_relation']['dndnode_2']['data'] ?? [],
                "User {$record->user_id}: first_enrolled must be absent on dndnode_2 before completion."
            );

            $fb = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'before',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'before' when first_enrolled is not yet set."
            );
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' when timed_duration window"
                    . " has not started (strpos falsy-zero behaviour; see class docblock)."
            );
        }

        $this->sink->close();
    }
}
