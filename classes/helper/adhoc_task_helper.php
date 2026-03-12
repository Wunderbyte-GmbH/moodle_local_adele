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
 * Helper functions for adhoc tasks
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\helper;
use local_adele\learning_path_update;
use local_adele\task\update_user_path;
use stdClass;

/**
 * The learnpath created event class.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_task_helper {
    /**
     * Sets scheduled adhoc tasks for a learning path node.
     *
     * @param array $node The learning path node containing restriction data
     * @param stdClass $userpath The user path object containing learning_path_id, user_id, and course_id
     * @return void
     */
    public static function set_scheduled_adhoc_tasks($node, $userpath) {

        if (isset($node['restriction'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                // Iterate over named date slots so the dedup key can encode the
                // slot identity (node + start|end + userpath) rather than the
                // date value itself.  If we encoded the date value, every date
                // change would yield a different customdata hash, causing
                // reschedule_or_queue_adhoc_task to insert a new row and leave
                // the old stale task in the queue instead of updating it.
                foreach (['start', 'end'] as $datetype) {
                    $date = $restrictionnode['data']['value'][$datetype] ?? '';
                    if (empty($date)) {
                        continue;
                    }
                    $timestamp = strtotime($date);
                    // No point scheduling a task for a date that has already passed.
                    // Access for past-dated restrictions is granted synchronously by
                    // updated_single() (via reschedule_timed_restrictions_for_all_nodes),
                    // which re-evaluates every node whenever the learning path or user
                    // path changes. Scheduling an immediate task here would create a
                    // perpetual loop: the task fires → updated_single() runs →
                    // set_scheduled_adhoc_tasks() sees past date → schedules another
                    // immediate task → repeat every 60 seconds indefinitely.
                    if ($timestamp <= time()) {
                        continue;
                    }
                    $taskdata = new stdClass();
                    $taskdata->learning_path_id = $userpath->learning_path_id;
                    $taskdata->user_id = $userpath->user_id;
                    $taskdata->course_id = $userpath->course_id;
                    $taskdata->userpath_id = $userpath->id;
                    // Stable slot-based dedup key: identifies this task by the
                    // restriction-node id + date type (start/end) + userpath id.
                    // This value does NOT change when the admin edits the date,
                    // so reschedule_or_queue_adhoc_task reliably finds and updates
                    // the existing row (adjusting nextruntime) rather than inserting
                    // a duplicate. Do NOT include the runtime/timestamp here.
                    $taskdata->time = $restrictionnode['id'] . '_' . $datetype . '_' . $userpath->id;
                    $runtime = $timestamp + 120;
                    $updateuserpathtask = new update_user_path();
                    $updateuserpathtask->set_userid($taskdata->user_id);
                    $updateuserpathtask->set_custom_data($taskdata);
                    $updateuserpathtask->set_next_run_time($runtime);
                    \core\task\manager::reschedule_or_queue_adhoc_task($updateuserpathtask);
                }
            }
        }
    }
}
