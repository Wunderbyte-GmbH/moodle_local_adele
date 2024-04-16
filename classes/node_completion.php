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
        $userpath = json_decode($event->other['userpath']->json);
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
                    $enrol->enrol_user($instance, $event->other['userpath']->user_id);
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
}
