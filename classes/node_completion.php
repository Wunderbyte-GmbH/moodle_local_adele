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

namespace local_adele;

use stdClass;

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
class node_completion {
    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function enrol_child_courses($event) {
        // Get the user path relation.
        global $DB;

        $userpath = $event->other['userpath']->json;
        if (is_string($userpath)) {
            $userpath = json_decode($userpath);
        }
        $firstenrollededit = false;
        foreach ($userpath->tree->nodes as $node) {
            if (in_array($node->id, $event->other['node']['childCourse'])) {
                foreach ($node->data->course_node_id as $subscribecourse) {
                    if (!enrol_is_enabled('manual')) {
                        break; // Manual enrolment not enabled.
                    }
                    if (!$enrol = enrol_get_plugin('manual')) {
                        break; // No manual enrolment plugin.
                    }
                    if (!$instances = $DB->get_records(
                            'enrol',
                            ['enrol' => 'manual', 'courseid' => $subscribecourse, 'status' => ENROL_INSTANCE_ENABLED],
                            'sortorder,id ASC'
                        )) {
                        break; // No manual enrolment instance on this course.
                    }
                    $instance = reset($instances); // Use the first manual enrolment plugin in the course.

                    if (!isset($node->data->first_enrolled)) {
                        $node->data->first_enrolled = time();
                        $firstenrollededit = true;
                    }
                    $enrol->enrol_user($instance, $event->other['userpath']->user_id, null);
                    self::enrol_user_group(
                      $userpath->tree->nodes,
                      $subscribecourse,
                      $event->other['userpath']->user_id
                    );
                }
            }
        }
        if ($firstenrollededit) {
            $data = [
                'id' => $event->other['userpath']->id,
                'json' => json_encode($userpath),
            ];
            $DB->update_record('local_adele_path_user', $data);
        }
    }

    /**
     * Observer for course completed
     *
     * @param array $nodes
     * @param int $newcourseid
     * @param int $userid
     */
    private static function enrol_user_group($nodes, $newcourseid, $userid) {
        // Get all groups from startingnode.
        $startinggroups = self::get_groups_for_multiple_courses($nodes);
        // Check if new course has this group.
        $currentgroups = groups_get_all_groups($newcourseid);
        $currentgroupnames = [];
        if (!empty($currentgroups)) {
            foreach ($currentgroups as $group) {
                $currentgroupnames[] = $group->name;
            }
        }
        // Create groups and add member.
        foreach ($startinggroups as $courseid => $groups) {
            foreach ($groups as $group) {
                self::check_groups_add_memebers(
                    $group,
                    $userid,
                    $currentgroupnames,
                    $newcourseid,
                    $currentgroups
                );
            }
        }
    }

    /**
     * Get all groups for multiple courses
     *
     * @param object $group
     * @param int $userid
     * @param array $currentgroupnames
     * @param int $newcourseid
     * @param array $currentgroups
     */
    private static function check_groups_add_memebers(
        $group,
        $userid,
        $currentgroupnames,
        $newcourseid,
        $currentgroups
    ) {
        // Check if the user was a member of the group in the starting node.
        if (groups_is_member($group->id, $userid)) {
            // Check if the group exists by name in the destination course.
            if (!in_array($group->name, $currentgroupnames)) {
                // Group does not exist, so create it.
                $newgroupdata = new stdClass();
                $newgroupdata->courseid = $newcourseid;
                $newgroupdata->name = $group->name;
                $newgroupdata->description = isset($group->description) ? $group->description : '';

                // Create the new group and add user.
                $newgroupid = groups_create_group($newgroupdata);
                groups_add_member($newgroupid, $userid);
            } else {
                // Group already exists, find the group id and add the user to it.
                $existinggroupid = array_search($group->name, array_column($currentgroups, 'name', 'id'));
                if ($existinggroupid) {
                    groups_add_member($existinggroupid, $userid);
                }
            }
        }
    }

    /**
     * Get all groups for multiple courses
     *
     * @param array $nodes Array of nodes
     * @return array Groups for course groups
     */
    private static function get_groups_for_multiple_courses($nodes) {
        $allgroups = [];
        foreach ($nodes as $node) {
            if (
              $node->parentCourse &&
              in_array('starting_node', $node->parentCourse)
            ) {
                $courseid = $node->data->course_node_id[0];
                $groups = groups_get_all_groups($courseid);
                if (!empty($groups)) {
                    $allgroups[$courseid] = $groups;
                }
            }
        }

        return $allgroups;
    }
}
