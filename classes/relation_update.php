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
            if (!empty($userpath->json['tree']['nodes'])) {
                foreach ($userpath->json['tree']['nodes'] as $node) {
                    $completioncriteria = course_completion_status::get_condition_status($node, $userpath->user_id);
                    $restrictioncriteria = course_restriction_status::get_restriction_status($node, $userpath);
                    $restrictionnodepaths = [];
                    $singlerestrictionnode = [];
                    if (isset($node['restriction'])) {
                        foreach ($node['restriction']['nodes'] as $restrictionnodepath) {
                            $failedrestriction = false;
                            $validationconditionstring = [];
                            if (
                                isset($restrictionnodepath['parentCondition']) &&
                                $restrictionnodepath['parentCondition'][0] == 'starting_condition'
                            ) {
                                $currentcondition = $restrictionnodepath;
                                $validationcondition = false;
                                while ($currentcondition) {
                                    $currlabel = $currentcondition['data']['label'];
                                    if (
                                        $currentcondition['data']['label'] == 'timed' ||
                                        $currentcondition['data']['label'] == 'timed_duration' ||
                                        $currentcondition['data']['label'] == 'specific_course' ||
                                        $currentcondition['data']['label'] == 'parent_courses'
                                    ) {
                                        $currcondi = $currentcondition['id'];
                                        $validationcondition =
                                            $restrictioncriteria[$currlabel]['completed'][$currcondi] ?? false;
                                        $singlerestrictionnode[$currentcondition['data']['label']
                                            . '_' . $currentcondition['id']] = $validationcondition;
                                        $validationconditionstring[] = $currentcondition['data']['label']
                                            . '_' . $currentcondition['id'];
                                    } else if (
                                        isset($restrictioncriteria[$currlabel]['completed']) &&
                                        $currentcondition['data']['label'] == 'parent_node_completed'
                                    ) {
                                        foreach ($restrictioncriteria[$currlabel]['completed'] as $keynode => $parentnode) {
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
                                        $validationcondition =
                                          $restrictioncriteria[$currentcondition['data']['label']]['completed'] ?? false;
                                        $singlerestrictionnode[$currentcondition['data']['label']] = $validationcondition;
                                        $validationconditionstring[] = $currentcondition['data']['label'];
                                    }
                                    // Check if the conditon is true and break if one condition is not met.
                                    if (!$validationcondition) {
                                        $failedrestriction = true;
                                    }
                                    // Get next Condition and return null if no child node exsists.
                                    $currentcondition = self::searchnestedarray(
                                        $node['restriction']['nodes'],
                                        $currentcondition['childCondition'],
                                        'id'
                                    );
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
                    $getoldcompletion =
                      self::checkcondition($completionnode, $userpath->json, $node['id'], 'completionnode');
                    $getoldrestriction =
                      self::checkcondition($restrictionnode, $userpath->json, $node['id'], 'restrictionnode');
                    if (!$getoldrestriction) {
                        $userpath->json['user_path_relation'][$node['id']]['restrictioncriteria'] = $restrictioncriteria;
                        $userpath->json['user_path_relation'][$node['id']]['restrictionnode'] = $restrictionnode;
                        $userpath->json['user_path_relation'][$node['id']]['singlerestrictionnode'] = $singlerestrictionnode;
                    }
                    if (!$getoldcompletion) {
                        $userpath->json['user_path_relation'][$node['id']]['completioncriteria'] = $completioncriteria;
                        $userpath->json['user_path_relation'][$node['id']]['completionnode'] = $completionnode;
                        $userpath->json['user_path_relation'][$node['id']]['singlecompletionnode'] =
                          $validatenodecompletion['singlecompletionnode'];
                        $userpath->json['user_path_relation'][$node['id']]['feedback'] = $validatenodecompletion['feedback'];
                    }
                }
                $userpathrelationhelper = new user_path_relation();
                $userpathrelationhelper->revision_user_path_relation($userpath);
            }
        }
    }

    /**
     * Observer for course completed
     *
     * @param  Array $newcompletion
     * @param  Array $oldcompletion
     * @param  int $nodeid
     * @param  int $condition
     * @return bool
     */
    public static function checkcondition($newcompletion, $oldcompletion, $nodeid, $condition) {
        if (
            isset($oldcompletion['user_path_relation'][$nodeid][$condition]) &&
            !$newcompletion['valid'] &&
            $oldcompletion['user_path_relation'][$nodeid][$condition]['valid']
        ) {
            return true;
        }
        return false;
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
        $feedback = self::getfeedback($node, $completioncriteria);
        foreach ($node['completion']['nodes'] as $completionnode) {
            $failedcompletion = false;
            $validationconditionstring = [];
            if (
                isset($completionnode['parentCondition']) &&
                $completionnode['parentCondition'][0] == 'starting_condition'
            ) {
                $currentcondition = $completionnode;
                $validationcondition = false;
                while ($currentcondition) {
                    $label = $currentcondition['data']['label'];
                    if (
                        $label == 'catquiz' ||
                        $label == 'modquiz'
                    ) {
                        $validationcondition =
                            $completioncriteria[$label]['completed'][$currentcondition['id']];
                        $singlecompletionnode[$label
                            . '_' . $currentcondition['id']] = $validationcondition;
                        $validationconditionstring[] = $label
                            . '_' . $currentcondition['id'];
                    } else if ($label == 'course_completed') {
                        $completednodecourses = 0;
                        if (isset($completioncriteria[$label])) {
                            foreach ($completioncriteria[$label]['completed'] as $coursecompleted) {
                                if ($coursecompleted) {
                                    $completednodecourses += 1;
                                    if (!isset($completionnode['data']['value']) || $completionnode['data']['value'] == null) {
                                        $validationcondition = true;
                                        $validationconditionstring[] = $label;
                                    }
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
                        $validationcondition = $completioncriteria[$label]['completed'] ?? false;
                        $singlecompletionnode[$label] = $validationcondition;
                        $validationconditionstring[] = $label;
                    }
                    // Check if the conditon is true and break if one condition is not met.
                    if (!$validationcondition) {
                        $failedcompletion = true;
                    }
                    // Get next Condition and return null if no child node exsists.
                    $currentcondition = self::searchnestedarray(
                        $node['completion']['nodes'],
                        $currentcondition['childCondition'],
                        'id'
                    );
                }
                if ($validationcondition && !$failedcompletion) {
                    if (!$mode) {
                        return true;
                    } else if (
                        $node['restriction'] == null ||count($restrictionnodepaths) ||
                        !count($node['restriction']['nodes'])
                    ) {
                        $completionnodepaths[] = $validationconditionstring;
                        $feedback['completion']['after'][] = $feedback['completion']['after_all'][$completionnode['id']]['text'];
                        if (
                            !$priority ||
                            $priority > $feedback['completion']['after_all'][$completionnode['id']]['priority']
                        ) {
                            $priority = $feedback['completion']['after_all'][$completionnode['id']]['priority'];
                        }
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
        $feedback['completion']['higher'] = [];
        if ($priority) {
            foreach ($feedback['completion']['after_all'] as $completionpriority) {
                if ($completionpriority['priority'] < $priority) {
                    $feedback['completion']['higher'][] = $completionpriority['text'];
                }
            }
        }
        unset($feedback['completion']['after_all']);
        $feedback['status'] = self::getnodestatus(
            $feedback,
            $restrictionnodepaths,
            $node['restriction']
        );

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
     * Return node status for display purpose.
     *
     * @param array $feedback
     * @param array $restrictionnodepaths
     * @param array $restrictions
     * @return string
     */
    public static function getnodestatus($feedback, $restrictionnodepaths, $restrictions) {
        if ($feedback['completion']['after']) {
            return 'completed';
        }
        if ($restrictionnodepaths) {
            return 'accessible';
        }
        foreach ($restrictions['nodes'] as $restrictionall) {
            if (str_contains($restrictionall['id'], '_feedback')) {
                $hastimedcondition = false;
                $nextid = str_replace('_feedback', '', $restrictionall['id']);
                while ($nextid) {
                    foreach ($restrictions as $restrictioncolumn) {
                        if ($restrictioncolumn['id'] == $nextid) {
                            if (str_contains($restrictioncolumn['data']['label'], 'timed')) {
                                $hastimedcondition = true;
                            }
                            $newnextid = null;
                            foreach ($restrictioncolumn['childCondition'] as $children) {
                                if (!str_contains($children, '_feedback')) {
                                    $newnextid = $children;
                                }
                            }
                            $nextid = $newnextid;
                        }
                    }
                }
                if (!$hastimedcondition) {
                    return 'not_accessible';
                }
            }
        }
        return 'not_accessible';
      // return 'closed'
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
                            $completionpriorities[$condition] < $priority
                        ) {
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
     * @param array $node
     * @param array $completioncriteria
     * @return array
     */
    public static function getfeedback($node, $completioncriteria) {
        $feedbacks = [
          'completion' => [
            'before' => null,
            'after_all' => null,
            'after' => null,
            'inbetween' => null,
          ],
          'restriction' => [
            'before' => null,
          ],
        ];
        foreach ($node['completion']['nodes'] as $conditionnode) {
            if (strpos($conditionnode['id'], '_feedback') !== false && $conditionnode['data']['visibility']) {
                $feedbacks['completion']['before'][] =
                  isset($conditionnode['data']['feedback_before']) ? $conditionnode['data']['feedback_before'] : '';

                $feedbacks['completion']['after_all'][str_replace('_feedback', '', $conditionnode['id'])] = [
                    'priority' => $conditionnode['data']['feedback_priority'] ?? 3,
                    'text' => isset($conditionnode['data']['feedback_after']) ? $conditionnode['data']['feedback_after'] : '',
                ];

                if ($conditionnode['data']['feedback_inbetween_checkmark']) {
                    $feedbacks['completion']['inbetween'][] = str_replace([
                      '{course progress}',
                      '{best catquiz}',
                      '{best quiz}',
                    ], [
                      $completioncriteria['course_completed']['inbetween_info'] ?? '0',
                      $completioncriteria['catquiz']['inbetween_info'] ?? '0',
                      $completioncriteria['modquiz']['inbetween_info'] ?? '0',
                    ], $conditionnode['data']['feedback_inbetween']);
                } else {
                    $feedbacks['completion']['inbetween'][] =
                      isset($conditionnode['data']['feedback_inbetween']) ? $conditionnode['data']['feedback_inbetween'] : '';
                }
            }
        }
        if (isset($node['restriction'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if (strpos($restrictionnode['id'], '_feedback') !== false && $restrictionnode['data']['visibility']) {
                    $feedbacks['restriction']['before'][] =
                      isset($restrictionnode['data']['feedback_before']) ? $restrictionnode['data']['feedback_before'] : '';
                }
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
     * @return mixed
     */
    public static function searchnestedarray($haystack, $needle, $key) {
        foreach ($haystack as $item) {
            foreach ($needle as $need) {
                if (!strpos($need, '_feedback')) {
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
        if (!empty($userpath->json['tree']['nodes'])) {
            foreach ($userpath->json['tree']['nodes'] as &$node) {
                if (in_array('starting_node', $node['parentCourse'])) {
                    foreach ($node['data']['course_node_id'] as $courseid) {
                        if (!enrol_is_enabled('manual')) {
                            break; // Manual enrolment not enabled.
                        }
                        if (!$enrol = enrol_get_plugin('manual')) {
                            break; // No manual enrolment plugin.
                        }
                        if (!isset($node['data']['first_enrolled'])) {
                            $node['data']['first_enrolled'] = time();
                            $firstenrollededit = true;
                        }
                        $instances = $DB->get_records('enrol', [
                          'courseid' => $courseid,
                          'enrol' => 'manual',
                        ]);
                        if (!$instances) {
                            break;
                        }
                        $instance = reset($instances); // Use the first manual enrolment plugin in the course.
                        $enrol->enrol_user($instance, $userpath->user_id, null);
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
}
