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

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use local_adele\learning_paths;
use moodle_exception;

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
            'learninggoalid'  => new external_value(PARAM_INT, 'learninggoalid', VALUE_REQUIRED),
            'name'  => new external_value(PARAM_TEXT, 'name', VALUE_REQUIRED),
            'description'  => new external_value(PARAM_TEXT, 'description', VALUE_REQUIRED),
            'json'  => new external_value(PARAM_RAW, 'json', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $userid
     * @param int $learninggoalid
     * @param string $name
     * @param string $description
     * @return bool
     */
    public static function execute($userid, $learninggoalid, $name, $description, $json): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'learninggoalid' => $learninggoalid,
            'name' => $name,
            'description' => $description,
            'json' => $json,
        ]);

        require_login();

        $context = context_system::instance();
        if (!has_capability('local/adele:canmanage', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_adele');
        }

        return ['success' => learning_paths::save_learning_path($params)];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_INT, '1 for success', VALUE_REQUIRED),
            ]
        );
    }
}
