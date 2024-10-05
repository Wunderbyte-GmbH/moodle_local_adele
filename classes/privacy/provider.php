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
    \core_privacy\local\request\plugin\provider
{
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
                'createdby' => 'privacy:metadata:local_adele_learning_paths:createdby',
                'json' => 'privacy:metadata:local_adele_learning_paths:json',
            ],
            'privacy:metadata:local_adele_learning_paths'
        );

        $collection->add_database_table(
            'local_adele_path_user',
            [
                'user_id' => 'privacy:metadata:local_adele_path_user:user_id',
                'createdby' => 'privacy:metadata:local_adele_learning_paths:createdby',
                'json' => 'privacy:metadata:local_adele_path_user:json',
            ],
            'privacy:metadata:local_adele_path_user'
        );

        $collection->add_database_table(
            'local_adele_lp_editors',
            [
                'userid' => 'privacy:metadata:local_adele_lp_editors:userid',
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

        // Add contexts for local_adele_learning_paths.
        $sql = "SELECT ctx.id
                  FROM {local_adele_learning_paths} lap
                  JOIN {context} ctx ON ctx.instanceid = lap.id
                 WHERE lap.createdby = :userid";
        $contextlist->add_from_sql($sql, ['userid' => $userid]);

        // Add contexts for local_adele_path_user.
        $sql = "SELECT ctx.id
                  FROM {local_adele_path_user} lpu
                  JOIN {context} ctx ON ctx.instanceid = lpu.id
                 WHERE lpu.user_id = :userid";
        $contextlist->add_from_sql($sql, ['userid' => $userid]);

        // Add contexts for local_adele_lp_editors.
        $sql = "SELECT ctx.id
                  FROM {local_adele_lp_editors} lpe
                  JOIN {context} ctx ON ctx.instanceid = lpe.id
                 WHERE lpe.userid = :userid";
        $contextlist->add_from_sql($sql, ['userid' => $userid]);

        return $contextlist;
    }

    /**
     * Export all user data for the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $contexts = $contextlist->get_contexts();

        foreach ($contexts as $context) {
            // Export data from local_adele_learning_paths
            $learningpaths = $DB->get_records('local_adele_learning_paths', ['createdby' => $userid]);
            foreach ($learningpaths as $path) {
                $data = (object) [
                    'name' => $path->name,
                    'description' => $path->description,
                    'json' => $path->json,
                ];
                writer::with_context($context)->export_data(['Learning Paths'], $data);
            }

            // Export data from local_adele_path_user
            $pathusers = $DB->get_records('local_adele_path_user', ['user_id' => $userid]);
            foreach ($pathusers as $pathuser) {
                $data = (object) [
                    'course_id' => $pathuser->course_id,
                    'status' => $pathuser->status,
                    'json' => $pathuser->json,
                ];
                writer::with_context($context)->export_data(['Path User Relations'], $data);
            }

            // Export data from local_adele_lp_editors
            $editors = $DB->get_records('local_adele_lp_editors', ['userid' => $userid]);
            foreach ($editors as $editor) {
                $data = (object) [
                    'learningpathid' => $editor->learningpathid,
                ];
                writer::with_context($context)->export_data(['Learning Path Editors'], $data);
            }
        }
    }
}
