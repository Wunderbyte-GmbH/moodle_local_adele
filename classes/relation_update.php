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

use local_adele\course_completion\course_completion_status;
use local_adele\course_restriction\course_restriction_status;
use local_adele\helper\user_path_relation;
use local_adele\event\node_finished;
use context_system;

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
class relation_update {
    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function updated_single($event) {
        // Get the user path relation.
        $userpath = $event->other['userpath'];
        if ($userpath) {
            self::subscribe_user_starting_node($userpath);
            foreach ($userpath->json['tree']['nodes'] as $node) {
                $completioncriteria = course_completion_status::get_condition_status($node, $userpath->user_id);
                $restrictioncriteria = course_restriction_status::get_restriction_status($node, $userpath);
                $restrictionnodepaths = [];
                $singlerestrictionnode = [];
                if (isset($node['restriction'])) {
                    foreach ($node['restriction']['nodes'] as $restrictionnodepath) {
                        $failedrestriction = false;
                        $validationconditionstring = [];
                        if ($restrictionnodepath['parentCondition'][0] == 'starting_condition') {
                            $currentcondition = $restrictionnodepath;
                            $validationcondition = false;
                            while ( $currentcondition ) {
                                if ($currentcondition['data']['label'] == 'timed' ||
                                    $currentcondition['data']['label'] == 'timed_duration' ||
                                    $currentcondition['data']['label'] == 'specific_course' ||
                                    $currentcondition['data']['label'] == 'parent_courses') {
                                    $validationcondition =
                                        $restrictioncriteria[$currentcondition['data']['label']][$currentcondition['id']];
                                    $singlerestrictionnode[$currentcondition['data']['label']
                                        . '_' . $currentcondition['id']] = $validationcondition;
                                    $validationconditionstring[] = $currentcondition['data']['label']
                                        . '_' . $currentcondition['id'];
                                } else if ($currentcondition['data']['label'] == 'parent_node_completed') {
                                    foreach ($restrictioncriteria[$currentcondition['data']['label']] as $keynode => $parentnode) {
                                        $parentcompletioncriteria = course_completion_status::get_condition_status(
                                          $parentnode,
                                          $userpath->user_id
                                        );
                                        $parentnode = self::validatenodecompletion(
                                            $parentnode,
                                            $parentcompletioncriteria,
                                            $userpath,
                                            $restrictionnodepaths,
                                            0
                                        );
                                        if ($parentnode) {
                                            $validationcondition = true;
                                        }
                                    }
                                    $singlerestrictionnode[$currentcondition['data']['label']] = $validationcondition;
                                    $validationconditionstring[] = $currentcondition['data']['label'];
                                } else {
                                    $validationcondition = $restrictioncriteria[$currentcondition['data']['label']];
                                    $singlerestrictionnode[$currentcondition['data']['label']] = $validationcondition;
                                    $validationconditionstring[] = $currentcondition['data']['label'];
                                }
                                // Check if the conditon is true and break if one condition is not met.
                                if (!$validationcondition) {
                                    $failedrestriction = true;
                                }
                                // Get next Condition and return null if no child node exsists.
                                $currentcondition = self::searchnestedarray($node['restriction']['nodes'],
                                    $currentcondition['childCondition'], 'id');
                            }
                            if ($validationcondition && !$failedrestriction) {
                                $restrictionnodepaths[] = $validationconditionstring;
                            }
                        }
                    }
                }
                if (isset($node['completion'])) {
                    $validatenodecompletion = self::validatenodecompletion(
                        $node,
                        $completioncriteria,
                        $userpath,
                        $restrictionnodepaths,
                        1
                    );
                }
                $completionnode = self::getconditionnode($validatenodecompletion['completionnodepaths'], 'completion');
                $restrictionnode = self::getconditionnode($restrictionnodepaths, 'restriction');
                $userpath->json['user_path_relation'][$node['id']] = [
                    'completioncriteria' => $completioncriteria,
                    'completionnode' => $completionnode,
                    'singlecompletionnode' => $validatenodecompletion['singlecompletionnode'],
                    'restrictioncriteria' => $restrictioncriteria,
                    'restrictionnode' => $restrictionnode,
                    'singlerestrictionnode' => $singlerestrictionnode,
                    'feedback' => $validatenodecompletion['feedback'],
                ];
            }
            $userpathrelationhelper = new user_path_relation();
            $userpathrelationhelper->revision_user_path_relation($userpath);
        }
    }

