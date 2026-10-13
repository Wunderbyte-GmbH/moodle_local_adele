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
 * Event observers.
 *
 * @package local_adele
 * @copyright 2023 Georg Maißer <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\event\base;
use local_adele\completion;
use local_adele\enrollment;
use local_adele\learning_path_update;
use local_adele\node_completion;
use local_adele\relation_update;

/**
 * Event observer for local_adele.
 */
class local_adele_observer {
    /**
     * Observer for the core course_completed event.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event) {
        completion::completed($event);
    }

    /**
     * Observer for the update_catscale event
     *
     * @param base $event
     */
    public static function user_enrolment_created(base $event) {
        $observer = enrollment::enrolled($event);
    }

    /**
     * Observer for role_assigned: a new participant of a course becomes a
     * learner of the paths it embeds (#587).
     *
     * @param \core\event\role_assigned $event
     */
    public static function role_assigned_in_course(\core\event\role_assigned $event) {
        enrollment::role_assigned_in_course($event);
    }

    /**
     * Observer for the assign_assistant_to_role event
     *
     * @param \core\event\role_assigned $event
     */
    public static function assign_assistant_to_role(\core\event\role_assigned $event) {
        $observer = enrollment::assign_assistant_to_role($event);
    }

    /**
     * Observer for the update_catscale event
     *
     * @param base $event
     */
    public static function user_path_updated(base $event) {
        $observer = relation_update::updated_single($event);
    }

    /**
     * Observer for the update_catscale event
     *
     * @param base $event
     */
    public static function learnpath_updated(base $event) {
        $observer = learning_path_update::updated_learning_path($event);
    }


    /**
     * Observer for the user_views_learning_path
     *
     * @param base $event
     */
    public static function user_views_learning_path(base $event) {
        $observer = learning_path_update::user_views_learning_path($event);
    }

    /**
     * Observer for the user_views_learning_path
     *
     * @param base $event
     */
    public static function node_finished(base $event) {
        $observer = node_completion::enrol_child_courses($event);
    }

    /**
     * Observer for the mod_quiz attempt_submitted event.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_finished(\mod_quiz\event\attempt_submitted $event) {
        learning_path_update::quiz_finished($event);
    }

    /**
     * Observer for quiz attempt change events that can alter a completion result
     * after submission: manual grading, regrading, deletion and reopening (#498).
     *
     * The affected student is carried in relateduserid (the acting user is a
     * teacher/cron and must not be used). The quiz id is read from other['quizid']
     * and, if the event does not carry it, derived from the module context. The
     * recompute re-evaluates the full current state, so a completion can also be
     * revoked (e.g. after a deletion or a regrade below the threshold).
     *
     * @param \core\event\base $event
     */
    public static function quiz_attempt_changed(base $event) {
        $other = $event->other ?? [];
        $quizid = isset($other['quizid']) ? (int) $other['quizid'] : 0;
        if ($quizid <= 0 && (int) $event->contextlevel === CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('quiz', (int) $event->contextinstanceid, 0, false, IGNORE_MISSING);
            if ($cm) {
                $quizid = (int) $cm->instance;
            }
        }
        learning_path_update::recompute_quiz_paths((int) $event->relateduserid, $quizid);
    }

    /**
     * Observer for the update_catscale event
     *
     * @param base $event
     */
    public static function catquiz_attempt_finished(base $event) {
        $observer = learning_path_update::catquiz_finished($event);
    }

    /**
     * A user account was deleted: pass their learning paths to the next editor
     * and drop their editor memberships (#571).
     *
     * @param base $event
     */
    public static function user_deleted(base $event) {
        \local_adele\ownership::handle_user_deleted((int) $event->objectid);
    }
}
