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
 * Adhoc task to update all user paths after a learning path is updated.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\task;

use context_system;
use local_adele\event\user_path_updated;
use local_adele\helper\user_path_relation;
use local_adele\learning_path_update;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task that processes all enrolled users after a learning path is updated.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_lp_users extends \core\task\adhoc_task {
    /**
     * Get name of task.
     *
     * @return \lang_string|string
     */
    public function get_name() {
        return get_string('pluginname', 'local_adele');
    }

    /**
     * Execute the task: update all user paths for the given learning path.
     */
    public function execute() {
        global $DB;

        $taskdata = $this->get_custom_data();
        $learningpathid = $taskdata->learningpathid;

        $lp = $DB->get_record('local_adele_learning_paths', ['id' => $learningpathid], 'json');
        if (!$lp) {
            return;
        }

        $userpathrelation = new user_path_relation();
        $records = $userpathrelation->get_user_path_relations($learningpathid);

        foreach ($records as $userpath) {
            try {
                $userpath->json = learning_path_update::passnodevalues($lp->json, $userpath->json, $userpath->user_id);
                $eventsingle = user_path_updated::create([
                    'objectid' => $userpath->id,
                    'context' => context_system::instance(),
                    'other' => [
                        'userpath' => $userpath,
                    ],
                ]);
                $eventsingle->trigger();
            } catch (\Exception $e) {
                debugging(
                    'update_lp_users: failed for user ' . $userpath->user_id . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
    }
}
