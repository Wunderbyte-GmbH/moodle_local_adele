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
 * Helper functions for adhoc tasks.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\helper;

use local_adele\task\update_user_path;
use local_adele\course_restriction\conditions\timed_duration;
use stdClass;

/**
 * Schedules per-user adhoc tasks that re-evaluate a learning path exactly when a
 * timed restriction boundary is reached (#438).
 *
 * This is the event-driven alternative to a periodic sweep: instead of scanning
 * every path-user row every few minutes, a single adhoc task is queued to fire at
 * each future boundary. The work is therefore proportional to the number of timed
 * boundaries, not to the size of the table - which is what keeps it cheap on large
 * sites.
 *
 * Robustness:
 *  - The dedup key (customdata->time) encodes the SLOT identity
 *    (restriction-node id + slot + userpath id), NOT the boundary value, so editing
 *    a date updates the existing task via reschedule_or_queue_adhoc_task() instead
 *    of leaving a stale duplicate behind.
 *  - Past boundaries are skipped, so a re-evaluation that re-stamps the path can
 *    never queue an immediate task and spin in a tight reschedule loop.
 *  - update_user_path re-reads the CURRENT json at fire time, so even a task that
 *    fires against a since-changed window simply recomputes the real state.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_task_helper {
    /**
     * Seconds added after a boundary before the task runs, so the boundary has
     * definitely passed (clock skew / cron granularity) when the path is recomputed.
     */
    private const RUNTIME_BUFFER = 120;

    /**
     * Schedule (or reschedule) the timed-restriction adhoc tasks for a single node.
     *
     * @param array $node The learning path node (array form) containing restriction data.
     * @param stdClass $userpath The user path object (learning_path_id, user_id, course_id, id, timecreated).
     * @return void
     */
    public static function set_scheduled_adhoc_tasks($node, $userpath) {
        if (!isset($node['restriction']['nodes']) || !is_array($node['restriction']['nodes'])) {
            return;
        }

        $timecreated = (int)($userpath->timecreated ?? 0);

        foreach ($node['restriction']['nodes'] as $restrictionnode) {
            $label = $restrictionnode['data']['label'] ?? '';

            if ($label === 'timed') {
                // Fixed start/end boundaries: one task per slot. Read through
                // time_value so the task is planned for exactly the second the
                // evaluation switches (#581) - strtotime() read a legacy value
                // in a different way than the evaluation did.
                foreach (['start', 'end'] as $slot) {
                    $timestamp = time_value::to_timestamp($restrictionnode['data']['value'][$slot] ?? null);
                    if ($timestamp === null) {
                        continue;
                    }
                    self::schedule_boundary($userpath, $restrictionnode['id'], $slot, $timestamp);
                }
            } else if ($label === 'timed_duration') {
                // Relative window: the only future boundary is when the window closes.
                $close = self::timed_duration_close_time($restrictionnode, $node, $timecreated);
                if ($close !== null) {
                    self::schedule_boundary($userpath, $restrictionnode['id'], 'durationclose', $close);
                }
            }
        }
    }

    /**
     * Queue or reschedule a single boundary task, skipping boundaries already in the past.
     *
     * @param stdClass $userpath
     * @param string|int $restrictionnodeid The restriction-node id (part of the dedup key).
     * @param string $slot The boundary slot: 'start' | 'end' | 'durationclose'.
     * @param int $boundary The unix timestamp of the boundary.
     * @return void
     */
    private static function schedule_boundary($userpath, $restrictionnodeid, $slot, $boundary) {
        // No point scheduling a task for a boundary that has already passed: such
        // access is granted synchronously by the recompute that is running right now.
        // Scheduling an immediate task would create a perpetual loop (task fires ->
        // recompute -> sees past boundary -> schedules another immediate task).
        if ($boundary <= \core\di::get(\core\clock::class)->time()) {
            return;
        }

        $taskdata = new stdClass();
        $taskdata->learning_path_id = $userpath->learning_path_id;
        $taskdata->user_id = $userpath->user_id;
        $taskdata->course_id = $userpath->course_id;
        $taskdata->userpath_id = $userpath->id;
        // Stable slot-based dedup key: restriction-node id + slot + userpath id. This
        // value does NOT change when the boundary moves, so reschedule_or_queue_adhoc_task
        // finds and updates the existing row (adjusting nextruntime) rather than inserting
        // a duplicate. Do NOT include the timestamp here.
        $taskdata->time = $restrictionnodeid . '_' . $slot . '_' . $userpath->id;

        $task = new update_user_path();
        $task->set_userid($taskdata->user_id);
        $task->set_custom_data($taskdata);
        $task->set_next_run_time($boundary + self::RUNTIME_BUFFER);
        \core\task\manager::reschedule_or_queue_adhoc_task($task);
    }

    /**
     * Absolute close time of a timed_duration window (start reference + duration),
     * or null when the condition is incomplete.
     *
     * Mirrors the computation the conditions\timed_duration class uses so the
     * scheduled boundary matches what the user actually experiences.
     *
     * @param array $restrictionnode The timed_duration restriction node.
     * @param array $node The owning learning path node (carries first_enrolled).
     * @param int $timecreated The user-path creation time (start ref when not enrolment-based).
     * @return int|null
     */
    private static function timed_duration_close_time(array $restrictionnode, array $node, int $timecreated): ?int {
        $value = $restrictionnode['data']['value'] ?? [];
        if (!isset($value['selectedOption'])) {
            return null;
        }
        $durationvalue = $value['durationValue'] ?? null;
        $selectedduration = $value['selectedDuration'] ?? null;
        $map = timed_duration::DURATION_SECONDS;
        if (!isset($map[$durationvalue]) || !is_numeric($selectedduration)) {
            return null;
        }
        if ((string)$value['selectedOption'] === '1') {
            // Window starts when the learner is first enrolled into the node.
            $startref = $node['data']['first_enrolled'] ?? null;
            if (!is_numeric($startref)) {
                return null;
            }
            $startref = (int)$startref;
        } else {
            // Window starts at path creation.
            $startref = $timecreated;
        }
        return $startref + (int)($map[$durationvalue] * $selectedduration);
    }
}
