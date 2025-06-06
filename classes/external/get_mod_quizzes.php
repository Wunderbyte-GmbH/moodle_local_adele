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

namespace local_adele\external;

use context;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use local_adele\modquiz;

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
class get_mod_quizzes extends external_api {

    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'availablecourses' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'course_node_id' => new external_multiple_structure(
                            new external_value(PARAM_TEXT, 'Course node IDs')
                        ),
                        'fullname' => new external_value(PARAM_TEXT, 'Full name of the course'),
                        'shortname' => new external_value(PARAM_TEXT, 'Short name of the course'),
                        'category' => new external_value(PARAM_INT, 'Category ID of the course'),
                        'summary' => new external_value(PARAM_RAW, 'Summary of the course', VALUE_OPTIONAL),
                        'tags' => new external_value(PARAM_TEXT, 'Tags for the course', VALUE_OPTIONAL, null),
                        'selected_course_image' => new external_value(PARAM_URL, 'Selected course image URL', VALUE_OPTIONAL, null),
                    ]
                )
            ),
        ]);
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $contextid
     * @param array $availablecourses
     * @return array
     */
    public static function execute($contextid, $availablecourses): array {

        require_login();

        $context = context::instance_by_id($contextid);
        require_capability('local/adele:canmanage', $context);

        return modquiz::get_mod_quizzes($availablecourses);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                    'id' => new external_value(PARAM_TEXT, 'id'),
                    'course' => new external_value(PARAM_TEXT, 'courseid'),
                    'name' => new external_value(PARAM_TEXT, 'name'),
                    'coursename' => new external_value(PARAM_TEXT, 'coursename'),
                ]
            )
        );
    }
}
