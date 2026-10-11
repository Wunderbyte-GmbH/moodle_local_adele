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
        global $USER;
        require_login();
        $context = context::instance_by_id($contextid);
        // The validate_context() call is removed again —
        // real CI run showed it broke a legitimate case. For CONTEXT_COURSE,
        // validate_context() effectively requires course ENROLMENT
        // (require_login($course, ...) internally), but a learning-path
        // editor's authority here comes from local_adele_lp_editors/
        // canmanage/check_access() (path-level), not from being enrolled in
        // whichever course this context happens to be. A manager or
        // path-editor reading data scoped to a course they do not teach is
        // the normal case here, not an edge case — the actual security
        // boundary is the capability/ownership check a few lines below,
        // which validate_context() does not add to.
        require_capability('local/adele:view', $context);
        // The context is client input; outside any course it carries no
        // course rights.
        $coursecontext = $context->get_course_context(false);
        $params['courseid'] = $coursecontext ? (int) $coursecontext->instanceid : 0;

        // Local/adele:view is granted to the `user` archetype, so every
        // authenticated user holds it - it is NOT a boundary. This getter returns the
        // requested user's email plus their full progress snapshot, so reading a path
        // that is not your own must be restricted to a manager or an editor of THIS
        // path (has_path_authority() - being allowed to edit some other path is not
        // enough) or a course teacher (:viewparticipants, resolved up the module/course context);
        // otherwise any student could read any other student's progress + email by id
        // (IDOR). The teacher's right counts only in a course that EMBEDS this path:
        // it used to count in any course, so a teacher of an unrelated course read
        // every learner of every path by passing the context of their own course.
        // The owner viewing their own path is always allowed (StudentView.vue
        // passes userId == own).
        if (
            (int)$params['userpathid'] !== (int)$USER->id
            && !learning_paths::has_path_authority((int) $params['learningpathid'])
        ) {
            // ... and it covers the learners of that course, not the ones
            // another course brought to the same path (#588).
            $teacherhere = $coursecontext
                && learning_paths::is_embedded_in_course((int) $params['learningpathid'], (int) $params['courseid'])
                && learning_paths::can_view_participants($context)
                && is_enrolled($coursecontext, (int) $params['userpathid']);
            if (!$teacherhere) {
                throw new \required_capability_exception(
                    $context,
                    'local/adele:teacheredit',
                    'nopermissions',
                    ''
                );
            }
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
                    'lp_deleted' => new external_value(
                        PARAM_BOOL,
                        'True when the master learning path has been deleted but this user snapshot is kept',
                        VALUE_OPTIONAL
                    ),
            ]);
    }
}
