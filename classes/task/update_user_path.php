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
 * Adhoc Task to update user learning path when timed restrictions change.
 *
 * @package    local_adele
 * @copyright  2026 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\task;

use local_adele\helper\user_path_relation;
use local_adele\learning_path_update;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc Task to re-evaluate a user's learning path when a timed
 * restriction window opens or closes.
 *
 * @package    local_adele
 * @copyright  2026 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_user_path extends \core\task\adhoc_task {
    /**
     * Get name of Module.
     *
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('pluginname', 'local_adele');
    }

    /**
     * Execution function. Loads the current user path from the database
     * and triggers a full re-evaluation of all restrictions and completions.
     * This will fire user_path_updated → updated_single() → node_finished
     * → enrol_child_courses() if any nodes have become accessible.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function execute() {
        $taskdata = $this->get_custom_data();

        if (empty($taskdata->learning_path_id) || empty($taskdata->user_id)) {
            mtrace('ADELE update_user_path: Missing learning_path_id or user_id in task data.');
            return;
        }

        $helper = new user_path_relation();
        $currentuserpath = $helper->get_user_path_relation($taskdata->learning_path_id, $taskdata->user_id);

        if (!$currentuserpath) {
            mtrace('ADELE update_user_path: No active user path found for '
                . 'learning_path_id=' . $taskdata->learning_path_id
                . ' user_id=' . $taskdata->user_id);
            return;
        }

        try {
            $currentuserpath->json = json_decode($currentuserpath->json, true);
            learning_path_update::trigger_user_path_update($currentuserpath);
            mtrace('ADELE update_user_path: Successfully triggered update for '
                . 'learning_path_id=' . $taskdata->learning_path_id
                . ' user_id=' . $taskdata->user_id);
        } catch (\Exception $e) {
            mtrace('ADELE update_user_path: Exception during update: ' . $e->getMessage());
        }
    }
}
