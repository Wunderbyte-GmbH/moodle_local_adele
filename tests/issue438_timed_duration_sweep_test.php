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

use local_adele\task\check_timed_restrictions;

require_once(__DIR__ . '/adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Integration tests for the #438 sweep against a 'timed_duration' restriction
 * (a window relative to path creation). The sweep must close the window at the
 * right time without the learner returning, and must leave an open window alone.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class issue438_timed_duration_sweep_test extends adele_learningpath_testcase {

    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * dndnode_2 becomes a single timed_duration condition: a 1-day window from
     * path creation (selectedOption='0', durationValue='0' = days, selectedDuration=1).
     * Mirrors uc06.
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
                        $cn['data']['label'] = 'timed_duration';
                        $cn['data']['value'] = [
                            'selectedOption'   => '0',
                            'durationValue'    => '0',
                            'selectedDuration' => 1,
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

    private function subscribe_and_evaluate(): void {
        $this->subscribe_users_to_lp();
        foreach ($this->get_update_events() as $event) {
            relation_update::updated_single($event);
        }
    }

    private function run_sweep(): void {
        if ($this->sink) {
            $this->sink->close();
            $this->sink = null;
        }
        ob_start();
        (new check_timed_restrictions())->execute();
        ob_end_clean();
    }

    private function assert_node2_status(string $statusrestriction, string $status): void {
        global $DB;
        $found = 0;
        foreach ($DB->get_records('local_adele_path_user', ['status' => 'active']) as $row) {
            $json = json_decode($row->json, true);
            if (!is_array($json) || !isset($json['user_path_relation']['dndnode_2']['feedback'])) {
                continue;
            }
            $fb = $json['user_path_relation']['dndnode_2']['feedback'];
            $this->assertSame($statusrestriction, $fb['status_restriction'], "row {$row->id} status_restriction");
            $this->assertSame($status, $fb['status'], "row {$row->id} status");
            $found++;
        }
        $this->assertGreaterThan(0, $found, 'Expected at least one real learning-path row.');
    }

    // -------------------------------------------------------------------- tests.

    /**
     * The relative window has expired since the last compute -> the sweep closes it.
     */
    public function test_sweep_closes_expired_duration_window(): void {
        global $DB;
        $this->subscribe_and_evaluate();
        $this->assert_node2_status('inbetween', 'accessible');   // open at subscribe.

        // Backdate creation 2 days (1-day window ended 1 day ago) and stamp the last
        // recompute 3 days ago (before the window closed) — without recomputing.
        foreach ($DB->get_records('local_adele_path_user', ['status' => 'active']) as $row) {
            $DB->update_record('local_adele_path_user', [
                'id' => $row->id,
                'timecreated' => time() - 2 * 86400,
                'timemodified' => time() - 3 * 86400,
            ]);
        }

        $this->run_sweep();

        $this->assert_node2_status('after', 'not_accessible');   // closed by the sweep.
    }

    /**
     * While the window is still open the sweep must not recompute the path.
     */
    public function test_sweep_ignores_open_duration_window(): void {
        global $DB;
        $this->subscribe_and_evaluate();
        $this->assert_node2_status('inbetween', 'accessible');

        $before = $DB->get_records_menu('local_adele_path_user', ['status' => 'active'], '', 'id, timemodified');
        $this->run_sweep();
        $after = $DB->get_records_menu('local_adele_path_user', ['status' => 'active'], '', 'id, timemodified');

        $this->assertEquals($before, $after, 'An open timed_duration window must not be recomputed.');
        $this->assert_node2_status('inbetween', 'accessible');
    }
}
