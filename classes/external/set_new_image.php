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
use local_adele\asset_handler;

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
class set_new_image extends external_api {

    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'learningpathid' => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'image' => new external_value(PARAM_RAW, 'file data', VALUE_REQUIRED),
        ]);
    }

    /**
     * Webservice for the local adele plugin to get next question.
     *
     * @param int $contextid
     * @param int $learningpathid
     * @param mixed $image
     * @return array
     */
    public static function execute($contextid, $learningpathid, $image): array {
        require_login();

        $context = context::instance_by_id($contextid);
        require_capability('local/adele:canmanage', $context);

        return asset_handler::set_new_image($contextid, $learningpathid, $image);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
                'status' => new external_value(PARAM_TEXT, 'Image path'),
                'filename' => new external_value(PARAM_TEXT, 'Image path'),
        ]);
    }
}
