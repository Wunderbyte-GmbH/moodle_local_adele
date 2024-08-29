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
 * Entities Class to display list of entity records.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use local_adele\event\learnpath_created;
use local_adele\event\learnpath_updated;
use stdClass;
use context_system;
use context_course;
use local_adele\event\learnpath_deleted;
use local_adele\event\user_path_updated;
use local_adele\helper\user_path_relation;
use core_completion\progress;
use Exception;
use moodle_url;

/**
 * Class learning_paths
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_path_editors {

    /**
     * Entities constructor.
     */
    public function __construct() {

    }

    /**
     * Get editors of user path.
     *
     * @param int $lpid
     * @return array
     */
    public static function get_editors($lpid) {
        global $DB;
        $sql = "
            SELECT u.id, u.email, u.firstname, u.lastname
            FROM {local_adele_lp_editors} e
            LEFT JOIN {user} u ON e.userid = u.id
            WHERE e.learningpathid = :lpid
        ";

        $editors = $DB->get_records_sql($sql, ['lpid' => $lpid]);
        $result = [];
        foreach ($editors as $editor) {
            $result[] = [
                'id' => $editor->id,
                'email' => $editor->email,
                'firstname' => $editor->firstname,
                'lastname' => $editor->lastname,
            ];
        }
        return $result;
    }

    /**
     * Create a new editor for a field.
     *
     * @param int $lpid
     * @param int $userid
     * @return array
     */
    public static function create_editors($lpid, $userid) {
        global $DB;
        $data = new stdClass();
        $data->learningpathid = $lpid;
        $data->userid = $userid;
        $result = $DB->insert_record('local_adele_lp_editors', $data);
        return ['success' => $result];
    }

    /**
     * Create a new editor for a field.
     *
     * @param int $lpid
     * @param int $userid
     * @return array
     */
    public static function remove_editors($lpid, $userid) {
        global $DB;
        $DB->delete_records('local_adele_lp_editors', [
            'learningpathid' => $lpid,
            'userid' => $userid
          ]
        );
        return ['success' => true];
    }
}
