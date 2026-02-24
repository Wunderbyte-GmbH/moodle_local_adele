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
 * UC-06 — Timed-duration restriction: dndnode_2 becomes accessible for a
 * configurable duration starting from path creation (selectedOption = '0').
 * The duration is expressed as N days / weeks / months.
 *
 * Two tests:
 *   a) test_node_is_accessible_during_active_window
 *      — path was just created and the window is 1 day long: the current time
 *        falls inside the window → inbetween / accessible.
 *   b) test_node_is_not_accessible_when_duration_window_has_expired
 *      — timecreated is patched to 2 days ago in the DB so the 1-day window
 *        has already closed → status_restriction = 'after', status = 'not_accessible'.
 *
 * NOTE on the status after window expiry:
 *   The expected UI status would be 'closed', but production code in
 *   getnodestatus() uses `strpos($label, 'timed')` to detect timed conditions.
 *   For $label === 'timed_duration' (or 'timed'), strpos() returns integer 0
 *   which PHP treats as falsy, so $hastimedcondition is never set to true.
 *   The while loop exits with $reachablecolumn still true and returns
 *   'not_accessible' instead of falling through to 'closed'.
 *   The assertion documents the actual runtime behaviour; the bug is tracked
 *   separately as "strpos falsy-zero for timed labels in getnodestatus()".
 *
 * Setup: patch_node_ids() replaces condition_1 (parent_courses) with a
 * timed_duration node (selectedOption='0', durationValue='0' = days,
 * selectedDuration=1), and strips condition_2 / condition_2_feedback so the
 * timed_duration path is the only OR-column evaluated.
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
 * Timed-duration restriction: active window and expired window tests.
 *
 * @package    local_adele
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(course_restriction\course_restriction_status::class)]
final class uc06_timed_duration_restriction_test extends adele_learningpath_testcase {
    /**
     * Uses the main fixture; patch_node_ids() replaces the restriction on
     * dndnode_2 with a single timed_duration OR-column.
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * Replace dndnode_2's condition_1 with a timed_duration condition that
     * spans 1 day from path creation (selectedOption='0').  Strip the manual
     * OR-column so timed_duration is the only restriction path.
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
                // selectedOption='0' means start = $userpath->timecreated.
                // durationValue='0' = days; selectedDuration=1 → 1-day window.
                foreach ($node['restriction']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['data']['label'] = 'timed_duration';
                        $cn['data']['description_before'] =
                            '[EN_placeholder]Die Bearbeitungsfrist läuft noch.';
                        $cn['data']['value'] = [
                            'selectedOption'   => '0',
                            'durationValue'    => '0',
                            'selectedDuration' => 1,
                        ];
                    }
                }
                unset($cn);

                // Strip condition_2 (manual) + condition_2_feedback to isolate
                // timed_duration as the only restriction OR-column.
                $node['restriction']['nodes'] = array_values(array_filter(
                    $node['restriction']['nodes'],
                    fn($cn) => !in_array($cn['id'], ['condition_2', 'condition_2_feedback'])
                ));
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
    // Helpers.

    /**
     * Subscribe users, run the first updated_single pass to create
     * user_path_relation rows, then optionally backdate timecreated in every
     * stored record and fire a fresh evaluation pass.
     *
     * When $backdayseconds is 0 the records are not touched — they remain at
     * "path just created" which keeps the 1-day window open.
     *
     * When $backdayseconds > 0 the timecreated field is moved that many seconds
     * into the past so the 1-day window (86 400 s) falls entirely in the past.
     *
     * @param int $backdayseconds Seconds to subtract from timecreated; 0 = no change.
     */
    private function enrol_and_optionally_expire(int $backdayseconds): void {
        global $DB;

        // Step 1: Subscribe + initial evaluation.
        $this->subscribe_users_to_lp();
        $updateevents = $this->get_update_events();
        relation_update::updated_single($updateevents[0]);
        relation_update::updated_single($updateevents[1]);

        if ($backdayseconds <= 0) {
            // Window is still open; no further DB patching required.
            return;
        }

        // Step 2: Move timecreated back so the 1-day window has already expired.
        $records = $DB->get_records('local_adele_path_user');
        foreach ($records as $record) {
            $DB->set_field(
                'local_adele_path_user',
                'timecreated',
                $record->timecreated - $backdayseconds,
                ['id' => $record->id]
            );
        }

        // Step 3: Re-evaluate with fresh events carrying the backdated records.
        $freshrecords = $DB->get_records('local_adele_path_user');
        foreach ($freshrecords as $freshrecord) {
            $freshrecord->json = json_decode($freshrecord->json, true);
            $event = user_path_updated::create([
                'objectid' => $freshrecord->id,
                'context'  => context_system::instance(),
                'other'    => ['userpath' => $freshrecord],
            ]);
            relation_update::updated_single($event);
        }
    }

