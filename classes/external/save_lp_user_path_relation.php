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
class save_lp_user_path_relation extends external_api {
    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'learningpathid'  => new external_value(PARAM_INT, 'learningpathid', VALUE_REQUIRED),
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'params'  => new external_value(PARAM_RAW, 'params', VALUE_REQUIRED),
            'contextid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            ]);
    }

    /**
     * Webservice for the local adele plugin to get next question.
     *
     * @param int $learningpathid
     * @param int $userid
     * @param array $params
     * @param int $contextid
     * @return array
     */
    public static function execute($learningpathid, $userid, $params, $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'learningpathid' => $learningpathid,
            'userid' => $userid,
            'params' => $params,
            'contextid' => $contextid,
        ]);

        require_login();

        $context = context::instance_by_id($contextid);
        // See the identical comment in
        // get_lp_user_path_relation.php — canmanage/teacheredit is
        // system-/course-role-based, not course-enrolment-based; a manager
        // acting on a course they are not enrolled in is the normal case.
        // Managers keep full access; teachers may set a user's node status
        // (manual completion / master toggle) via teacheredit, without canmanage (#431).
        // The context is client input: a right that comes from a course counts
        // only in a course that EMBEDS this path. It used to count in any course,
        // so a teacher of an unrelated course could complete nodes for the
        // learners of every path - and thereby open their courses.
        // The path that is actually written is named INSIDE the payload
        // (route.learningpathId); the check below must be about that path.
        $payload = json_decode($params['params']);
        if (
            !is_object($payload)
            || (int) ($payload->route->learningpathId ?? 0) !== (int) $params['learningpathid']
        ) {
            throw new \invalid_parameter_exception('The payload does not belong to the learning path given.');
        }
        $coursecontext = $context->get_course_context(false);
        $params['courseid'] = $coursecontext ? (int) $coursecontext->instanceid : 0;
        if (!is_siteadmin() && !has_capability('local/adele:canmanage', \context_system::instance())) {
            if (
                !$coursecontext
                || !learning_paths::is_embedded_in_course((int) $params['learningpathid'], (int) $params['courseid'])
            ) {
                throw new \required_capability_exception($context, 'local/adele:teacheredit', 'nopermissions', '');
            }
            if (!has_capability('local/adele:canmanage', $context)) {
                require_capability('local/adele:teacheredit', $context);
            }
        }
        return learning_paths::save_learning_user_relation($params);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
                'success' => new external_value(PARAM_BOOL, 'Item id'),
            ]);
    }
}
