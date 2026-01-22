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
 * GDPR Provider
 *
 *
 * @package    local_adele
 * @copyright  2024 Wunderbyte GmbH
 * @author     Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

namespace local_adele\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\transform;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\manager;

/**
 * Class provider
 *
 *
 * @package   local_adele
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about the data stored by this plugin.
     *
     * @param collection $collection The initialized collection to add items to.
     * @return collection The collection of metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_adele_learning_paths',
            [
                'name' => 'privacy:metadata:local_adele_learning_paths:name',
                'description' => 'privacy:metadata:local_adele_learning_paths:description',
                'createdby' => 'privacy:metadata:local_adele_learning_paths:createdby',
                'timecreated' => 'privacy:metadata:local_adele_learning_paths:timecreated',
                'timemodified' => 'privacy:metadata:local_adele_learning_paths:timemodified',
                'json' => 'privacy:metadata:local_adele_learning_paths:json',
            ],
            'privacy:metadata:local_adele_learning_paths'
        );

        $collection->add_database_table(
            'local_adele_path_user',
            [
                'user_id' => 'privacy:metadata:local_adele_path_user:user_id',
                'course_id' => 'privacy:metadata:local_adele_path_user:course_id',
                'learning_path_id' => 'privacy:metadata:local_adele_path_user:learning_path_id',
                'status' => 'privacy:metadata:local_adele_path_user:status',
                'createdby' => 'privacy:metadata:local_adele_path_user:createdby',
                'timecreated' => 'privacy:metadata:local_adele_path_user:timecreated',
                'timemodified' => 'privacy:metadata:local_adele_path_user:timemodified',
                'json' => 'privacy:metadata:local_adele_path_user:json',
            ],
            'privacy:metadata:local_adele_path_user'
        );

        $collection->add_database_table(
            'local_adele_lp_editors',
            [
                'userid' => 'privacy:metadata:local_adele_lp_editors:userid',
                'learningpathid' => 'privacy:metadata:local_adele_lp_editors:learningpathid',
            ],
            'privacy:metadata:local_adele_lp_editors'
        );

        return $collection;
    }

    /**
     * Returns all contexts that contain user information for the specified user.
     *
     * @param int $userid The user ID to search.
     * @return contextlist The list of contexts that contain user information.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // This plugin stores user data at the system context level.
        // Add system context if user has any data in any of the tables.
        $sql = "SELECT DISTINCT c.id
                  FROM {context} c
                 WHERE c.contextlevel = :contextlevel
                   AND (
                       EXISTS (SELECT 1 FROM {local_adele_learning_paths} lap WHERE lap.createdby = :userid1)
                       OR EXISTS (SELECT 1 FROM {local_adele_path_user} lpu WHERE lpu.user_id = :userid2 OR lpu.createdby = :userid3)
                       OR EXISTS (SELECT 1 FROM {local_adele_lp_editors} lpe WHERE lpe.userid = :userid4)
                   )";

        $params = [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }

        // Users who created learning paths.
        $sql = "SELECT DISTINCT createdby as userid
                  FROM {local_adele_learning_paths}
                 WHERE createdby > 0";
        $userlist->add_from_sql('userid', $sql, []);

        // Users in path_user table (both user_id and createdby).
        $sql = "SELECT DISTINCT user_id as userid
                  FROM {local_adele_path_user}
                 WHERE user_id > 0";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT DISTINCT createdby as userid
                  FROM {local_adele_path_user}
                 WHERE createdby > 0";
        $userlist->add_from_sql('userid', $sql, []);

        // Users who are editors.
        $sql = "SELECT DISTINCT userid
                  FROM {local_adele_lp_editors}
                 WHERE userid > 0";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export all user data for the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        // Check if system context is in the list.
        $systemcontext = \context_system::instance();
        if (!in_array($systemcontext->id, $contextlist->get_contextids())) {
            return;
        }

        // Export data from local_adele_learning_paths.
        $learningpaths = $DB->get_records('local_adele_learning_paths', ['createdby' => $userid]);
        if (!empty($learningpaths)) {
            $data = [];
            foreach ($learningpaths as $path) {
                $data[] = (object) [
                    'name' => $path->name,
                    'description' => $path->description,
                    'timecreated' => $path->timecreated ? transform::datetime($path->timecreated) : null,
                    'timemodified' => $path->timemodified ? transform::datetime($path->timemodified) : null,
                    'json' => $path->json,
                ];
            }
            writer::with_context($systemcontext)->export_data(['learning_paths'], (object) ['paths' => $data]);
        }

        // Export data from local_adele_path_user where user is the participant.
        $pathusers = $DB->get_records('local_adele_path_user', ['user_id' => $userid]);
        if (!empty($pathusers)) {
            $data = [];
            foreach ($pathusers as $pathuser) {
                $data[] = (object) [
                    'learning_path_id' => $pathuser->learning_path_id,
                    'course_id' => $pathuser->course_id,
                    'status' => $pathuser->status,
                    'timecreated' => $pathuser->timecreated ? transform::datetime($pathuser->timecreated) : null,
                    'timemodified' => $pathuser->timemodified ? transform::datetime($pathuser->timemodified) : null,
                    'json' => $pathuser->json,
                ];
            }
            writer::with_context($systemcontext)->export_data(['learning_path_assignments'], (object) ['assignments' => $data]);
        }

        // Export data from local_adele_path_user where user is the creator.
        $pathusers = $DB->get_records('local_adele_path_user', ['createdby' => $userid]);
        if (!empty($pathusers)) {
            $data = [];
            foreach ($pathusers as $pathuser) {
                $data[] = (object) [
                    'learning_path_id' => $pathuser->learning_path_id,
                    'user_id' => $pathuser->user_id,
                    'course_id' => $pathuser->course_id,
                    'status' => $pathuser->status,
                    'timecreated' => $pathuser->timecreated ? transform::datetime($pathuser->timecreated) : null,
                    'timemodified' => $pathuser->timemodified ? transform::datetime($pathuser->timemodified) : null,
                ];
            }
            writer::with_context($systemcontext)->export_data(
                ['learning_path_assignments_created'],
                (object) ['created_assignments' => $data]
            );
        }

        // Export data from local_adele_lp_editors.
        $editors = $DB->get_records('local_adele_lp_editors', ['userid' => $userid]);
        if (!empty($editors)) {
            $data = [];
            foreach ($editors as $editor) {
                $data[] = (object) [
                    'learningpathid' => $editor->learningpathid,
                ];
            }
            writer::with_context($systemcontext)->export_data(['learning_path_editor_permissions'], (object) ['permissions' => $data]);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        // Delete all records from all tables as this is system level data.
        $DB->delete_records('local_adele_learning_paths');
        $DB->delete_records('local_adele_path_user');
        $DB->delete_records('local_adele_lp_editors');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $systemcontext = \context_system::instance();

        if (!in_array($systemcontext->id, $contextlist->get_contextids())) {
            return;
        }

        // Delete learning paths created by the user.
        $DB->delete_records('local_adele_learning_paths', ['createdby' => $userid]);

        // Delete path_user records where user is the participant.
        $DB->delete_records('local_adele_path_user', ['user_id' => $userid]);

        // Delete path_user records created by the user.
        $DB->delete_records('local_adele_path_user', ['createdby' => $userid]);

        // Delete editor permissions for the user.
        $DB->delete_records('local_adele_lp_editors', ['userid' => $userid]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete learning paths created by these users.
        $DB->delete_records_select('local_adele_learning_paths', "createdby $insql", $inparams);

        // Delete path_user records where users are participants.
        $DB->delete_records_select('local_adele_path_user', "user_id $insql", $inparams);

        // Delete path_user records created by these users.
        $DB->delete_records_select('local_adele_path_user', "createdby $insql", $inparams);

        // Delete editor permissions for these users.
        $DB->delete_records_select('local_adele_lp_editors', "userid $insql", $inparams);
    }
}