    // -------------------------------------------------------------------------
    // Tests.

    /**
     * ACTIVE WINDOW: path was just created; the 1-day timed_duration window
     * is open, so dndnode_2 is accessible.
     *
     * timed_duration::get_restriction_status() computes:
     *   start = $userpath->timecreated  (≈ now)
     *   end   = now + 86 400 s
     *   → iscurrenttimeinrange = true → completed = true
     *
     * getnodestatusforrestriciton → 'inbetween' (path satisfied)
     * getnodestatus               → 'accessible'
     *
     * @return void
     */
    public function test_node_is_accessible_during_active_window(): void {
        global $DB;

        $this->enrol_and_optionally_expire(0);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            $this->assertEquals(
                'inbetween',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'inbetween' when within the timed_duration window."
            );
            $this->assertEquals(
                'accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'accessible' when within the timed_duration window."
            );
        }

        $this->sink->close();
    }

    /**
     * EXPIRED WINDOW: timecreated is moved 2 days into the past, placing the
     * 1-day window entirely before the current time.
     *
     * timed_duration::get_restriction_status() computes:
     *   start = timecreated (2 days ago)
     *   end   = timecreated + 86 400 s (1 day ago)
     *   → isafterrange = true → completed = false, inbetween = false
     *
     * getnodestatusforrestriciton → 'after'   (istypetimedandcolumnvalid returns
     *                                false for expired timed_duration, before_valid
     *                                is empty → falls through to 'after')
     * getnodestatus               → 'not_accessible'
     *
     * NOTE: 'not_accessible' is produced instead of the expected 'closed' due
     * to the strpos($label, 'timed') === 0 falsy bug in getnodestatus().
     * This assertion documents the current runtime behaviour.
     *
     * @return void
     */
    public function test_node_is_not_accessible_when_duration_window_has_expired(): void {
        global $DB;

        // Move timecreated 2 days into the past so the 1-day window is over.
        $this->enrol_and_optionally_expire(2 * 86400);

        $records = $DB->get_records('local_adele_path_user');
        $this->assertNotEmpty($records, 'Expected user path records to exist.');

        foreach ($records as $record) {
            $json = json_decode($record->json, true);
            $fb   = $json['user_path_relation']['dndnode_2']['feedback'];

            // Getnodestatusforrestriciton() correctly identifies the window as
            // expired (istypetimedandcolumnvalid returns false → before_valid
            // stays empty → returns 'after').
            $this->assertEquals(
                'after',
                $fb['status_restriction'],
                "User {$record->user_id}: expected 'after' when timed_duration window has expired."
            );

            // Getnodestatus() returns 'not_accessible' instead of 'closed' due
            // to the strpos(label, 'timed') === 0 falsy bug (see class docblock).
            $this->assertEquals(
                'not_accessible',
                $fb['status'],
                "User {$record->user_id}: expected 'not_accessible' (see docblock) when window expired."
            );
        }

        $this->sink->close();
    }
}
