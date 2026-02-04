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
 * Adhoc Task to remove expired items from the shopping cart.
 *
 * @package    local_adele
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\task;

use core\message\message;
use local_adele\helper\user_path_relation;
use local_adele\learning_path_update;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Adhoc Task to remove expired items from the shopping cart.
 *
 * @package    local_adele
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
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
     * Execution function.
     *
     * {@inheritdoc}
     * @throws \coding_exception
     * @throws \dml_exception
     * @see \core\task\task_base::execute()
     */
    public function execute() {

        $taskdata = $this->get_custom_data();

        $helper = new user_path_relation();
        $currentuserpath = $helper->get_user_path_relation($taskdata->learning_path_id, $taskdata->user_id, $taskdata->course_id);
        try {
            $currentuserpath->json = json_decode($currentuserpath->json, true);
            learning_path_update::trigger_user_path_update($currentuserpath);
        } catch (\Exception $e) {
            return true;
        }
    }
}
