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
use local_adele\event\user_path_updated;

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
            $creation = false;
            if (!isset($userpath->json['user_path_relation'])) {
                $creation = true;
            }
            self::subscribe_user_starting_node($userpath);
            if (!empty($userpath->json['tree']['nodes'])) {
                foreach ($userpath->json['tree']['nodes'] as &$node) {
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
                                            $restrictioncriteria[$currlabel][$currcondi]['completed'] ?? false;
                                        $singlerestrictionnode[$currentcondition['data']['label']
                                            . '_' . $currentcondition['id']] = $validationcondition;
                                        $validationconditionstring[] = $currentcondition['data']['label']
                                            . '_' . $currentcondition['id'];
                                    } else if (
                                        $currentcondition['data']['label'] == 'parent_node_completed' &&
                                        isset($restrictioncriteria[$currlabel][$currentcondition['id']]['completed'])
                                    ) {
                                        $validationcondition =
                                          $restrictioncriteria[$currlabel][$currentcondition['id']]['completed'];
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
                            1,
                            $restrictioncriteria
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
                $userpathid = $userpathrelationhelper->revision_user_path_relation($userpath);
                if ($creation) {
                    global $DB;
                    $createduserpath = $DB->get_record('local_adele_path_user', ['id' => $userpathid]);
                    $createduserpath->json = json_decode($createduserpath->json, true);
                    $createduserpath->json = self::translate_completion_criteria($createduserpath->json);
                    $eventsingle = user_path_updated::create([
                      'objectid' => $userpathid,
                      'context' => context_system::instance(),
                      'other' => [
                        'userpath' => $createduserpath,
                      ],
                    ]);
                    $eventsingle->trigger();
                }
            }
        }
    }

    /**
     * Translate the completion into nodes on creation
     *
     * @param  array $json
     * @return array
     */
    public static function translate_completion_criteria($json) {
        foreach ($json['tree']['nodes'] as &$node) {
            if (
                !isset($node['data']['completion']) &&
                isset($json['user_path_relation'][$node['id']])
            ) {
                $node['data']['completion'] = $json['user_path_relation'][$node['id']];
            }
        }
        return $json;
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
     * @param  array $node
     * @param  array $completioncriteria
     * @param  object $userpath
     * @param  array $restrictionnodepaths
     * @param  number $mode
     * @param  array $restrictioncriteria
     * @return array
     */
    public static function validatenodecompletion(
        &$node,
        $completioncriteria,
        $userpath,
        $restrictionnodepaths,
        $mode,
        $restrictioncriteria
    ) {
        $completionnodepaths = [];
        $singlecompletionnode = [];
        $feedback = self::getfeedback($node, $completioncriteria, $restrictioncriteria);
        $priority = false;
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
                        $label == 'modquiz' ||
                        $label == 'course_completed'
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
                    } else {
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
            $i = 0;
            foreach ($feedback['completion']['after_all'] as $condition => $completionpriority) {
                if ($completionpriority['priority'] < $priority) {
                    $feedback['completion']['higher'][] = $feedback['completion']['inbetween'][$i];
                }
                $i++;
            }
        }
        unset($feedback['completion']['after_all']);
        $feedback['status'] = self::getnodestatus(
            $feedback,
            $restrictionnodepaths,
            $node
        );
        $node = self::set_animation_data($node, $feedback['status']);

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
     * @param array $node
     * @param string $status
     * @return array
     */
    public static function set_animation_data($node, $status) {
        if (
            !isset($node['data']['animations']['seenrestriction']) &&
            $status != 'closed' &&
            $status != 'not_accessible'
          ) {
            $node['data']['animations']['seenrestriction'] = false;
        }
        if (
          !isset($node['data']['animations']['seencompletion']) &&
          $status == 'completed'
        ) {
            $node['data']['animations']['seencompletion'] = false;
        }
        return $node;
    }

    /**
     * Return node status for display purpose.
     *
     * @param array $feedback
     * @param array $restrictionnodepaths
     * @param array $node
     * @return string
     */
    public static function getnodestatus($feedback, $restrictionnodepaths, $node) {
        if ($feedback['completion']['after']) {
            return 'completed';
        }
        if (
          $restrictionnodepaths ||
          is_null($feedback['restriction']['before'])
        ) {
            return 'accessible';
        }
        if (isset($node['restriction'])) {
            foreach ($node['restriction']['nodes'] as $restrictionall) {
                if (str_contains($restrictionall['id'], '_feedback')) {
                    $hastimedcondition = false;
                    $nextid = str_replace('_feedback', '', $restrictionall['id']);
                    $safetycounter = 0;
                    $maxiterations = 50;
                    $reachablecolumn = true;
                    while ($nextid && $safetycounter < $maxiterations && $reachablecolumn) {
                        $found = false;
                        foreach ($node['restriction']['nodes'] as $restrictioncolumn) {
                            if ($restrictioncolumn['id'] == $nextid) {
                                if (str_contains($restrictioncolumn['data']['label'], 'timed')) {
                                    $hastimedcondition = true;
                                    $starttime = new \DateTime();
                                    if ($node['data']['first_enrolled']) {
                                        $starttime->setTimestamp($node['data']['first_enrolled']);
                                    }
                                    $istimeinfuture = self::gettimestamptoday(
                                        $restrictioncolumn['data'],
                                        $starttime
                                    );
                                    if (!$istimeinfuture) {
                                        $reachablecolumn = false;
                                    }
                                }
                                $newnextid = null;
                                foreach ($restrictioncolumn['childCondition'] as $children) {
                                    if (!str_contains($children, '_feedback')) {
                                        $newnextid = $children;
                                        break;
                                    }
                                }
                                $nextid = $newnextid;
                                $found = true;
                                break;
                            }
                        }
                        if ($reachablecolumn) {
                            return 'not_accessible';
                        }
                        $safetycounter++;
                        if (!$found) {
                            break;
                        }
                    }
                    if ($safetycounter >= $maxiterations) {
                        return 'error: loop limit exceeded';
                    }
                    if (!$hastimedcondition) {
                        return 'not_accessible';
                    }
                }
            }
        }
        return 'closed';
    }

    /**
     * Check if node is reachable
     *
     * @param array $data
     * @param \DateTime $starttime
     * @return bool
     */
    public static function gettimestamptoday($data, $starttime) {
        $now = new \DateTime();
        if (
           isset($data['value']['end'])
        ) {
            $date = \DateTime::createFromFormat('Y-m-d\TH:i', $data['value']['end']);
            return $date > $now;
        }
        if (
            $data['value']['selectedDuration']
        ) {
            $durationvalue = $data['value']['durationValue'];
            $selectedduration = $data['value']['selectedDuration'];
            if (isset(self::$durationvaluearray[$durationvalue])) {
                $totalseconds = self::$durationvaluearray[$durationvalue] * $selectedduration;
                $endtime = clone $starttime;
                $endtime->modify("+{$totalseconds} seconds");
                return $now < $endtime;
            }
        }
        return true;
    }

     /**
      * Maps duration types to their equivalent durations in seconds.
      *
      * @var array The keys represent the duration types as follows:
      *            '0' for days, with each day being 86400 seconds;
      *            '1' for weeks, with each week being 604800 seconds;
      *            '2' for months, with each month approximated to 2629746 seconds (considering an average month duration).
      */
    private static $durationvaluearray = [
        '0' => 86400, // Days.
        '1' => 604800, // Weeks.
        '2' => 2629746, // Months.
    ];

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
                            isset($completionpriorities[$condition]) && (
                                $priority == 0 ||
                                $completionpriorities[$condition] < $priority
                            )
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
     * @param array $restrictioncriteria
     * @return array
     */
    public static function getfeedback($node, $completioncriteria, $restrictioncriteria) {
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
                  isset($conditionnode['data']['feedback_before']) ?
                      self::render_placeholders(
                        $conditionnode['data']['feedback_before'],
                        $completioncriteria,
                        $conditionnode['id']
                      ) :
                      '';

                $feedbacks['completion']['after_all'][str_replace('_feedback', '', $conditionnode['id'])] = [
                    'priority' => $conditionnode['data']['feedback_priority'] ?? 3,
                    'text' => isset($conditionnode['data']['feedback_after']) ?
                        self::render_placeholders(
                            $conditionnode['data']['feedback_after'],
                            $completioncriteria,
                            $conditionnode['id']
                        ) :
                        '',
                ];

                if ($conditionnode['data']['feedback_inbetween_checkmark']) {
                    $feedbacks['completion']['inbetween'][] = isset($conditionnode['data']['feedback_inbetween']) ?
                        self::render_placeholders(
                            $conditionnode['data']['feedback_inbetween'],
                            $completioncriteria,
                            $conditionnode['id']
                        ) :
                        '';
                } else {
                    $feedbacks['completion']['inbetween'][] =
                      isset($conditionnode['data']['feedback_inbetween']) ?
                          self::render_placeholders(
                            $conditionnode['data']['feedback_inbetween'],
                            $completioncriteria,
                            $conditionnode['id']
                          ) :
                          '';
                }
            }
        }
        if (isset($node['restriction'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if (strpos($restrictionnode['id'], '_feedback') !== false && $restrictionnode['data']['visibility']) {
                    $feedbacks['restriction']['before'][] =
                      isset($restrictionnode['data']['feedback_before']) ?
                        self::render_placeholders(
                            $restrictionnode['data']['feedback_before'],
                            $restrictioncriteria,
                            $restrictionnode['id']
                        ) :
                        '';
                }
            }
        }
        return $feedbacks;
    }

    /**
     * Observer for course completed
     *
     * @param string $string
     * @param array $placeholders
     * @param string $id
     * @return string
     */
    public static function render_placeholders($string, $placeholders , $id) {
        $id = str_replace('_feedback', '', $id);
        foreach ($placeholders as $condition) {
            if (isset($condition['placeholders'])) {
                foreach ($condition['placeholders'] as $placeholder => $text) {
                    $string = str_replace(
                        '{' . $placeholder . '}',
                        $text,
                        $string
                    );
                }
            } else if (isset($condition[$id]['placeholders'])) {
                foreach ($condition[$id]['placeholders'] as $placeholder => $text) {
                    if ($placeholder == 'quiz_attempts_list') {
                        $tmptext = '';
                        foreach ($text as $textelement) {
                            $textelement = (object) $textelement;
                            $tmptext .=
                              get_string('course_description_after_condition_modquiz_list', 'local_adele', $textelement);
                        }
                        $text = $tmptext;
                    } else if ($placeholder == 'quiz_attempts_best') {
                        $text = get_string('course_description_inbetween_condition_catquiz_best', 'local_adele', $text);
                    } else if (is_array($text)) {
                        $text = implode(', ', $text);
                    }
                    $string = str_replace(
                        '{' . $placeholder . '}',
                        strval($text),
                        $string
                    );
                }
            }
        }
        return $string;
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
