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
use local_adele\helper\adhoc_task_helper;
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
        $userpath = $event->other['userpath'];
        $completionclass = new course_completion_status();
        $restrictionclass = new  course_restriction_status();
        if ($userpath) {
            $creation = false;
            $nodecompletedname = [];
            if (!isset($userpath->json['user_path_relation'])) {
                $creation = true;
            }
            self::subscribe_user_starting_node($userpath);
            if (!empty($userpath->json['tree']['nodes'])) {
                foreach ($userpath->json['tree']['nodes'] as &$node) {
                    $completioncriteria = $completionclass->get_condition_status($node, $userpath->user_id);
                    $restrictioncriteria = $restrictionclass->get_restriction_status($node, $userpath);
                    $restrictionnodepaths = [];
                    $singlerestrictionnode = [];
                    if (isset($node['data']['completion']['master'])) {
                        $userpath->json['user_path_relation'][$node['id']]['master'] =
                              $node['data']['completion']['master'];
                    }
                    if (
                        isset($restrictioncriteria['master']) &&
                        $restrictioncriteria['master']
                    ) {
                        $restrictionnodepaths[] = 'master';
                    } else if (isset($node['restriction'])) {
                        foreach ($node['restriction']['nodes'] as $restrictionnodepath) {
                            $failedrestriction = false;
                            $validationconditionstring = [];
                            if (
                                isset($restrictionnodepath['parentCondition']) &&
                                $restrictionnodepath['parentCondition'][0] == 'starting_condition'
                            ) {
                                $currentcondition = $restrictionnodepath;
                                $feedback = self::searchnestedarray(
                                    $node['restriction']['nodes'],
                                    $currentcondition['childCondition'],
                                    'id',
                                    true
                                );
                                $activecolumnfeedback = [];
                                $activefeedbackfornode = [];
                                $validationcondition = false;
                                $allconditions = [];
                                while ($currentcondition) {
                                    $currlabel = $currentcondition['data']['label'];
                                    $allconditions[] = $currentcondition['data']['label']
                                    . '_' . $currentcondition['id'];
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
                                        $activecolumnfeedback[] = self::render_placeholders_single_restriction(
                                            $currentcondition['data']['description_before'],
                                            $currentcondition['id'],
                                            $node['restriction']['nodes'],
                                            $restrictioncriteria[$currentcondition['data']['label']][$currentcondition['id']]
                                            ?? null
                                        );
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
                                $activefeedbackfornode =
                                    implode(
                                        get_string('course_condition_concatination_and', 'local_adele'),
                                        $activecolumnfeedback
                                    );
                                $restrictionnodepathsall[] = $allconditions;
                                $node['data']['completion']['feedback']['restriction']['before_active'][$feedback['id']] =
                                $activefeedbackfornode;
                            }
                        }
                    }

                    if (
                        isset($completioncriteria['master']) &&
                        $completioncriteria['master']
                    ) {
                        $validatenodecompletion = [
                            'completionnodepaths' => ['master'],
                            'singlecompletionnode' => 'master',
                            'feedback' => self::getfeedback($node, $completioncriteria, $restrictioncriteria),
                        ];
                    } else if (isset($node['completion'])) {
                        $validatenodecompletion = self::validatenodecompletion(
                            $restrictionnodepathsall ?? [],
                            $node,
                            $completioncriteria,
                            $userpath,
                            $restrictionnodepaths,
                            1,
                            $restrictioncriteria,
                            $nodecompletedname
                        );
                    }
                    $completionnode = self::getconditionnode($validatenodecompletion['completionnodepaths'], 'completion');
                    $restrictionnode = self::getconditionnode($restrictionnodepaths, 'restriction');

                    $userpath->json['user_path_relation'][$node['id']]['restrictioncriteria'] = $restrictioncriteria;
                    $userpath->json['user_path_relation'][$node['id']]['restrictionnode'] = $restrictionnodepathsall ?? [];
                    $userpath->json['user_path_relation'][$node['id']]['allrestrictioncriteria'] = $restrictionnode;
                    $userpath->json['user_path_relation'][$node['id']]['singlerestrictionnode'] = $singlerestrictionnode;

                    $userpath->json['user_path_relation'][$node['id']]['completioncriteria'] = $completioncriteria;
                    $userpath->json['user_path_relation'][$node['id']]['completionnode'] = $completionnode;
                    $userpath->json['user_path_relation'][$node['id']]['singlecompletionnode'] =
                        $validatenodecompletion['singlecompletionnode'];
                    $userpath->json['user_path_relation'][$node['id']]['feedback'] = $validatenodecompletion['feedback'];

                    $node['data']['completion'] = $userpath->json['user_path_relation'][$node['id']];
                }
                $userpathid = user_path_relation::revision_user_path_relation($userpath);
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
                if (!empty($nodecompletedname)) {
                        $nodefinished = node_finished::create([
                            'objectid' => $userpath->id,
                            'context' => context_system::instance(),
                            'other' => [
                                'node' => $nodecompletedname,
                                'userpath' => $userpath,
                            ],
                        ]);
                        $nodefinished->trigger();
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
     * @param array $restrictionnodepathsall Array of all restriction node paths
     * @param array $node Node data to validate
     * @param array $completioncriteria Criteria for completion
     * @param object $userpath User path object
     * @param array $restrictionnodepaths Array of restriction node paths
     * @param int $mode Mode of validation (0 = check only, 1 = full validation)
     * @param array $restrictioncriteria Criteria for restrictions
     * @param array $nodecompletedname Reference to array of completed node names
     * @return bool|array Returns false if mode=0 and validation fails
     */
    public static function validatenodecompletion(
        $restrictionnodepathsall,
        &$node,
        $completioncriteria,
        $userpath,
        $restrictionnodepaths,
        $mode,
        $restrictioncriteria,
        &$nodecompletedname
    ) {
        $completionnodepaths = [];
        $singlecompletionnode = [];
        $feedback = self::getfeedback($node, $completioncriteria, $restrictioncriteria);
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
                        isset($completioncriteria[$label]['completed'][$currentcondition['id']]) &&
                        (
                            $label == 'catquiz' ||
                            $label == 'modquiz' ||
                            $label == 'course_completed'
                        )
                    ) {
                        $validationcondition =
                            $completioncriteria[$label]['completed'][$currentcondition['id']];
                        $singlecompletionnode[$label
                            . '_' . $currentcondition['id']] = $validationcondition;
                        $validationconditionstring[] = $label
                            . '_' . $currentcondition['id'];
                    } else if ($label == 'course_completed') {
                        $completednodecourses = 0;
                        if (
                          isset($completioncriteria[$label]['completed'])
                        ) {
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
                    if (!$validationcondition) {
                        $failedcompletion = true;
                    }
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
                        $feedback['completion']['after'][] = $feedback['completion']['after_all'][$completionnode['id']];
                        unset($feedback['completion']['after_all'][$completionnode['id']]);
                        if (
                            !isset($node['firstcompleted']) ||
                            $node['firstcompleted'] == false
                        ) {
                            $nodecompletedname[] = $node;
                            $node['firstcompleted'] = true;
                        }
                    }
                }
            }
        }
        $feedback['status_restriction'] = self::getnodestatusforrestriciton(
            $feedback,
            $restrictionnodepaths,
            $restrictioncriteria,
            $node,
            $restrictionnodepathsall,
        );
        $feedback['status_completion'] = self::getnodestatusforcompletion(
            $feedback,
            $completionnodepaths,
            $completioncriteria,
            $node['completion']['nodes']
        );
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
     * @param array $completionnodepaths
     * @param array $completioncriteria
     * @param array $node
     * @return string Info on state
     */
    public static function getnodestatusforcompletion($feedback, $completionnodepaths, $completioncriteria, $node) {
        if (count($completionnodepaths) > 0) {
            return 'after';
        }
        foreach ($completioncriteria as $singlecriteria) {
            if (isset($singlecriteria['inbetween'])) {
                foreach ($singlecriteria['inbetween'] as $inbetween) {
                    if ($inbetween) {
                        return 'inbetween';
                    }
                }
            }
        }
        return 'before';
    }


    /**
     * Checks if a node is of a timed type and if its column is valid based on restriction criteria.
     *
     * @param array $node The node to check
     * @param array $restrictioncriteria The restriction criteria to validate against
     * @return bool Returns true if the node is timed and its column is valid, false otherwise
     */
    public static function istypetimedandcolumnvalid($node, $restrictioncriteria) {
        switch ($node['data']['label']) {
            case 'timed':
            case 'timed_duration':
                if (isset($restrictioncriteria[$node['data']['label']][$node['id']]) &&
                 $restrictioncriteria[$node['data']['label']][$node['id']]['isafter']) {
                    return false;
                } else {
                    return true;
                }
            default:
                return true;
        }
    }

    /**
     * Return node status for display purpose.
     *
     * @param array $feedback
     * @param array $restrictionnodepaths
     * @param array $restrictioncriteria
     * @param array $node
     * @param string $wheretoput
     */
    public static function inbetweenfeedback(&$feedback, $restrictionnodepaths, $restrictioncriteria, $node, $wheretoput) {
        $latestdate = 0;
        foreach ($restrictionnodepaths as $signlerestrictionpatharray) {
            $smallestenddate = 0;
            $istimerestricted = false;
            foreach ($signlerestrictionpatharray as $restrictionlabelid) {
                if (strpos($restrictionlabelid, 'time') === 0) {
                    $nodelabelid = explode('_condition_', $restrictionlabelid);
                    $restnode = $restrictioncriteria[$nodelabelid[0]]['condition_' . $nodelabelid[1]] ?? [];
                    if (
                        isset($restnode['inbetween_info']['endtime']) &&
                        $restnode['inbetween_info']['endtime'] !== false
                    ) {
                        if (!$smallestenddate || strtotime($restnode['inbetween_info']['endtime']) < $smallestenddate) {
                            $smallestenddate = strtotime($restnode['inbetween_info']['endtime']);
                        }
                    }
                    $istimerestricted = true;
                }
            }
            if ($latestdate == 0 ||  $smallestenddate > $latestdate) {
                $latestdate = $smallestenddate;
            }

            if (!$istimerestricted) {
                return;
            }
        }
        if ($latestdate !== 0) {
            $feedback['restriction'][$wheretoput . '_timed'] =
            get_string('node_restriction_' . $wheretoput . '_timed', 'local_adele', date('d.m.Y H:i', $latestdate));
        }
    }

    /**
     * Return node status for display purpose.
     *
     * @param array $feedback
     * @param array $restrictionnodepaths
     * @param array $restrictioncriteria
     * @param array $node
     * @param array $restrictionnodepathsall
     */
    public static function getnodestatusforrestriciton(
        &$feedback,
        $restrictionnodepaths,
        $restrictioncriteria,
        $node,
        $restrictionnodepathsall
    ) {
        if (count($restrictionnodepaths) > 0 || !isset($node['restriction']) ||  $node['restriction'] === null) {
            self::inbetweenfeedback($feedback, $restrictionnodepaths, $restrictioncriteria, $node, 'inbetween');
            return 'inbetween';
        }

        foreach ($node['restriction']['nodes'] as $restnode) {
            if (isset($restnode['parentCondition']) && $restnode['parentCondition'][0] === "starting_condition") {
                $isvalid = false;
                if (self::istypetimedandcolumnvalid($restnode, $restrictioncriteria)) {
                    $isvalid = true;
                    $childconditionid = $restnode['childCondition'][1] ?? null;
                    $filterednodes = array_filter($node['restriction']['nodes'], function($item) use ($childconditionid) {
                        return isset($item['id']) && $item['id'] === $childconditionid;
                    });
                    $childcondition = reset($filterednodes);
                    while ($childcondition !== null &&
                        $childcondition !== false && self::istypetimedandcolumnvalid($childcondition, $restrictioncriteria)) {
                        $childconditionid = $childcondition['childCondition'][0] ?? null;
                        $filterednodes = array_filter($node['restriction']['nodes'], function($item) use ($childconditionid) {
                            return isset($item['id']) && $item['id'] === $childconditionid;
                        });
                        $childcondition = $filterednodes[0] ?? null;
                    }
                    if ($childcondition !== null && $childcondition !== false) {
                        $isvalid = false;
                    }
                }
                if ($isvalid) {
                    $childconditionid = $restnode['childCondition'][0];
                    $feedback['restriction']['before_valid'][$childconditionid] =
                    $feedback['restriction']['before'][$childconditionid];
                }
            }
        }
        if (empty($feedback['restriction']['before_valid'])) {
            return 'after';
        }
            self::inbetweenfeedback($feedback, $restrictionnodepathsall, $restrictioncriteria, $node, 'before');
            return 'before';
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
                if (strpos($restrictionall['id'], '_feedback')) {
                    $hastimedcondition = false;
                    $nextid = str_replace('_feedback', '', $restrictionall['id']);
                    $safetycounter = 0;
                    $maxiterations = 50;
                    $reachablecolumn = true;
                    while ($nextid && $safetycounter < $maxiterations && $reachablecolumn) {
                        $found = false;
                        foreach ($node['restriction']['nodes'] as $restrictioncolumn) {
                            if ($restrictioncolumn['id'] == $nextid) {
                                if (strpos($restrictioncolumn['data']['label'], 'timed')) {
                                    $hastimedcondition = true;
                                    $starttime = new \DateTime();
                                    if (
                                        $node['data'] &&
                                        isset($node['data']['first_enrolled'])
                                    ) {
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
                                    if (!strpos($children, '_feedback')) {
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
        $valid = count($conditionnodepaths) ? true : false;
        if ($valid) {
            if ($type == 'completion') {
                foreach ($conditionnodepaths as $conditionnodepath) {
                    if (!is_array($conditionnodepath)) {
                        $conditionnodepath = [$conditionnodepath];
                    }
                }
            }
        }
        return [
            'valid' => $valid,
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
            'information' => null,
            'before' => null,
            'after_all' => null,
            'after' => null,
            'inbetween' => null,
          ],
          'restriction' => [
            'information' => null,
            'before' => null,
            'before_active' => isset($node["data"]["completion"]) ?
            $node["data"]["completion"]["feedback"]["restriction"]["before_active"] : '',
          ],
        ];

        foreach ($node['completion']['nodes'] as $conditionnode) {
            if (
                strpos($conditionnode['id'], '_feedback') !== false &&
                isset($conditionnode['data']['visibility'])
            ) {
                $feedbacks['completion']['before'][] =
                    isset($conditionnode['data']['feedback_before']) ?
                    self::render_placeholders(
                        $conditionnode['data']['feedback_before'],
                        $completioncriteria,
                        $conditionnode['id'],
                        $node['completion']['nodes']
                    ) :
                    '';
                $feedbacks['completion']['information'][] =
                isset($conditionnode['data']['information']) ?
                self::render_placeholders(
                    $conditionnode['data']['information'],
                    $completioncriteria,
                    $conditionnode['id'],
                    $node['completion']['nodes']
                ) :
                '';
                $conditionnodename = str_replace('_feedback', '', $conditionnode['id']);
                $feedbacks['completion']['after_all'][$conditionnodename] =
                    isset($conditionnode['data']['feedback_after']) ?
                        self::render_placeholders(
                            $conditionnode['data']['feedback_after'],
                            $completioncriteria,
                            $conditionnode['id'],
                            $node['completion']['nodes']
                        ) :
                        '';

                if ($conditionnode['data']['feedback_inbetween_checkmark']) {
                    $feedbacks['completion']['inbetween'][] = isset($conditionnode['data']['feedback_inbetween']) ?
                        self::render_placeholders(
                            $conditionnode['data']['feedback_inbetween'],
                            $completioncriteria,
                            $conditionnode['id'],
                            $node['completion']['nodes']
                        ) :
                        '';
                } else {
                    $feedbacks['completion']['inbetween'][] =
                        isset($conditionnode['data']['feedback_inbetween']) ?
                            self::render_placeholders(
                                $conditionnode['data']['feedback_inbetween'],
                                $completioncriteria,
                                $conditionnode['id'],
                                $node['completion']['nodes']
                            ) :
                        '';
                }
            }
        }

        if (isset($node['restriction'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if (strpos($restrictionnode['id'], '_feedback') !== false && $restrictionnode['data']['visibility']) {
                    $feedbacks['restriction']['before'][$restrictionnode['id']] =
                      isset($restrictionnode['data']['feedback_before']) ?
                        self::render_placeholders(
                            $restrictionnode['data']['feedback_before'],
                            $restrictioncriteria,
                            $restrictionnode['id'],
                            $node['restriction']['nodes']
                        ) :
                        '';
                        $feedbacks['restriction']['information'][$restrictionnode['id']] =
                        isset($restrictionnode['data']['information']) ?
                          self::render_placeholders(
                              $restrictionnode['data']['information'],
                              $restrictioncriteria,
                              $restrictionnode['id'],
                              $node['restriction']['nodes']
                          ) :
                          '';
                }
            }
        }
        if ($restrictioncriteria['master']) {
            $feedbacks['restriction']['before'] = [get_string('course_description_master', 'local_adele')];
            $feedbacks['status_restriction'] = 'accessible';
            $feedbacks['status'] = 'accessible';
        }
        if ($completioncriteria['master']) {
            $feedbacks['completion']['after'] = [get_string('course_description_master', 'local_adele')];
            $feedbacks['status_completion'] = 'completed';
            $feedbacks['status'] = 'completed';
        }
        return $feedbacks;
    }



    /**
     * Renders placeholders in a string for a single restriction.
     *
     * @param string $string The string containing placeholders to be replaced
     * @param string $id The ID of the node to process
     * @param array $nodes Array of nodes containing child conditions
     * @param array $condition Optional array of conditions with placeholder data
     * @return string The string with all placeholders replaced with their values
     */
    public static function render_placeholders_single_restriction($string, $id, $nodes, $condition = [] ) {
        if (isset($condition['placeholders'])) {
            foreach ($condition['placeholders'] as $placeholder => $text) {
                if (is_array($text)) {
                    $text = implode(', ', $text);
                }
                $string = str_replace(
                    '{' . $placeholder . '}',
                    (string)$text,
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
                    $text = '<ul>' . $tmptext . '</ul>';
                } else if ($placeholder == 'quiz_attempts_best') {
                    if ($text != '') {
                        $text = get_string('course_description_inbetween_condition_catquiz_best', 'local_adele', $text);
                    }
                } else if (is_array($text)) {
                    $text = implode(', ', $text);
                }
                $needle = '{' . $placeholder . '}';
                $pos = strpos($string, $needle);
                if ($pos !== false) {
                    $string = substr_replace($string, strval($text), $pos, strlen($needle));
                }
            }
        }
        return $string;
    }


    /**
     * Observer for course completed
     *
     * @param string $string
     * @param array $placeholders
     * @param string $id
     * @param array $nodes
     * @return string
     */
    public static function render_placeholders($string, $placeholders , $id, $nodes) {
        $id = str_replace('_feedback', '', $id);
        while ($id != null) {
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
                            $text = '<ul>' . $tmptext . '</ul>';
                        } else if ($placeholder == 'quiz_attempts_best') {
                            if ($text != '') {
                                $text = get_string('course_description_inbetween_condition_catquiz_best', 'local_adele', $text);
                            }
                        } else if (is_array($text)) {
                            $text = implode(', ', $text);
                        }
                        $needle = '{' . $placeholder . '}';
                        $pos = strpos($string, $needle);
                        if ($pos !== false) {
                            $string = substr_replace($string, strval($text), $pos, strlen($needle));
                        }
                    }
                }
            }
            $id = self::findnodebyid($nodes, $id);
        }
        return $string;
    }

    /**
     * Find node by id
     *
     * @param array $nodes
     * @param string $id
     * @return mixed
     */
    public static function findnodebyid($nodes, $id) {
        foreach ($nodes as $node) {
            if (isset($node['id']) && $node['id'] === $id) {
                foreach ($node['childCondition'] as $childcondition) {
                    if (strpos($childcondition, '_feedback') === false) {
                        return $childcondition;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Observer for course completed
     *
     * @param array $haystack
     * @param array $needle
     * @param string $key
     * @param bool $returnfeedback
     * @return mixed
     */
    public static function searchnestedarray($haystack, $needle, $key, $returnfeedback = false) {
        foreach ($haystack as $item) {
            foreach ($needle as $need) {
                if (strpos($need, '_feedback') == $returnfeedback) {
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
     * @param object $userpath
     */
    public static function subscribe_user_starting_node(&$userpath) {
        global $DB;
        $instances = [];
        if (!empty($userpath->json['tree']['nodes'])) {
            foreach ($userpath->json['tree']['nodes'] as &$node) {
                if (
                    $node['type'] != 'dropzone' && isset($node['parentCourse']) &&
                    in_array('starting_node', $node['parentCourse'])
                ) {
                    if (!is_int($node['data']['course_node_id'])) {
                        foreach ($node['data']['course_node_id'] as $courseid) {
                            if (!isset($node['data']['first_enrolled'])) {
                                $node['data']['first_enrolled'] = time();
                                adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);
                            }
                            if (isset($instances[$courseid])) {
                                $instance = $instances[$courseid];
                            } else {
                                if (!enrol_is_enabled('manual')) {
                                    break;
                                }
                                if (!$enrol = enrol_get_plugin('manual')) {
                                    break;
                                }
                                $instance = $DB->get_record(
                                    'enrol',
                                    [
                                        'courseid' => $courseid,
                                        'enrol' => 'manual',
                                    ]
                                );
                                $instances[$courseid] = $instance;
                            }
                            if (!$instance) {
                                continue;
                            }
                            $context = \context_course::instance($courseid);
                            $isenrolled = is_enrolled($context, $userpath->user_id);
                            if (!$isenrolled) {
                                $selectedrole = get_config('local_adele', 'enroll_as_setting');
                                $enrol->enrol_user($instance, $userpath->user_id, $selectedrole);
                            }
                        }
                    }
                }
            }
        }
    }
}
