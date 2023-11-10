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
 * Class learninggoals.
 *
 * @package     local_adele
 * @copyright   2019 Luca Bösch <luca.boesch@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use external_single_structure;

/**
 * Class learninggoals
 *
 * @package     local_adele
 * @copyright   2019 Luca Bösch <luca.boesch@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class availablecourses extends external_api {
    /**
     * Definition of parameters for {@see get_learninggoals()}.
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_availablecourses_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'userid'),
            'learninggoalid' => new external_value(PARAM_INT, 'learninggoalid'),
        ]);
    }

    /**
     * Definition of parameters for {@see get_learninggoals()}.
     * Returns description of method result value.
     *
     * @return external_multiple_structure
     */
    public static function get_availablecourses_returns() {
        return new external_multiple_structure(
            exporter\learninggoal::get_read_structure()
        );
    }

    /**
     * Get all learning goals.
     *
     * @param int $userid
     * @param int $learninggoalid
     * @return array
     * @throws \invalid_parameter_exception
     */
    public static function get_availablecourses($userid, $learninggoalid) {
        global $USER;
        $params = self::validate_parameters(self::get_availablecourses_parameters(),
            array(
                'userid' => $userid,
                'learninggoalid' => $learninggoalid
            )
        );

        $userid = $USER->id;

        self::validate_context(\context_system::instance());

        global $PAGE, $DB;
        $renderer = $PAGE->get_renderer('core');

        $ctx = \context_system::instance();

        $sql = "SELECT id, fullname, shortname FROM {course} LIMIT 40";

        $learninggoals = $DB->get_records_sql($sql);

        return $learninggoals;
    }
}
