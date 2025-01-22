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
 * Helper functions for user path relation.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\helper;

/**
 * The learnpath created event class.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_path_relation {

    /**
     * Get learning paths that contain course.
     *
     * @param int $userid
     * @param string $catquiz
     * @param string $modquiz
     * @return object
     *
     */
    public function get_learning_paths($userid, $catquiz = null, $modquiz = null) {
        global $DB;
        // Using named parameter :courseid in the SQL query.
        $sql = "SELECT *
            FROM {local_adele_path_user} lpu
            WHERE lpu.user_id = :user_id
            AND lpu.status = 'active'";

        $params = [
            'user_id' => (int)$userid,
        ];
        // Using get_records_sql function to execute the query with parameters.
        if ($catquiz !== null) {
            $sql .= " AND lpu.json LIKE :catquiz";
            $params['catquiz'] = '%' . $DB->sql_like_escape($catquiz) . '%';
        }

        // Extend the SQL query if modquiz is provided.
        if ($modquiz !== null) {
            $sql .= " AND lpu.json LIKE :modquiz";
            $params['modquiz'] = '%' . $DB->sql_like_escape($modquiz) . '%';
        }
        $records = $DB->get_records_sql($sql, $params);

        return $records;
    }

    /**
     * Get active user path relation.
     *
     * @param int $learningpathid
     * @return object
     *
     */
    public function get_user_path_relations($learningpathid) {
        global $DB;
        $sql = "SELECT *
            FROM {local_adele_path_user} lpu
            WHERE lpu.learning_path_id = :learningpathid
            AND lpu.status = 'active'";

        // Providing the named parameter in the $params array.
        $params = [
            'learningpathid' => (int)$learningpathid,
        ];
        // Using get_records_sql function to execute the query with parameters.
        $records = $DB->get_records_sql($sql, $params);
        return $records;
    }


    /**
     * Get active user path relation.
     *
     * @param int $learningpathid
     * @param int $userid
     * @param int $courseid
     * @return object
     *
     */
    public function get_user_path_relation($learningpathid, $userid, $courseid) {
        global $DB;

        $sql = "SELECT *
            FROM {local_adele_path_user} lpu
            WHERE lpu.learning_path_id = :learningpathid
            AND lpu.status = 'active'
            AND lpu.course_id = :courseid
            AND lpu.user_id = :userid";

        // Providing the named parameter in the $params array.
        $params = [
            'learningpathid' => (int)$learningpathid,
            'userid' => (int)$userid,
            'courseid' => (int)$courseid,
        ];
        // Using get_records_sql function to execute the query with parameters.
        $record = $DB->get_record_sql($sql, $params);
        return $record;
    }

    /**
     * Get active user path relation.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     *
     */
    public function get_active_user_path_relation($userid, $courseid) {
        global $DB;

        $sql = "SELECT *
            FROM {local_adele_path_user} lpu
            WHERE lpu.status = 'active'
            AND lpu.course_id = :courseid
            AND lpu.user_id = :userid";

        // Providing the named parameter in the $params array.
        $params = [
            'userid' => (int)$userid,
            'courseid' => (int)$courseid,
        ];
        // Using get_records_sql function to execute the query with parameters.
        $records = $DB->get_records_sql($sql, $params);
        return $records;
    }


    /**
     * Get active user path relation.
     *
     * @param object $userpath
     * @return object
     *
     */
    public function revision_user_path_relation($userpath) {
        global $DB;
        $data = [
            'id' => $userpath->id,
            'status' => 'revision',
            'timemodified' => time(),
        ];
        $DB->update_record('local_adele_path_user', $data);

        // Update nodes and save new user path relation.
        $userpath->json = json_encode($userpath->json, true);

        return $DB->insert_record('local_adele_path_user', [
            'user_id' => $userpath->user_id,
            'course_id' => $userpath->course_id,
            'learning_path_id' => $userpath->learning_path_id,
            'status' => 'active',
            'timecreated' => $userpath->timecreated,
            'timemodified' => time(),
            'createdby' => $userpath->createdby,
            'json' => $userpath->json,
        ]);
    }
}
