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
 * This class contains a list of webservice functions related to the adele Module by Wunderbyte.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_adele;

use local_adele\event\user_path_updated;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local adele.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment {
    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param object $event
     */
    public static function enrolled($event) {
        $params = $event;
        $learningpaths = self::buildsqlquerypath($params->courseid);
        if ($learningpaths) {
            foreach ($learningpaths as $learningpath) {
                self::subscribe_user_to_learning_path($learningpath, $params);
            }
        }
    }

    /**
     * Build sql query with config filters.
     *
     * @param object $learningpath
     * @param object $params
     * @return array
     */
    public static function subscribe_user_to_learning_path($learningpath, $params) {
        global $DB;
        if ($learningpath) {
            if (is_string($learningpath->json)) {
                $learningpath->json = json_decode($learningpath->json, true);
            }
            $userpath = self::buildsqlqueryuserpath($learningpath->id, $params->relateduserid);
            if (!$userpath) {
                $id = $DB->insert_record('local_adele_path_user', [
                    'user_id' => $params->relateduserid,
                    'learning_path_id' => $learningpath->id,
                    'status' => 'active',
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'createdby' => $params->userid,
                    'json' => json_encode([
                        'tree' => $learningpath->json['tree'],
                        'modules' => $learningpath->json['modules'] ?? null,
                    ]),
                ]);
                $userpath = $DB->get_record('local_adele_path_user', ['id' => $id]);
            }
            $userpath->json = json_decode($userpath->json, true);
            $eventsingle = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
                ],
            ]);
            $eventsingle->trigger();
        }
    }

    /**
     * Build sql query with config filters.
     *
     * @param string $courseid
     * @return array
     */
    public static function buildsqlquerypath($courseid) {
        global $DB;
        // Using named parameter :courseid in the SQL query.
        $likecourseid = $DB->sql_like('lp.json', ':courseidpattern');
        $sql = "SELECT lp.id, lp.json
        FROM {local_adele_learning_paths} lp
        WHERE {$likecourseid}";

        // Providing the named parameter in the $params array.
        $params = ['courseidpattern' => '%course_node_id____' . $courseid . '__,%'];

        // Using get_records_sql function to execute the query with parameters.
        $records = $DB->get_records_sql($sql, $params);

        return $records;
    }

    /**
     * Build sql query with config filters.
     *
     * @param int $learningpathid
     * @param int $userid
     * @return array
     */
    public static function buildsqlqueryuserpath($learningpathid, $userid) {
        global $DB;
        // Using named parameter :courseid in the SQL query.
        $sql = "SELECT *
        FROM {local_adele_path_user} lpu
        WHERE lpu.learning_path_id = :learningpathid
        AND lpu.status = 'active'
        AND lpu.user_id = :userid
        ORDER BY lpu.id DESC";

        // Providing the named parameter in the $params array.
        $params = [
            'learningpathid' => (int)$learningpathid,
            'userid' => (int)$userid,
        ];

        // Using get_records_sql function to execute the query with parameters.
        $record = $DB->get_record_sql($sql, $params);

        return $record;
    }
}
