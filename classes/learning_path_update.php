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

use local_adele\event\user_path_updated;
use local_adele\helper\user_path_relation;
use context_system;
use core_completion\progress;
use mod_adele_observer;
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
class learning_path_update {
    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function updated_learning_path($event) {
        // Get the user path relations.
        $userpathrelation = new user_path_relation();
        $records = $userpathrelation->get_user_path_relations($event->other['learningpathid']);
        foreach ($records as $userpath) {
            $userpath->json = self::passnodevalues($event->other['json'], $userpath->json, $userpath->user_id);
            $eventsingle = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
                ],
            ]);
            $eventsingle->trigger();
        }
    }

    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function user_views_learning_path($event) {

        $userpathrelation = new user_path_relation();
        $eventdata = $event->get_data();
        $records = $userpathrelation->get_active_user_path_relation($eventdata['userid'], $eventdata['courseid']);
        foreach ($records as $userpath) {
            self::trigger_user_path_update($userpath);
        }
    }

    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function trigger_user_path_update($userpath) {
        $eventsingle = user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => context_system::instance(),
            'other' => [
                'userpath' => $userpath,
            ],
        ]);
        $eventsingle->trigger();
    }

    /**
     * Finished quiz.
     *
     * @param object $event
     */
    public static function quiz_finished($event) {
        // Get the user path relations.
        $userpathrelation = new user_path_relation();
        $records = $userpathrelation->get_learning_paths(
          $event->userid,
          null,
          '"quizid":"' . $event->other['quizid'] . '"'
        );
        foreach ($records as $userpath) {
            $userpath->json = json_decode($userpath->json, true);
            $eventsingle = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
                ],
            ]);
            $eventsingle->trigger();
        }
    }

    /**
     * Finished quiz.
     *
     * @param object $event
     */
    public static function catquiz_finished($event) {
        // Get the user path relations.
        $cm = get_coursemodule_from_id(null, $event->contextinstanceid);
        $userpathrelation = new user_path_relation();
        $records = $userpathrelation->get_learning_paths(
          $event->userid,
          '"componentid":"' . $cm->instance . '"'
        );
        foreach ($records as $userpath) {
            $userpath->json = json_decode($userpath->json, true);
            $eventsingle = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
                ],
            ]);
            $eventsingle->trigger();
        }
    }

    /**
     * Observer for course completed
     *
     * @param string $newtree
     * @param string $oldtree
     * @param string $userid
     * @return array
     */
    public static function passnodevalues($newtree, $oldtree, $userid) {
        $oldpath = json_decode($oldtree, true);
        $userpathjson = json_decode($newtree, true);
        $userpathjson['user_path_relation'] = $oldpath['user_path_relation'];
        $oldvalues = [];

        foreach ($oldpath['tree']['nodes'] as $node) {
            $oldvalues[$node['id']] = [
                'firstcompleted' => $node['firstcompleted'] ?? false,
                'manualcompletion' => $node['data']['manualcompletion'],
                'manualcompletionvalue' => $node['data']['manualcompletionvalue'],
                'manualrestriction' => $node['data']['manualrestriction'],
                'manualrestrictionvalue' => $node['data']['manualrestrictionvalue'],
            ];
        }

        foreach ($userpathjson['tree']['nodes'] as &$node) {
            if (isset($oldvalues[$node['id']]) && $oldvalues[$node['id']] == true ) {
                $node['firstcompleted'] = true;
            }
            $manualrestriction = false;
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if ( $restrictionnode['data']['label'] == 'manual' ) {
                    $manualrestriction = true;
                }
            }
            $manualcompletion = false;
            foreach ($node['completion']['nodes'] as $completionnode) {
                if ( $completionnode['data']['label'] == 'manual' ) {
                    $manualcompletion = true;
                }
            }
            if ($oldvalues[$node['id']]) {
                if ($manualrestriction) {
                    $node['data']['manualrestriction'] = $oldvalues[$node['id']]['manualrestriction'];
                    $node['data']['manualrestrictionvalue'] = $oldvalues[$node['id']]['manualrestrictionvalue'];
                }
                if ($manualcompletion) {
                    $node['data']['manualcompletion'] = $oldvalues[$node['id']]['manualcompletion'];
                    $node['data']['manualcompletionvalue'] = $oldvalues[$node['id']]['manualcompletionvalue'];
                }
            }
            $node = self::checknodeprogression($node, $userid);
        }
        return $userpathjson;
    }

    /**
     * Get user path relation.
     *
     * @param object $node
     * @param String $userid
     * @return array
     */
    public static function checknodeprogression($node, $userid) {
        $progress = 0;
        foreach ($node['data']['course_node_id'] as $coursenodeid) {
            $course = get_course($coursenodeid);
            $tmpprogress = (int) progress::get_course_progress_percentage($course, $userid);
            if ($tmpprogress > $progress) {
                $progress = $tmpprogress;
            }
        }
        $node['data']['progress'] = $progress;
        return $node;
    }

    /**
     * Trigger user path subscription
     *
     * @param string $learningpathid
     */
    public static function trigger_user_subscription($learningpathid) {
        global $DB, $USER;
        $adeleactivities = $DB->get_records('adele', ['learningpathid' => $learningpathid]);
        $data = (object) [
          'courseid' => 0,
          'userid' => $USER->id,
        ];
        foreach ($adeleactivities as $adeleactivity) {
            $adeleactivity->participantslist = explode(',', $adeleactivity->participantslist);
            $data->courseid = $adeleactivity->course;
            foreach ($adeleactivity->participantslist as $participantslist) {
                if ($participantslist == '1') {
                    mod_adele_observer::enroll_all_participants($adeleactivity, $data, true);
                } else if ($participantslist == '2') {
                    mod_adele_observer::enroll_starting_nodes_participants($adeleactivity, $data, true);
                }
            }
        }
    }

    /**
     * Trigger user path subscription
     *
     * @param string $lpid
     * @param bool $visibility
     */
    public static function update_visiblity($lpid, $visibility) {
        global $DB;
        $data = new stdClass();
        $data->id = $lpid;
        $data->visibility = $visibility;
        $result = $DB->update_record('local_adele_learning_paths', $data);
        return ['success' => $result];
    }

    /**
     * Observer for course completed
     *
     * @param string $learningpathid
     * @param string $userid
     * @param string $nodeid
     * @param string $animations
     * @return array
     */
    public static function update_animations(
        $learningpathid,
        $userid,
        $nodeid,
        $animations
    ) {
        global $DB;
        $record = $DB->get_record(
          'local_adele_path_user',
          [
            'user_id' => $userid,
            'learning_path_id' => $learningpathid,
            'status' => 'active',
          ],
          'id, json',
        );
        $animations = (array) json_decode($animations);
        $json = json_decode($record->json);
        foreach ($json->tree->nodes as $node) {
            if ($node->id == $nodeid) {
                $node->data->animations = $animations;
                break;
            }
        }
        $DB->update_record(
          'local_adele_path_user',
          ['id' => $record->id, 'json' => json_encode($json)]
        );
        return ['success' => true];
    }

}
