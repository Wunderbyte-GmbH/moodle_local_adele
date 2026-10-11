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
class get_lp_user_path_relations extends external_api {
    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
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
        global $USER;
        require_login();

        $context = context::instance_by_id($contextid);
        // See the identical comment in
        // get_lp_user_path_relation.php (singular) — same reasoning.
        require_capability('local/adele:view', $context);

        // The context is client input. Its course matters only as far as the
        // path is embedded there (see below); a context outside any course
        // (system, user) carries no course rights at all.
        $coursecontext = $context->get_course_context(false);
        $params['courseid'] = $coursecontext ? (int) $coursecontext->instanceid : 0;

        // Local/adele:view is held by every authenticated user, so it is not a
        // boundary. This leaderboard returns the whole participant roster (names +
        // progress) of the path. Who may read it:
        // - managers and the editors of THIS path (has_path_authority()):
        // their authority is about the path, not about a course. Being
        // allowed to edit some other path (check_access()) is not enough;
        // - teachers of a course (local/adele:viewparticipants) and its enrolled
        // participants (StudentView.vue) - but only of a course that EMBEDS
        // this path. The check used to accept any course: a participant or
        // teacher of an unrelated course read the roster of every path by
        // passing the context of their own course, and a learner whose
        // activity says 'Only own results' got the full roster through the
        // context of a node course ADELE itself had enrolled them in.
        $embedded = $coursecontext
            && learning_paths::is_embedded_in_course((int) $params['learningpathid'], (int) $params['courseid']);
        $isprivileged = learning_paths::has_path_authority((int) $params['learningpathid'])
            || ($embedded && learning_paths::can_view_participants($coursecontext));
        if (!$isprivileged && !($embedded && is_enrolled($coursecontext, $USER->id))) {
            throw new \required_capability_exception(
                $coursecontext ?: $context,
                'local/adele:teacheredit',
                'nopermissions',
                ''
            );
        }

        // Asked for through a course that embeds the path, the roster is that
        // course's: its enrolled learners of the path, ranked among themselves
        // (#588). Path authority outside such a course gets the path's roster.
        if ($embedded) {
            $params['scopecourseid'] = (int) $params['courseid'];
        }
        $relations = learning_paths::get_learning_user_relations($params);

        // The mod_adele setting 'Participant results' = 'Only own results' must
        // hold HERE, not only in the client: the UserList.vue filter still
        // shipped every participant's name, progress and rank to any enrolled
        // student's browser (#569). Teachers and path editors keep the full
        // roster; filtering after ranking keeps the caller's true rank.
        if (!$isprivileged && isset($relations[0]) && self::only_own_results($context, $params)) {
            $ownid = (int) $USER->id;
            $relations = array_values(array_filter(
                $relations,
                static function ($relation) use ($ownid) {
                    return (int) $relation['id'] === $ownid;
                }
            ));
        }

        return $relations;
    }

    /**
     * Whether the calling context demands the 'Only own results' filter (#569).
     *
     * Called from the mod_adele activity that shows this path (module context)
     * the instance's own userlist setting decides; for every other call the
     * strictest embedding of this path in the course wins.
     *
     * @param context $context The context the SPA was booted with.
     * @param array $params Validated parameters (learningpathid, courseid).
     * @return bool
     */
    protected static function only_own_results(context $context, array $params): bool {
        global $DB;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('adele', $context->instanceid);
            if ($cm) {
                // Only the activity that shows THIS path speaks for it: another
                // ADELE activity of the course, set to 'all results' for a
                // different path, must not lift the restriction.
                $instance = $DB->get_record('adele', ['id' => $cm->instance], 'id, learningpathid, userlist');
                if ($instance && (int) $instance->learningpathid === (int) $params['learningpathid']) {
                    return (int) $instance->userlist == 2;
                }
            }
        }
        return $DB->record_exists('adele', [
            'course' => $params['courseid'],
            'learningpathid' => $params['learningpathid'],
            'userlist' => 2,
        ]);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Item id'),
                    'username' => new external_value(PARAM_TEXT, 'Username'),
                    'firstname' => new external_value(PARAM_TEXT, 'Firstname'),
                    'lastname' => new external_value(PARAM_TEXT, 'Lastname'),
                    'progress' => new external_single_structure([
                        'completed_nodes' => new external_value(PARAM_TEXT, 'completed nodes'),
                        'progress' => new external_value(PARAM_FLOAT, 'progress'),
                        'route_completed' => new external_value(PARAM_INT, 'completed nodes on the best route'),
                        'route_total' => new external_value(PARAM_INT, 'length of the best route'),
                        'total_nodes' => new external_value(PARAM_INT, 'total nodes of the path'),
                    ]),
                    'rank' => new external_value(PARAM_INT, 'Ranking'),
                ])
        );
    }
}
