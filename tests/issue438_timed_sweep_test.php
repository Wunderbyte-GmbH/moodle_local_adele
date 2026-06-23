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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_adele;

use context_system;
use local_adele\event\user_path_updated;
use local_adele\task\check_timed_restrictions;

require_once(__DIR__ . '/adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Integration tests for the #438 sweep (check_timed_restrictions::execute):
 * a timed node unlocks / closes at its scheduled time without the learner
 * opening the course, and only when a boundary actually crossed.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class issue438_timed_sweep_test extends adele_learningpath_testcase {

    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * dndnode_2 becomes a single timed condition (placeholder future dates; each
     * test overwrites them). Mirrors uc02 so the timed path is isolated.
     *
     * @param array $nodes
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_2') {
                $node['data']['course_node_id'] = [$this->courseids[2]];
                foreach ($node['restriction']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['data']['label'] = 'timed';
                        $cn['data']['value'] = [
                            'start' => date('Y-m-d\TH:i', strtotime('+1 day')),
                            'end'   => date('Y-m-d\TH:i', strtotime('+7 days')),
                        ];
                    }
                }
                unset($cn);
                $node['restriction']['nodes'] = array_values(array_filter(
                    $node['restriction']['nodes'],
                    fn($cn) => !in_array($cn['id'], ['condition_2', 'condition_2_feedback'])
                ));
            } else {
                $node['data']['course_node_id'] = [$this->courseids[0], $this->courseids[3]];
            }
        }
        unset($node);
    }

    // ------------------------------------------------------------------ helpers.

    /** Subscribe the two users and run the first evaluation (creates path_user rows). */
    private function subscribe_and_evaluate(): void {
        $this->subscribe_users_to_lp();
        foreach ($this->get_update_events() as $event) {
            relation_update::updated_single($event);
        }
    }

    /**
     * Overwrite dndnode_2's timed window and the snapshot's timemodified in every
     * path_user row WITHOUT recomputing — so we can stage an exact gate scenario.
     *
     * @param string $start strtotime expression for the start date.
     * @param string $end strtotime expression for the end date.
     * @param int $timemodified Value to stamp as "last recomputed".
     */
    private function stage(string $start, string $end, int $timemodified): void {
        global $DB;
        foreach ($DB->get_records('local_adele_path_user') as $record) {
            $json = json_decode($record->json, true);
            foreach ($json['tree']['nodes'] as &$tn) {
                if ($tn['id'] !== 'dndnode_2') {
                    continue;
                }
                foreach ($tn['restriction']['nodes'] as &$cn) {
                    if (($cn['id'] ?? '') === 'condition_1') {
                        $cn['data']['value']['start'] = date('Y-m-d\TH:i', strtotime($start));
                        $cn['data']['value']['end']   = date('Y-m-d\TH:i', strtotime($end));
                    }
                }
                unset($cn);
            }
            unset($tn);
            $DB->update_record('local_adele_path_user', [
                'id' => $record->id,
                'json' => json_encode($json),
                'timemodified' => $timemodified,
            ]);
        }
    }

    /** Run the scheduled task, swallowing its mtrace output. */
    private function run_sweep(): void {
        // The harness redirects events into a sink, which suppresses observers.
        // Close it first so the sweep's user_path_updated event reaches
        // relation_update::updated_single exactly as it would in production.
        if ($this->sink) {
            $this->sink->close();
            $this->sink = null;
        }
        ob_start();
        (new check_timed_restrictions())->execute();
        ob_end_clean();
    }

    /**
     * @return array<int, object> path_user rows keyed by id.
     */
    private function rows(): array {
        global $DB;
        return $DB->get_records('local_adele_path_user', ['status' => 'active']);
    }

    /**
     * Assert dndnode_2's restriction status across all active rows.
     *
     * @param string $statusrestriction expected feedback.status_restriction.
     * @param string $status expected feedback.status.
     */
    private function assert_node2_status(string $statusrestriction, string $status): void {
        $found = 0;
        foreach ($this->rows() as $row) {
            $json = json_decode($row->json, true);
            if (!is_array($json) || !isset($json['user_path_relation']['dndnode_2']['feedback'])) {
                continue; // Foreign / injected rows are not part of this assertion.
            }
            $fb = $json['user_path_relation']['dndnode_2']['feedback'];
            $this->assertSame($statusrestriction, $fb['status_restriction'], "row {$row->id} status_restriction");
            $this->assertSame($status, $fb['status'], "row {$row->id} status");
            $found++;
        }
        $this->assertGreaterThan(0, $found, 'Expected at least one real learning-path row to assert on.');
    }

    // -------------------------------------------------------------------- tests.

    /**
     * A: the window opened (start crossed since last compute) -> the sweep unlocks
     * the node even though the learner never returned.
     */
    public function test_sweep_unlocks_when_window_opened(): void {
        $this->subscribe_and_evaluate();
        $this->assert_node2_status('before', 'not_accessible');     // locked initially.

        // Start is now in the past (1h ago) but newer than the last compute (2h ago).
        $this->stage('-1 hour', '+7 days', time() - 7200);
        $this->run_sweep();

        $this->assert_node2_status('inbetween', 'accessible');      // unlocked by the sweep.
    }

    /**
     * B: the window is still in the future -> the sweep must NOT touch the path.
     */
    public function test_sweep_ignores_future_window(): void {
        global $DB;
        $this->subscribe_and_evaluate();

        $this->stage('+1 day', '+7 days', time() - 60);
        $before = $DB->get_records_menu('local_adele_path_user', ['status' => 'active'], '', 'id, timemodified');

        $this->run_sweep();

        $after = $DB->get_records_menu('local_adele_path_user', ['status' => 'active'], '', 'id, timemodified');
        $this->assertEquals($before, $after, 'Future-window paths must not be recomputed.');
        $this->assert_node2_status('before', 'not_accessible');
    }

    /**
     * C: the boundary is older than the last recompute -> already accounted for,
     * so the sweep must skip it (no needless recompute).
     */
    public function test_sweep_skips_already_accounted_boundary(): void {
        global $DB;
        $this->subscribe_and_evaluate();

        // Boundary 1h ago, but the path was (notionally) recomputed 30 min ago.
        $this->stage('-1 hour', '+7 days', time() - 1800);
        $before = $DB->get_records_menu('local_adele_path_user', ['status' => 'active'], '', 'id, timemodified');

        $this->run_sweep();

        $after = $DB->get_records_menu('local_adele_path_user', ['status' => 'active'], '', 'id, timemodified');
        $this->assertEquals($before, $after, 'Already-accounted boundaries must not trigger a recompute.');
    }

    /**
     * D: the end date passed -> the sweep closes the window.
     */
    public function test_sweep_closes_when_window_expired(): void {
        $this->subscribe_and_evaluate();

        // start long past (already accounted), end 1h ago (newly crossed).
        $this->stage('-30 days', '-1 hour', time() - 7200);
        $this->run_sweep();

        // Per uc02, an expired timed window reports 'after' / 'not_accessible'.
        $this->assert_node2_status('after', 'not_accessible');
    }

    /**
     * Idempotency: running the sweep again after it already unlocked must be a
     * no-op (the bumped timemodified moves the boundary out of the window).
     */
    public function test_sweep_is_idempotent(): void {
        global $DB;
        $this->subscribe_and_evaluate();
        $this->stage('-1 hour', '+7 days', time() - 7200);

        $this->run_sweep();
        $this->assert_node2_status('inbetween', 'accessible');
        $first = $DB->get_records_menu('local_adele_path_user', ['status' => 'active'], '', 'id, timemodified');

        $this->run_sweep();
        $second = $DB->get_records_menu('local_adele_path_user', ['status' => 'active'], '', 'id, timemodified');

        $this->assertEquals($first, $second, 'A second sweep must not recompute an already-current path.');
    }

    /**
     * Isolation: a corrupt path mentioning "timed" is skipped without aborting the
     * sweep — a genuinely due path in the same run is still unlocked.
     */
    public function test_sweep_survives_malformed_json(): void {
        global $DB;
        $this->subscribe_and_evaluate();
        $this->stage('-1 hour', '+7 days', time() - 7200);

        // A broken active row that passes the SQL "timed" pre-filter.
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => 999999, 'course_id' => 999999, 'learning_path_id' => 999999,
            'status' => 'active', 'timecreated' => time() - 7200, 'timemodified' => time() - 7200,
            'createdby' => 0, 'json' => '{ broken json "timed" ',
        ]);

        $this->run_sweep();   // must not throw.

        $this->assert_node2_status('inbetween', 'accessible');   // valid path still processed.
    }

    /**
     * A path with no timed restriction is never recomputed (SQL pre-filter).
     */
    public function test_sweep_ignores_non_timed_path(): void {
        global $DB;
        $id = $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => 1, 'course_id' => 1, 'learning_path_id' => 1,
            'status' => 'active', 'timecreated' => time() - 7200, 'timemodified' => 1234,
            'createdby' => 0,
            'json' => json_encode(['tree' => ['nodes' => [
                ['id' => 'n1', 'restriction' => ['nodes' => [['data' => ['label' => 'manual', 'value' => []]]]]],
            ]]]),
        ]);

        $this->run_sweep();

        $this->assertEquals(1234, $DB->get_field('local_adele_path_user', 'timemodified', ['id' => $id]),
            'A non-timed path must not be recomputed.');
    }

    /**
     * An inactive (revision) path is never touched, even with a due timed window.
     */
    public function test_sweep_ignores_inactive_path(): void {
        global $DB;
        $id = $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => 1, 'course_id' => 1, 'learning_path_id' => 1,
            'status' => 'revision', 'timecreated' => time() - 7200, 'timemodified' => 1234,
            'createdby' => 0,
            'json' => json_encode(['tree' => ['nodes' => [
                ['id' => 'n1', 'restriction' => ['nodes' => [
                    ['data' => ['label' => 'timed', 'value' => ['start' => date('Y-m-d\TH:i', time() - 600)]]],
                ]]],
            ]]]),
        ]);

        $this->run_sweep();

        $this->assertEquals(1234, $DB->get_field('local_adele_path_user', 'timemodified', ['id' => $id]),
            'An inactive path must not be recomputed.');
    }

    // ------------------------------------------- enrolment / time-change lifecycle.

    /** Set dndnode_2's window WITHOUT touching timemodified (simulates an admin edit). */
    private function set_window(string $start, string $end): void {
        global $DB;
        foreach ($DB->get_records('local_adele_path_user', ['status' => 'active']) as $record) {
            $json = json_decode($record->json, true);
            foreach ($json['tree']['nodes'] as &$tn) {
                if ($tn['id'] !== 'dndnode_2') {
                    continue;
                }
                foreach ($tn['restriction']['nodes'] as &$cn) {
                    if (($cn['id'] ?? '') === 'condition_1') {
                        $cn['data']['value']['start'] = date('Y-m-d\TH:i', strtotime($start));
                        $cn['data']['value']['end']   = date('Y-m-d\TH:i', strtotime($end));
                    }
                }
                unset($cn);
            }
            unset($tn);
            $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
        }
    }

    /** Recompute every active path the normal way (as an LP edit or a view would). */
    private function recompute_via_event(): void {
        global $DB;
        foreach ($DB->get_records('local_adele_path_user', ['status' => 'active']) as $record) {
            $decoded = json_decode($record->json, true);
            if (!is_array($decoded)) {
                continue;
            }
            $record->json = $decoded;
            $event = user_path_updated::create([
                'objectid' => $record->id,
                'context'  => context_system::instance(),
                'other'    => ['userpath' => $record],
            ]);
            relation_update::updated_single($event);
        }
    }

    /** The set of course ids a user currently has any enrolment in (sorted). */
    private function enrolled_courseids(int $userid): array {
        global $DB;
        $sql = "SELECT DISTINCT e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid";
        $ids = array_map('intval', $DB->get_fieldset_sql($sql, ['userid' => $userid]));
        sort($ids);
        return $ids;
    }

    /** A user id taken from the seeded learning-path rows. */
    private function a_userid(): int {
        global $DB;
        $records = $DB->get_records('local_adele_path_user', ['status' => 'active'], 'id', 'id, user_id', 0, 1);
        return (int) reset($records)->user_id;
    }

    /**
     * Reversed direction: a window that opens via the sweep enrols the user into
     * the node's course.
     */
    public function test_sweep_opening_window_enrols_user(): void {
        $this->subscribe_and_evaluate();   // future window -> locked, not enrolled in node course.
        $uid = $this->a_userid();
        $this->assertNotContains((int) $this->courseids[2], $this->enrolled_courseids($uid),
            'Precondition: the locked node course is not enrolled yet.');

        $this->stage('-1 hour', '+7 days', time() - 7200);   // window opens, boundary crossed.
        $this->run_sweep();

        $this->assert_node2_status('inbetween', 'accessible');
        $this->assertContains((int) $this->courseids[2], $this->enrolled_courseids($uid),
            'Opening the window via the sweep must enrol the user into the node course.');
    }

    /**
     * When the window expires the node closes but enrolment is neither duplicated
     * nor (currently) reversed — the enrolled course set is unchanged.
     *
     * FOR DISCUSSION / FUTURE WORK: whether a closing timed window should ALSO
     * un-enrol the learner from the node course is an open product decision
     * (deferred). This test pins the CURRENT behaviour (keep enrolment); update it
     * when that decision is made.
     */
    public function test_sweep_closing_window_keeps_enrolment(): void {
        $this->subscribe_and_evaluate();
        $this->stage('-1 hour', '+7 days', time() - 7200);
        $this->run_sweep();                                  // open -> enrolled.
        $uid = $this->a_userid();
        $before = $this->enrolled_courseids($uid);
        $this->assertContains((int) $this->courseids[2], $before);

        $this->stage('-30 days', '-1 hour', time() - 7200); // window expired, boundary crossed.
        $this->run_sweep();

        $this->assert_node2_status('after', 'not_accessible');
        $this->assertEquals($before, $this->enrolled_courseids($uid),
            'Closing the window must not change enrolment (no re-enrol, no spurious enrolment).');
    }

    /**
     * The core scenario: a user enrolled while the window matched, then the time
     * is pushed back (admin edit) so the node re-locks, then brought forward again.
     * The user must NOT be enrolled a second time in either transition.
     */
    public function test_enrolment_idempotent_across_time_changes(): void {
        $this->subscribe_and_evaluate();
        $this->stage('-1 hour', '+7 days', time() - 7200);
        $this->run_sweep();                                  // open -> enrolled.
        $uid = $this->a_userid();
        $baseline = $this->enrolled_courseids($uid);
        $this->assertContains((int) $this->courseids[2], $baseline);

        // Time pushed back into the future -> node re-locks on recompute.
        $this->set_window('+5 days', '+30 days');
        $this->recompute_via_event();
        $this->assert_node2_status('before', 'not_accessible');
        $this->assertEquals($baseline, $this->enrolled_courseids($uid),
            'Re-locking must not enrol the user again.');

        // Time brought back so the window is open again -> recompute must be idempotent.
        $this->set_window('-1 hour', '+7 days');
        $this->recompute_via_event();
        $this->assert_node2_status('inbetween', 'accessible');
        $this->assertEquals($baseline, $this->enrolled_courseids($uid),
            'Re-opening must not enrol the user a second time (idempotent).');
    }
}
