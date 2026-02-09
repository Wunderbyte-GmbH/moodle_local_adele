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
use local_adele\learning_paths;

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
class get_lp_user_path_relation extends external_api {
    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'learningpathid'  => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'userpathid'  => new external_value(PARAM_INT, 'userpathid', VALUE_REQUIRED),
            'contextid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            ]);
    }
    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $learningpathid
     * @param int $userpathid
     * @param int $contextid
     * @return array
     */
    public static function execute($learningpathid, $userpathid, $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'learningpathid' => $learningpathid,
            'userpathid' => $userpathid,
            'contextid' => $contextid,
        ]);
        require_login();
        $context = context::instance_by_id($contextid);
        require_capability('local/adele:view', $context);
        if ($context->contextlevel == CONTEXT_COURSE) {
            $params['courseid'] = $context->instanceid;
        } else {
            $coursecontext = $context->get_course_context();
            $params['courseid'] = $coursecontext->instanceid;
        }

        return learning_paths::get_learning_user_relation($params);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'ID'),
                    'user_id' => new external_value(PARAM_INT, 'Item id'),
                    'username' => new external_value(PARAM_TEXT, 'Username'),
                    'firstname' => new external_value(PARAM_TEXT, 'Firstname'),
                    'lastname' => new external_value(PARAM_TEXT, 'Lastname'),
                    'email' => new external_value(PARAM_RAW, 'email'),
                    'json' => new external_value(PARAM_RAW, 'Flow Chart'),
                    'last_seen_by_owner' => new external_value(PARAM_TEXT, 'Last seen'),
                    'image' => new external_value(PARAM_RAW, 'image'),
            ]);
    }
}
