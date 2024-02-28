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
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
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
class update_learningpath extends external_api {

    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'json'  => new external_value(PARAM_RAW, 'json', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $id
     * @param string $json
     * @return bool
     */
    public static function execute($id, $json): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'json' => $json,
        ]);

        require_login();

        $context = context_system::instance();
        if (!has_capability('local/adele:canmanage', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_adele');
        }

        return ['status' => learning_paths::update_learning_path($params)];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Condition label'),
                ]
        );
    }
}