    /**
     * Observer for course completed
     *
     * @param  Array $node
     * @param  Array $completioncriteria
     * @param  Object $userpath
     * @param  Array $restrictionnodepaths
     * @param  Number $mode
     * @return array
     */
    public static function validatenodecompletion($node, $completioncriteria, $userpath, $restrictionnodepaths, $mode) {
        $completionnodepaths = [];
        $singlecompletionnode = [];
        $feedback = self::getfeedback($node);
        foreach ($node['completion']['nodes'] as $completionnode) {
            $failedcompletion = false;
            $validationconditionstring = [];
            if (isset($completionnode['parentCondition']) &&
                $completionnode['parentCondition'][0] == 'starting_condition') {
                $currentcondition = $completionnode;
                $validationcondition = false;
                while ( $currentcondition ) {
                    $label = $currentcondition['data']['label'];
                    if ($label == 'catquiz' ||
                    $label == 'modquiz') {
                        $validationcondition =
                            $completioncriteria[$label][$currentcondition['id']];
                        $singlecompletionnode[$label
                            . '_' . $currentcondition['id']] = $validationcondition;
                        $validationconditionstring[] = $label
                            . '_' . $currentcondition['id'];
                    } else if ($label == 'course_completed') {
                        $completednodecourses = 0;
                        foreach ($completioncriteria[$label] as $coursecompleted) {
                            if ($coursecompleted) {
                                $completednodecourses += 1;
                                if (!isset($completionnode['data']['value']) || $completionnode['data']['value'] == null) {
                                    $validationcondition = true;
                                    $validationconditionstring[] = $label;
                                }
                            }
                        }
                        if (
                          isset($completionnode['data']) &&
                          isset($completionnode['data']['value']) &&
                          isset($completionnode['data']['value']['min_courses']) &&
                          $completionnode['data']['value']['min_courses'] <= $completednodecourses
                        ) {
                            $validationcondition = true;
                            $validationconditionstring[] = $label;
                        }
                        $singlecompletionnode[$label] = $validationcondition;
                    } else {
                        if (!$mode) {
                            $completioncriteria = course_completion_status::get_condition_status($node, $userpath->user_id);
                        }
                        $validationcondition = $completioncriteria[$label];
                        $singlecompletionnode[$label] = $validationcondition;
                        $validationconditionstring[] = $label;
                    }
                    // Check if the conditon is true and break if one condition is not met.
                    if (!$validationcondition) {
                        $failedcompletion = true;
                    }
                    // Get next Condition and return null if no child node exsists.
                    $currentcondition = self::searchnestedarray($node['completion']['nodes'],
                        $currentcondition['childCondition'], 'id');
                }
                if ($validationcondition && !$failedcompletion ) {
                    if (!$mode) {
                        return true;
                    } else if ($node['restriction'] == null ||count($restrictionnodepaths) ||
                    !count($node['restriction']['nodes'])) {
                        $completionnodepaths[] = $validationconditionstring;
                        $feedback['completion']['after'][] = $feedback['completion']['after_all'][$completionnode['id']];
                        $nodefinished = node_finished::create([
                            'objectid' => $userpath->id,
                            'context' => context_system::instance(),
                            'other' => [
                                'node' => $node,
                                'userpath' => $userpath,
                            ],
                        ]);
                        $nodefinished->trigger();
                    }
                }
            }
        }
        if (!$mode) {
            return false;
        }
        return [
            'completionnodepaths' => $completionnodepaths,
            'singlecompletionnode' => $singlecompletionnode,
            'feedback' => $feedback,
        ];
    }

