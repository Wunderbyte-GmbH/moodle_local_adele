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
class get_learningpaths extends external_api {
    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'learningpathid'  => new external_value(PARAM_INT, 'learningpathd', VALUE_REQUIRED),
            'contextid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            ]);
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
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'learningpathid' => $learningpathid,
            'contextid' => $contextid,
        ]);

        require_login();
        $context = context::instance_by_id($contextid);

        $learningpaths = learning_paths::return_learningpaths();
        $pathscreatedfornamedperson = learning_paths::return_learningpaths_owned();
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

        $role = $DB->get_record('role', ['shortname' => 'adelemanager']);
        $ismanager = user_has_role_assignment($userid, $role->id, $contextid);
        $isadmin = is_siteadmin($userid);

        $allpaths = learning_paths::get_learning_paths(
            true,
            $learningpaths
        );
        
        
        $lpkeys = array_keys($learningpaths);
        $lpownedkeys = array_keys($pathscreatedfornamedperson);

        if ($ismanager || $isadmin) {
            foreach ($allpaths as &$typepaths) {
                foreach ($typepaths as &$path) {
                    $path['isowner'] = "true";
                }
            }
            return $allpaths;
        }

        $filteredpaths = [
            'edit' => array_map(function ($lp) use ($lpkeys, $lpownedkeys) {
                if (in_array((int)$lp['id'], $lpkeys)) {
                    $lp['isowner'] = in_array((int)$lp['id'], $lpownedkeys) ? "true" : "false";
                    return $lp;
                }
            }, $allpaths['edit']),
            'view' => array_map(function ($lp) use ($lpkeys, $lpownedkeys) {
                if (in_array((int)$lp['id'], $lpkeys)) {
                    $lp['isowner'] = in_array((int)$lp['id'], $lpownedkeys) ? "true" : "false";
                    return $lp;
                }
            }, $allpaths['view']),
        ];

        $filteredpaths['edit'] = array_filter($filteredpaths['edit']);
        $filteredpaths['view'] = array_filter($filteredpaths['view']);

        return $filteredpaths;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'edit' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Item id'),
                    'name' => new external_value(PARAM_TEXT, 'Item name'),
                    'description' => new external_value(PARAM_TEXT, 'Item description'),
                    'image' => new external_value(PARAM_TEXT, 'Item image'),
                    'visibility' => new external_value(PARAM_TEXT, 'visibility'),
                    'isowner' => new external_value(PARAM_TEXT, 'canbedeleted'),
                ]),
                VALUE_OPTIONAL
            ),
            'view' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Item id'),
                    'name' => new external_value(PARAM_TEXT, 'Item name'),
                    'description' => new external_value(PARAM_TEXT, 'Item description'),
                    'image' => new external_value(PARAM_TEXT, 'Item image'),
                    'visibility' => new external_value(PARAM_TEXT, 'visibility'),
                    'isowner' => new external_value(PARAM_TEXT, 'canbedeleted'),
                ]),
                VALUE_OPTIONAL
            ),
        ]);
    }
}
