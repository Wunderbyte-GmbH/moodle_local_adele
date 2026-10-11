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
        self::validate_context($context);

        $learningpaths = learning_paths::return_learningpaths();
        $pathscreatedfornamedperson = learning_paths::return_learningpaths_owned();
        $sessionvalue = learning_paths::check_access();
        // Listing learning paths requires general editor access; the set is filtered
        // to the user's own paths below for non-managers (clean denial, #458). A course
        // teacher (local/adele:teacheredit on the activity's context) may also load it to
        // drive the in-course teacher view; the filter below scopes the result to their
        // own (zero) editor paths, so no other path leaks. Editing the path structure
        // stays gated on check_access()/canmanage elsewhere (save_learningpath etc.).
        if (empty($sessionvalue) && !learning_paths::can_view_participants($context)) {
            throw new required_capability_exception($context, 'local/adele:canmanage', 'nopermissions', '');
        }

        $role = $DB->get_record('role', ['shortname' => 'adelemanager']);
        $ismanager = user_has_role_assignment($userid, $role->id, $contextid);
        $isadmin = is_siteadmin($userid);

        $allpaths = learning_paths::get_learning_paths(
            true,
            $learningpaths
        );

        // Scope the master set to what this user may see (assistants see all visible
        // paths + their own, #472), then annotate each tile with its owner + editors
        // (#487). Both live in learning_paths so they are unit-testable without the
        // external-api isolated-process requirement.
        $scoped = learning_paths::scope_paths_for_user(
            $allpaths,
            array_keys($learningpaths),
            array_keys($pathscreatedfornamedperson),
            has_capability('local/adele:assist', $context),
            ($ismanager || $isadmin)
        );
        learning_paths::add_path_people($scoped);

        return $scoped;
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
                    'ownerless' => new external_value(PARAM_BOOL, 'owner vanished, no successor (#571)', VALUE_OPTIONAL),
                    'owner' => new external_single_structure([
                        'name' => new external_value(PARAM_TEXT, 'Owner (creator) full name'),
                        'email' => new external_value(PARAM_TEXT, 'Owner (creator) email'),
                    ]),
                    'editors' => new external_multiple_structure(
                        new external_single_structure([
                            'name' => new external_value(PARAM_TEXT, 'Editor full name'),
                        ])
                    ),
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
                    'ownerless' => new external_value(PARAM_BOOL, 'owner vanished, no successor (#571)', VALUE_OPTIONAL),
                    'owner' => new external_single_structure([
                        'name' => new external_value(PARAM_TEXT, 'Owner (creator) full name'),
                        'email' => new external_value(PARAM_TEXT, 'Owner (creator) email'),
                    ]),
                    'editors' => new external_multiple_structure(
                        new external_single_structure([
                            'name' => new external_value(PARAM_TEXT, 'Editor full name'),
                        ])
                    ),
                ]),
                VALUE_OPTIONAL
            ),
        ]);
    }
}
