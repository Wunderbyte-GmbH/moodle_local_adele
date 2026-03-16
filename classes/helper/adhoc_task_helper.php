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

use DateTime;
use local_adele\task\update_user_path;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for scheduling adhoc tasks related to learning path updates.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_task_helper {

    /**
     * Convert a date string (without timezone info, e.g. "2026-03-16T18:00")
     * to a UTC timestamp, interpreting the date in Moodle's server timezone.
     * This ensures consistency with the timed.php condition class which uses
     * DateTime without explicit timezone (= server timezone).
     *
     * @param string $datestring The date string
     * @return int|false UTC timestamp or false on failure
     */
    private static function date_to_timestamp($datestring) {
        if (empty($datestring)) {
            return false;
        }
        try {
            $tz = \core_date::get_server_timezone_object();
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $datestring, $tz);
            if ($dt === false) {
                // Try alternative: let DateTime parse it.
                $dt = new DateTime($datestring, $tz);
            }
            return $dt->getTimestamp();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sets scheduled adhoc tasks for a learning path node.
     * Schedules tasks for start and end dates of timed restrictions,
     * so the learning path is re-evaluated when the time window
     * opens or closes.
     *
     * @param array $node The learning path node containing restriction data
     * @param stdClass $userpath The user path object containing learning_path_id and user_id
     * @return void
     */
    public static function set_scheduled_adhoc_tasks($node, $userpath) {
        if (isset($node['restriction'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                $dates = [];
                if (!empty($restrictionnode['data']['value']['start'])) {
                    $dates[] = $restrictionnode['data']['value']['start'];
                }
                if (!empty($restrictionnode['data']['value']['end'])) {
                    $dates[] = $restrictionnode['data']['value']['end'];
                }

                foreach ($dates as $date) {
                    $timestamp = self::date_to_timestamp($date);
                    if (!$timestamp || $timestamp <= time()) {
                        continue; // Date is in the past or invalid – skip.
                    }

                    // Schedule 2 minutes after the date to ensure the condition
                    // has changed state when the task runs.
                    $runtime = $timestamp + 120;

                    $taskdata = new stdClass();
                    $taskdata->learning_path_id = $userpath->learning_path_id;
                    $taskdata->user_id = $userpath->user_id;
                    $taskdata->userpath = $userpath;
                    $taskdata->time = $runtime . $userpath->id;

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
