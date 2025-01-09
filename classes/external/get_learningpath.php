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
use required_capability_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/adele/lib.php');


/**
 * External Service for local adele.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_learningpath extends external_api {

    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'learningpathid'  => new external_value(PARAM_INT, 'learningpathid', VALUE_REQUIRED),
            'contextid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $userid
     * @param int $learningpathid
     * @param int $contextid
     * @return array
     */
    public static function execute($userid, $learningpathid, $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'learningpathid' => $learningpathid,
            'contextid' => $contextid,
        ]);

        require_login();

        $context = context::instance_by_id($contextid);

        $learningpaths = learning_paths::return_learningpaths();

        if (
          !isset($learningpaths[$params['learningpathid']]) &&
          !has_capability('local/adele:edit', $context)
        ) {
            throw new required_capability_exception($context, 'local/adele:canmanage', 'nopermissions', 'error');
        }
        $learningpath = learning_paths::get_learning_path($params);
        if (isset($learningpath[0]) && $learningpath[0] == false) {
            return [
                'id' => $learningpathid,
                'name' => get_string('not_found', 'local_adele'),
                'description' => get_string('not_found', 'local_adele'),
                'image' => get_string('not_found', 'local_adele'),
                'json' => '',
            ];
        }
        return $learningpath;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Item id'),
            'name' => new external_value(PARAM_TEXT, 'Historyid id'),
            'description' => new external_value(PARAM_TEXT, 'Item name'),
            'image' => new external_value(PARAM_TEXT, 'Item image'),
            'json' => new external_value(PARAM_RAW, 'Additional JSON data'),
        ]
        );
    }
}
