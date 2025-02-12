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

/**
 * Class learning_paths
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modquiz {

    /**
     * Entities constructor.
     */
    public function __construct() {

    }

    /**
     * Get all tests.
     *
     * @return array
     */
    public static function get_mod_quizzes($availablecourses) {
        global $DB;
        $availablecourseids = array_map(function ($course) {
            return $course['course_node_id'][0];
        }, $availablecourses);

        if (empty($availablecourseids)) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($availablecourseids, SQL_PARAMS_QM);

        $sql = "SELECT q.id, q.course, q.name, c.fullname as coursename
                FROM {quiz} q
                LEFT JOIN {course} c ON c.id = q.course
                WHERE q.course $insql";

        $records = $DB->get_records_sql($sql, $params);

        return array_map(fn($a) => (array)$a, $records);
    }
}