    /**
     * Observer for course completed
     *
     * @param array $conditionnodepaths
     * @param string $type
     * @return array
     */
    public static function getconditionnode($conditionnodepaths, $type) {
        // TODO sort the valid completion paths.
        $valid = count($conditionnodepaths) ? true : false;
        $priority = 0;
        if ($valid) {
            if ($type == 'completion') {
                $completionpriorities = course_completion_status::get_condition_priority();
                foreach ($conditionnodepaths as $conditionnodepath) {
                    foreach ($conditionnodepath as $condition) {
                        if (
                          $priority == 0 ||
                          $completionpriorities[$condition] < $priority) {
                            $priority = $completionpriorities[$condition];
                        }
                    }
                }
            }
        }
        return [
            'valid' => $valid,
            'priority' => $priority,
            'conditions' => $conditionnodepaths,
        ];
    }

    /**
     * Observer for course completed
     *
     * @param array $conditionnodes
     * @return array
     */
    public static function getfeedback($node) {
        $feedbacks = [
          'completion' => [
            'before' => null,
            'after_all' => null,
            'after' => null,
          ],
          'restriction' => [
            'before' => null,
          ],
        ];
        foreach ($node['completion']['nodes'] as $conditionnode) {
            if (strpos($conditionnode['id'], '_feedback') !== false && $conditionnode['data']['visibility']) {
                $feedbacks['completion']['before'][] =
                  isset($conditionnode['data']['feedback_before']) ? $conditionnode['data']['feedback_before'] : '';
                $feedbacks['completion']['after_all'][str_replace('_feedback', '', $conditionnode['id'])] =
                  isset($conditionnode['data']['feedback_after']) ? $conditionnode['data']['feedback_after'] : '';
            }
        }
        foreach ($node['restriction']['nodes'] as $restrictionnode) {
            if (strpos($restrictionnode['id'], '_feedback') !== false && $restrictionnode['data']['visibility']) {
                $feedbacks['restriction']['before'][] =
                  isset($restrictionnode['data']['feedback_before']) ? $restrictionnode['data']['feedback_before'] : '';
            }
        }
        return $feedbacks;
    }

    /**
     * Observer for course completed
     *
     * @param array $haystack
     * @param array $needle
     * @param string $key
     * @return array
     */
    public static function searchnestedarray($haystack, $needle, $key) {
        foreach ($haystack as $item) {
            foreach ($needle as $need) {
                if ( !strpos($need, '_feedback' )) {
                    if (isset($item[$key]) && $item[$key] === $need) {
                        return $item;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Subscribe to starting nodes
     *
     * @param object $userpath
     */
    public static function subscribe_user_starting_node($userpath) {
        global $DB;
        $firstenrollededit = false;
        foreach ($userpath->json['tree']['nodes'] as &$node) {
            if (in_array('starting_node', $node['parentCourse'])) {
                foreach ($node['data']['course_node_id'] as $courseid) {
                    if (!enrol_is_enabled('manual')) {
                        break; // Manual enrolment not enabled.
                    }
                    if (!$enrol = enrol_get_plugin('manual')) {
                        break; // No manual enrolment plugin.
                    }
                    if (!$instances = $DB->get_records(
                            'enrol',
                            ['enrol' => 'manual', 'courseid' => $courseid, 'status' => ENROL_INSTANCE_ENABLED],
                            'sortorder,id ASC'
                        )) {
                        break; // No manual enrolment instance on this course.
                    }
                    if (!isset($node['data']['first_enrolled'])) {
                        $node['data']['first_enrolled'] = time();
                        $firstenrollededit = true;
                    }
                    $instance = reset($instances); // Use the first manual enrolment plugin in the course.
                    $enrol->enrol_user($instance, $userpath->user_id);
                }
            }
        }
        if ($firstenrollededit) {
            $data = [
                'id' => $userpath->id,
                'json' => json_encode($userpath->json),
            ];
            $DB->update_record('local_adele_path_user', $data);
        }
    }
}
