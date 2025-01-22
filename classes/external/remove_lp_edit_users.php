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
class remove_lp_edit_users extends external_api {

    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'lpid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'userid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $contextid
     * @param int $lpid
     * @param int $userid
     * @return array
     */
    public static function execute($contextid, $lpid, $userid): array {
        require_login();
        $context = context::instance_by_id($contextid);
        $hascapability = has_capability('local/adele:canmanage', $context);

        $sessionvalue = learning_paths::check_access();

        // If the user doesn't have the capability and the session value is empty, handle the error.
        if (!$hascapability && empty($sessionvalue)) {
            throw new required_capability_exception(
              $context,
              'local/adele:canmanage',
              'nopermission',
              'You do not have the required capability to delete an editor and the session key is not set.'
            );
        }

        return learning_path_editors::remove_editors($lpid, $userid);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
                    'success' => new external_value(PARAM_INT, 'Condition description'),
                ]
        );
    }
}
