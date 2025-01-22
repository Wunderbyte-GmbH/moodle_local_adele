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
     * Schedules a task to update user paths.
     *
     * @param mixed $node Data needed to schedule the task.
     */
    public static function set_scheduled_adhoc_tasks($node, $userpath) {

        if (isset($node['restriction'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                $dates = [];
                if (isset($restrictionnode['data']['value']['start'])) {
                    $dates[] = $restrictionnode['data']['value']['start'];
                }
                if (isset($restrictionnode['data']['value']['end'])) {
                    $dates[] = $restrictionnode['data']['value']['end'];
                }

                foreach ($dates as $date) {
                    $taskdata = new stdClass();
                    $taskdata->learning_path_id = $userpath->learning_path_id;
                    $taskdata->user_id = $userpath->user_id;
                    $taskdata->course_id = $userpath->course_id;
                    $taskdata->userpath = $userpath;
                    $timestamp = strtotime($date);
                    $runtime = strtotime('+2 minutes', $timestamp);
                    $taskdata->time = $runtime . $userpath->id;
                    $updateuserpathtask = new update_user_path();
                            // Set details for the task.
                    $updateuserpathtask->set_userid($taskdata->userid);
                    $updateuserpathtask->set_custom_data($taskdata);
                    $updateuserpathtask->set_next_run_time($runtime);
                    \core\task\manager::reschedule_or_queue_adhoc_task($updateuserpathtask);
                }
            }
        }
    }


}
