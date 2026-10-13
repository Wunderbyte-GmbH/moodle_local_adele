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

use context_system;
use local_adele\event\user_path_updated;

/**
 * Truth table of a node whose access combines a time window with its
 * predecessor (chain T4/T5 of the E2E plan; E2E issue, section 2: "alle
 * UND-/ODER-Wahrheitstabellen").
 *
 * Window before / inside / after times predecessor completed or not - six
 * rows (each test class provides them and its expectation), each for every
 * subscribed user. The browser chain checks some rows
 * through the whole system; here every row is evaluated by relation_update,
 * the same code the system uses, on the AND fixture (one column) and on the
 * OR fixture (two columns).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait time_predecessor_truth_table {
    /**
     * The six rows of the table: window x predecessor.
     *
     * @return array
     */
    private static function table(): array {
        $rows = [];
        foreach (['before', 'inside', 'after'] as $window) {
            foreach ([false, true] as $done) {
                $rows["window {$window}, predecessor " . ($done ? 'completed' : 'open')] = [$window, $done];
            }
        }
        return $rows;
    }

    /**
     * The window as it is written into the stored path.
     *
     * @param string $window before, inside or after.
     * @return string[] strtotime() expressions for start and end.
     */
    private static function window(string $window): array {
        return [
            'before' => ['+7 days', '+14 days'],
            'inside' => ['-7 days', '+7 days'],
            'after' => ['-14 days', '-1 day'],
        ][$window];
    }

    /**
     * Subscribe, write the window, optionally complete the predecessor,
     * evaluate - then node 2's status for every user.
     *
     * @param string $window
     * @param bool $done
     * @return string[] status per user id.
     */
    private function evaluate(string $window, bool $done): array {
        global $DB;
        [$start, $end] = self::window($window);
        $this->subscribe_users_to_lp();
        foreach ($this->get_update_events() as $event) {
            relation_update::updated_single($event);
        }
        foreach ($DB->get_records('local_adele_path_user') as $record) {
            $json = json_decode($record->json, true);
            foreach ($json['tree']['nodes'] as &$treenode) {
                if ($treenode['id'] !== 'dndnode_2') {
                    continue;
                }
                foreach ($treenode['restriction']['nodes'] as &$condition) {
                    if (($condition['data']['label'] ?? '') === 'timed') {
                        $condition['data']['value']['start'] = strtotime($start);
                        $condition['data']['value']['end'] = strtotime($end);
                    }
                }
                unset($condition);
            }
            unset($treenode);
            $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
            if ($done) {
                $this->mark_course_complete_in_db((int) $this->courseids[0], (int) $record->user_id);
            }
        }
        $statuses = [];
        foreach ($DB->get_records('local_adele_path_user') as $record) {
            $record->json = json_decode($record->json, true);
            relation_update::updated_single(user_path_updated::create([
                'objectid' => $record->id,
                'context' => context_system::instance(),
                'other' => ['userpath' => $record],
            ]));
        }
        foreach ($DB->get_records('local_adele_path_user') as $record) {
            $json = json_decode($record->json, true);
            $statuses[$record->user_id] = $json['user_path_relation']['dndnode_2']['feedback']['status'];
        }
        $this->sink->close();
        return $statuses;
    }
}
