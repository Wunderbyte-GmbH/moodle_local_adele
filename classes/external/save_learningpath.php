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
use local_adele\learning_path_editors;
use local_adele\learning_paths;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local catquiz.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_learningpath extends external_api {

    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'learningpathid'  => new external_value(PARAM_INT, 'learningpathid', VALUE_REQUIRED),
            'name'  => new external_value(PARAM_TEXT, 'name', VALUE_REQUIRED),
            'description'  => new external_value(PARAM_TEXT, 'description', VALUE_REQUIRED),
            'json'  => new external_value(PARAM_RAW, 'json', VALUE_REQUIRED),
            'contextid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'image'  => new external_value(PARAM_TEXT, 'image', VALUE_DEFAULT, ''),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $userid
     * @param int $learningpathid
     * @param string $name
     * @param string $description
     * @param string $json
     * @param int $contextid
     * @param string|null $image Optional image parameter.
     * @return bool
     */
    public static function execute(
        $userid,
        $learningpathid,
        $name,
        $description,
        $json,
        $contextid,
        $image = null
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'learningpathid' => $learningpathid,
            'name' => $name,
            'description' => $description,
            'image' => $image,
            'json' => $json,
            'contextid' => $contextid,
        ]);

        require_login();
        $context = context::instance_by_id($contextid);
        $sessionvalue = learning_paths::check_access();
        // If the user doesn't have the capability and the session value is empty, handle the error.
        if (empty($sessionvalue)) {
            throw new required_capability_exception(
                $context,
                'local/adele:canmanage',
                'nopermission',
                'You do not have the required capability and the session key is not set.'
            );
        }

        $result = learning_paths::save_learning_path($params);
        if (!learning_path_editors::get_editors($result->id)) {
            learning_path_editors::create_editors($result->id, $userid);
        }
        return ['learningpath' => $result];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'learningpath' => new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Condition description'),
                    'name' => new external_value(PARAM_TEXT, 'Condition description'),
                    'description' => new external_value(PARAM_TEXT, 'Condition description'),
                    'timecreated' => new external_value(PARAM_TEXT, 'Condition label'),
                    'timemodified' => new external_value(PARAM_TEXT, 'Condition label'),
                    'createdby' => new external_value(PARAM_TEXT, 'Condition label'),
                    'json' => new external_value(PARAM_RAW, 'Condition label'),
                ]
            ),
            ]
        );
    }
}
