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
use local_adele\helper\adhoc_task_helper;
use local_adele\event\node_finished;
use context_system;
use local_adele\event\user_path_updated;

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
            self::reschedule_timed_restrictions_for_all_nodes($userpath);
            if (!empty($userpath->json['tree']['nodes'])) {
                foreach ($userpath->json['tree']['nodes'] as &$node) {
                    $completioncriteria = $completionclass->get_condition_status($node, $userpath->user_id);
                    $restrictioncriteria = $restrictionclass->get_restriction_status($node, $userpath);
                    $restrictionnodepaths = [];
                    $restrictionnodepathsall = [];
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
                        foreach (($node['restriction']['nodes'] ?? []) as $restrictionnodepath) {
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
                                // The $feedback value is null when the condition's feedback node is not
                                // wired up (empty childCondition); there is then nothing to key
                                // the per-feedback-node hint by, so skip it instead of fataling.
                                if (isset($feedback['id'])) {
                                    $fbdata = $feedback['data'] ?? [];
                                    if (empty($fbdata['visibility'])) {
                                        // Hidden ("Verbergen"): a hidden feedback node contributes no student-facing
                                        // text - neither the custom message nor the default description -
                                        // so the speech bubble stays empty, consistent with
                                        // restriction.before/information in getfeedback(). Display-only:
                                        // the lock verdict (status_restriction) is unaffected. See #474.
                                        continue;
                                    }
                                    if (
                                        empty($fbdata['feedback_before_checkmark'])
                                        && !empty($fbdata['feedback_before'])
                                    ) {
                                        $node['data']['completion']['feedback']['restriction']['before_active'][$feedback['id']] =
                                            self::render_placeholders_single_restriction(
                                                $fbdata['feedback_before'],
                                                $feedback['id'],
                                                $node['restriction']['nodes']
                                            );
                                    } else {
                                        $node['data']['completion']['feedback']['restriction']['before_active'][$feedback['id']] =
                                            $activefeedbackfornode;
                                    }
                                }
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
                    } else {
                        // A node with neither a master completion nor a `completion` structure
                        // yields the same empty envelope validatenodecompletion() returns for an
                        // empty completion set, so downstream getconditionnode() sees an empty
                        // (valid=false) path instead of an undefined variable.
                        $validatenodecompletion = [
                            'completionnodepaths' => [],
                            'singlecompletionnode' => [],
                            'feedback' => self::getfeedback($node, $completioncriteria, $restrictioncriteria),
                        ];
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

                    // Keep enrolment in sync with displayed accessibility: a node shown
                    // 'accessible' must have the user enrolled into its course, otherwise
                    // clicking it yields "you can't enrol yourself" (GitHub #452 / #443).
                    // Starting nodes are already handled above; this also covers nodes that
                    // are accessible because they carry no (or satisfied) access criteria.
                    if (($validatenodecompletion['feedback']['status'] ?? '') === 'accessible') {
                        self::enrol_user_into_node($node, $userpath);
                    }

                    $node['data']['completion'] = $userpath->json['user_path_relation'][$node['id']];
                }
                // Record which language the feedback was rendered in, so the fetch-time re-render
                // can skip when the viewer's language already matches it (#493 performance).
                $userpath->json['feedback_lang'] = current_language();
                $userpathid = user_path_relation::revision_user_path_relation($userpath);
                // Hand the persisted state to enrol_adele, which reconciles the
                // target course enrolments against it (enrol where accessible,
                // reactivate where suspended, suspend where no node grants the
                // course any more). Warns clearly (does not silently no-op)
                // when enrol_adele is absent.
                enrol_state::request_reconcile(
                    (int) $userpath->learning_path_id,
                    (int) $userpath->user_id
                );
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
        foreach (($node['completion']['nodes'] ?? []) as $completionnode) {
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
            isset($userpath->user_id) ? (int) $userpath->user_id : null
        );
        $feedback['status_completion'] = self::getnodestatusforcompletion(
            $feedback,
            $completionnodepaths,
            $completioncriteria,
            $node['completion']['nodes'] ?? []
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
                if (
                    isset($restrictioncriteria[$node['data']['label']][$node['id']]) &&
                    $restrictioncriteria[$node['data']['label']][$node['id']]['isafter']
                ) {
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
     * @param int|null $userid The path's learner: the end is shown in their time zone.
     */
    public static function inbetweenfeedback(
        &$feedback,
        $restrictionnodepaths,
        $restrictioncriteria,
        $node,
        $wheretoput,
        $userid = null
    ) {
        $latestdate = 0;
        foreach ($restrictionnodepaths as $signlerestrictionpatharray) {
            $smallestenddate = 0;
            $istimerestricted = false;
            // Defensive: in AND-chain graphs the walker can place a scalar
            // (feedback-node id string) directly into $restrictionnodepaths
            // alongside the path arrays. Skip anything that is not an array
            // so the inner foreach never tries to iterate a string.
            // No logic change – pure null-safety guard. (Tier 2 AND-chain tests).
            if (!is_array($signlerestrictionpatharray)) {
                continue;
            }
            foreach ($signlerestrictionpatharray as $restrictionlabelid) {
                if (strpos($restrictionlabelid, 'time') === 0) {
                    $nodelabelid = explode('_condition_', $restrictionlabelid);
                    $restnode = $restrictioncriteria[$nodelabelid[0]]['condition_' . $nodelabelid[1]] ?? [];
                    // Timestamps since #581; to_timestamp() still reads a value
                    // computed before the upgrade.
                    $endtime = \local_adele\helper\time_value::to_timestamp($restnode['inbetween_info']['endtime'] ?? null);
                    if ($endtime !== null && (!$smallestenddate || $endtime < $smallestenddate)) {
                        $smallestenddate = $endtime;
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
            get_string(
                'node_restriction_' . $wheretoput . '_timed',
                'local_adele',
                \local_adele\helper\time_value::display($latestdate, $userid)
            );
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
     * @param int|null $userid The path's learner, for dates in their time zone.
     */
    public static function getnodestatusforrestriciton(
        &$feedback,
        $restrictionnodepaths,
        $restrictioncriteria,
        $node,
        $restrictionnodepathsall,
        $userid = null
    ) {
        // Treat a present-but-empty restriction (no condition nodes) the same as
        // no restriction at all, so removing all access criteria opens the node
        // (issue #449).
        if (count($restrictionnodepaths) > 0 || empty($node['restriction']['nodes'])) {
            self::inbetweenfeedback($feedback, $restrictionnodepaths, $restrictioncriteria, $node, 'inbetween', $userid);
            return 'inbetween';
        }

        foreach ($node['restriction']['nodes'] as $restnode) {
            if (isset($restnode['parentCondition']) && $restnode['parentCondition'][0] === "starting_condition") {
                $isvalid = false;
                if (self::istypetimedandcolumnvalid($restnode, $restrictioncriteria)) {
                    $isvalid = true;
                    $childconditionid = $restnode['childCondition'][1] ?? null;
                    $filterednodes = array_filter($node['restriction']['nodes'], function ($item) use ($childconditionid) {
                        return isset($item['id']) && $item['id'] === $childconditionid;
                    });
                    $childcondition = reset($filterednodes);
                    while (
                        $childcondition !== null &&
                        $childcondition !== false && self::istypetimedandcolumnvalid($childcondition, $restrictioncriteria)
                    ) {
                        $childconditionid = $childcondition['childCondition'][0] ?? null;
                        $filterednodes = array_filter($node['restriction']['nodes'], function ($item) use ($childconditionid) {
                            return isset($item['id']) && $item['id'] === $childconditionid;
                        });
                        $childcondition = $filterednodes[0] ?? null;
                    }
                    if ($childcondition !== null && $childcondition !== false) {
                        $isvalid = false;
                    }
                }
                if ($isvalid) {
                    // The childCondition can be empty when the feedback node is not wired via it
                    // (auto-created restriction left unedited); the sibling feedback node
                    // ({condition}_feedback) still carries the text, so fall back to that id.
                    // Previously this branch skipped population entirely, which wrongly flipped a
                    // merely locked (satisfiable) node to 'after' - "kann nicht mehr freigeschaltet
                    // werden" - instead of showing its requirement (#483 follow-up).
                    $childconditionid = $restnode['childCondition'][0] ?? ($restnode['id'] . '_feedback');
                    // The status_restriction must mirror the ACTUAL restriction state at all
                    // times: $isvalid means this column is still blocking, so the node is
                    // 'before' (requirements not fulfilled). Key before_valid off $isvalid,
                    // NOT off whether the feedback text is present - hiding a feedback node
                    // ("Verbergen") empties the display string but must never flip a locked
                    // node to 'after'. The stored value is the (possibly empty) display text.
                    $feedback['restriction']['before_valid'][$childconditionid] =
                        $feedback['restriction']['before'][$childconditionid] ?? '';
                }
            }
        }
        if (empty($feedback['restriction']['before_valid'])) {
            return 'after';
        }
            self::inbetweenfeedback($feedback, $restrictionnodepathsall, $restrictioncriteria, $node, 'before', $userid);
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
        // Derive 'completed' from the completion verdict (status_completion, computed
        // from the condition evaluation just above), NOT from the presence of the
        // after-feedback text. The two are equivalent for a completed node, but reading
        // the verdict means hiding a completed node's feedback ("Verbergen") empties the
        // text without dropping the completed status/colour. Display-only. See #474.
        if (($feedback['status_completion'] ?? '') === 'after') {
            return 'completed';
        }
        if (
            $restrictionnodepaths ||
            // A present-but-empty restriction (no condition nodes) means the node
            // is unrestricted, exactly like a node with no restriction key at all.
            // Without this, removing all access criteria left the node locked
            // (issue #449): the empty restriction fell through to 'closed'.
            (is_null($feedback['restriction']['before']) && empty($node['restriction']['nodes']))
        ) {
            return 'accessible';
        }
        if (!empty($node['restriction']['nodes'])) {
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
                                    $starttime = \DateTime::createFromImmutable(\core\di::get(\core\clock::class)->now());
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
        $now = \DateTime::createFromImmutable(\core\di::get(\core\clock::class)->now());
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
     * Resolve a condition's DEFAULT template string for a feedback node, from the condition
     * class ({label}::get_description()[$field]), via the sibling condition node
     * ({condition}_feedback -> {condition}). Used both to fill an unseeded info field (#483)
     * and, under $forcelangdefaults, to render the info-symbol AND speech-bubble text from the
     * current-language lang defaults so they follow a language switch (#493). Returns '' when
     * it cannot be resolved so the caller can fall back.
     *
     * @param string $feedbackid The feedback node id (e.g. condition_1_feedback).
     * @param array $nodes The completion or restriction nodes array.
     * @param string $conditiontype 'completion' or 'restriction'.
     * @param string $field The get_description() key to read (e.g. 'information',
     *   'description_before', 'description_inbetween', 'description_after').
     * @return string
     */
    public static function get_default_condition_string($feedbackid, $nodes, $conditiontype, $field = 'information') {
        $conditionid = str_replace('_feedback', '', $feedbackid);
        $label = '';
        foreach ($nodes as $conditionnode) {
            if (($conditionnode['id'] ?? '') === $conditionid) {
                $label = $conditionnode['data']['label'] ?? '';
                break;
            }
        }
        if ($label === '') {
            return '';
        }
        $class = '\\local_adele\\course_' . $conditiontype . '\\conditions\\' . $label;
        if (!class_exists($class)) {
            return '';
        }
        $description = (new $class())->get_description();
        return $description[$field] ?? '';
    }

    /**
     * Pick the template to render for a feedback field.
     *  - $forcelangdefaults (fetch-time re-render, #493): the condition's current-language lang
     *    default wins, so the text follows a language switch.
     *  - otherwise (recompute): the per-node stored template wins; when it is empty the lang
     *    default is used only if $fallbackwhenempty is set (the info-symbol #483 behaviour) -
     *    the speech-bubble fields keep their original "stored or blank" behaviour.
     *
     * @param bool $forcelangdefaults
     * @param string|null $stored The per-node stored template (feedback_before / information / ...).
     * @param string $feedbackid
     * @param array $nodes
     * @param string $conditiontype 'completion' or 'restriction'.
     * @param string $field The get_description() key for the lang default.
     * @param bool $fallbackwhenempty Fall back to the lang default when the stored template is empty.
     * @return string
     */
    public static function pick_feedback_template(
        $forcelangdefaults,
        $stored,
        $feedbackid,
        $nodes,
        $conditiontype,
        $field,
        $fallbackwhenempty = false
    ) {
        $stored = ($stored === null) ? '' : $stored;
        if (!$forcelangdefaults && $stored !== '') {
            return $stored;
        }
        if ($forcelangdefaults || $fallbackwhenempty) {
            $default = self::get_default_condition_string($feedbackid, $nodes, $conditiontype, $field);
            if ($default !== '') {
                return $default;
            }
        }
        return $stored;
    }

    /**
     * Build the student-facing feedback (info-symbol + speech-bubble text) for a node.
     *
     * @param array $node
     * @param array $completioncriteria
     * @param array $restrictioncriteria
     * @param bool $forcelangdefaults When true the info-symbol text is always taken from the
     *   condition's lang default (ignoring the per-node stored template), so it renders in the
     *   current language. Used by the fetch-time re-render so the info-symbol follows a language
     *   switch instead of being frozen in the recompute language (#493).
     * @return array
     */
    public static function getfeedback($node, $completioncriteria, $restrictioncriteria, $forcelangdefaults = false) {
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
            // Defensive: the old isset($node["data"]["completion"]) guard only
            // checked one level; deeper keys could still be absent. Replaced
            // with a null-coalescing chain that short-circuits the whole path.
            // No logic change – pure null-safety guard. (Tier 2 AND-chain tests).
            'before_active' => $node["data"]["completion"]["feedback"]["restriction"]["before_active"] ?? '',
          ],
        ];

        foreach (($node['completion']['nodes'] ?? []) as $conditionnode) {
            if (
                strpos($conditionnode['id'], '_feedback') !== false &&
                isset($conditionnode['data']['visibility'])
            ) {
                // The "Verbergen" toggle is display-only: it empties the criterion text
                // the info symbol and speech bubble render, but never changes a status
                // verdict. All four display fields (information, before, inbetween and the
                // completed message after_all) honour visibility. after_all can be gated
                // safely because getnodestatus() now derives 'completed' from
                // status_completion (the condition verdict), not from this text. See #474.
                $visible = $conditionnode['data']['visibility'] ?? false;
                $completionnodes = $node['completion']['nodes'];
                // Under $forcelangdefaults (fetch-time re-render) every field is taken from the
                // condition's current-language lang default, so both the info-symbol AND the
                // speech-bubble text follow a language switch (#493). Otherwise the stored per-node
                // template is used (the info-symbol additionally falls back to the default when
                // unseeded - the #483 behaviour, via $fallbackwhenempty).
                $beforetemplate = self::pick_feedback_template(
                    $forcelangdefaults,
                    $conditionnode['data']['feedback_before'] ?? '',
                    $conditionnode['id'],
                    $completionnodes,
                    'completion',
                    'description_before'
                );
                $feedbacks['completion']['before'][] =
                    ($visible && $beforetemplate !== '') ?
                    self::render_placeholders($beforetemplate, $completioncriteria, $conditionnode['id'], $completionnodes) :
                    '';
                $informationtemplate = self::pick_feedback_template(
                    $forcelangdefaults,
                    $conditionnode['data']['information'] ?? '',
                    $conditionnode['id'],
                    $completionnodes,
                    'completion',
                    'information',
                    true
                );
                $feedbacks['completion']['information'][] =
                ($visible && $informationtemplate !== '') ?
                self::render_placeholders(
                    // The info-symbol shows the ABSOLUTE criterion (min of total), while the
                    // feedback messages show live progress. Both templates share the {item}
                    // token, so for the info-symbol only, render it against {item_total} - the
                    // absolute placeholder built alongside {item} in course_completed (#483).
                    str_replace('{item}', '{item_total}', $informationtemplate),
                    $completioncriteria,
                    $conditionnode['id'],
                    $completionnodes
                ) :
                '';
                $conditionnodename = str_replace('_feedback', '', $conditionnode['id']);
                $aftertemplate = self::pick_feedback_template(
                    $forcelangdefaults,
                    $conditionnode['data']['feedback_after'] ?? '',
                    $conditionnode['id'],
                    $completionnodes,
                    'completion',
                    'description_after'
                );
                $feedbacks['completion']['after_all'][$conditionnodename] =
                    ($visible && $aftertemplate !== '') ?
                        self::render_placeholders($aftertemplate, $completioncriteria, $conditionnode['id'], $completionnodes) :
                        '';

                $inbetweentemplate = self::pick_feedback_template(
                    $forcelangdefaults,
                    $conditionnode['data']['feedback_inbetween'] ?? '',
                    $conditionnode['id'],
                    $completionnodes,
                    'completion',
                    'description_inbetween'
                );
                $feedbacks['completion']['inbetween'][] =
                    ($visible && $inbetweentemplate !== '') ?
                        self::render_placeholders($inbetweentemplate, $completioncriteria, $conditionnode['id'], $completionnodes) :
                        '';
            }
        }

        if (isset($node['restriction'])) {
            foreach (($node['restriction']['nodes'] ?? []) as $restrictionnode) {
                if (strpos($restrictionnode['id'], '_feedback') !== false && ($restrictionnode['data']['visibility'] ?? false)) {
                    $restrictionnodes = $node['restriction']['nodes'];
                    // Under $forcelangdefaults the restriction requirement text (speech bubble) and
                    // info-symbol are taken from the condition lang default so they follow a language
                    // switch (#493); otherwise the stored template is used (info-symbol falls back to
                    // the default when unseeded, the #483 behaviour).
                    $restbeforetemplate = self::pick_feedback_template(
                        $forcelangdefaults,
                        $restrictionnode['data']['feedback_before'] ?? '',
                        $restrictionnode['id'],
                        $restrictionnodes,
                        'restriction',
                        'description_before'
                    );
                    $feedbacks['restriction']['before'][$restrictionnode['id']] =
                      ($restbeforetemplate !== '') ?
                        self::render_placeholders(
                            $restbeforetemplate,
                            $restrictioncriteria,
                            $restrictionnode['id'],
                            $restrictionnodes
                        ) :
                        '';
                        $restinformationtemplate = self::pick_feedback_template(
                            $forcelangdefaults,
                            $restrictionnode['data']['information'] ?? '',
                            $restrictionnode['id'],
                            $restrictionnodes,
                            'restriction',
                            'information',
                            true
                        );
                        $feedbacks['restriction']['information'][$restrictionnode['id']] =
                        ($restinformationtemplate !== '') ?
                          self::render_placeholders(
                              $restinformationtemplate,
                              $restrictioncriteria,
                              $restrictionnode['id'],
                              $restrictionnodes
                          ) :
                          '';
                }
            }
        }
        if ($restrictioncriteria['master'] ?? false) {
            $feedbacks['restriction']['before'] = [get_string('course_description_master', 'local_adele')];
            $feedbacks['status_restriction'] = 'accessible';
            $feedbacks['status'] = 'accessible';
        }
        if ($completioncriteria['master'] ?? false) {
            $feedbacks['completion']['after'] = [get_string('course_description_master', 'local_adele')];
            $feedbacks['status_completion'] = 'completed';
            $feedbacks['status'] = 'completed';
        }
        return $feedbacks;
    }

    /**
     * Re-render the stored feedback DISPLAY strings (info-symbol + speech bubble) of a user
     * path in the CURRENT language, so info + feedback follow a language switch instead of
     * staying frozen in the language the recompute ran in (#493).
     *
     * The recompute (updated_single) renders and stores the feedback text; this runs at FETCH
     * time (get_learning_user_relation), as the viewing user, and overwrites only the
     * language-dependent DISPLAY fields - the stored status verdicts and column structure are
     * kept. The condition status classes are pure reads (no side effects), so re-running them on
     * every fetch is safe. The info-symbol is forced to the condition lang default so it always
     * follows the language; the speech-bubble text is re-rendered from the node's templates.
     *
     * @param string $json The stored user-path json (string).
     * @param int $userid The path owner's id.
     * @return string The json with feedback display strings re-rendered in the current language.
     */
    public static function rerender_feedback_language($json, $userid) {
        $data = json_decode($json, true);
        if (empty($data['tree']['nodes']) || empty($data['user_path_relation'])) {
            return $json;
        }
        // The feedback was already rendered in the viewer's language (the common case on a
        // single-language site) - nothing to re-render, so avoid the per-node status reads (#493).
        if (isset($data['feedback_lang']) && $data['feedback_lang'] === current_language()) {
            return $json;
        }
        $completionclass = new course_completion_status();
        $restrictionclass = new course_restriction_status();
        $userpath = (object) ['user_id' => $userid, 'json' => $data];
        foreach ($data['tree']['nodes'] as $node) {
            $nodeid = $node['id'] ?? null;
            if ($nodeid === null || !isset($data['user_path_relation'][$nodeid]['feedback'])) {
                continue;
            }
            $completioncriteria = $completionclass->get_condition_status($node, $userid);
            $restrictioncriteria = $restrictionclass->get_restriction_status($node, $userpath);
            $fresh = self::getfeedback($node, $completioncriteria, $restrictioncriteria, true);
            $feedback = &$data['user_path_relation'][$nodeid]['feedback'];
            // Info-symbol + completion speech-bubble text (before/inbetween shown in the before/
            // inbetween states).
            foreach (['information', 'before', 'inbetween'] as $field) {
                if (isset($fresh['completion'][$field])) {
                    $feedback['completion'][$field] = $fresh['completion'][$field];
                }
            }
            // The after_all map (all conditions' completed message) comes from getfeedback(); the
            // recompute splits the completed ones into `after`. Re-derive both from the fresh
            // (translated) after_all using the stored completion verdict, so the "completed" bubble
            // follows the language too.
            $freshafterall = array_values((array) ($fresh['completion']['after_all'] ?? []));
            if (($feedback['status_completion'] ?? '') === 'after') {
                $feedback['completion']['after'] = $freshafterall;
                $feedback['completion']['after_all'] = [];
            } else {
                $feedback['completion']['after_all'] = $fresh['completion']['after_all'] ?? [];
            }
            // Restriction info-symbol + requirement text.
            foreach (['information', 'before'] as $field) {
                if (isset($fresh['restriction'][$field])) {
                    $feedback['restriction'][$field] = $fresh['restriction'][$field];
                }
            }
            // The before_valid / before_active maps are keyed per blocking column; refresh their
            // VALUES from the freshly-rendered `before` (same keys) so the locked-requirement list
            // follows the language too, keeping the stored keys (which columns block).
            foreach (['before_valid', 'before_active'] as $field) {
                if (isset($feedback['restriction'][$field]) && is_array($feedback['restriction'][$field])) {
                    foreach (array_keys($feedback['restriction'][$field]) as $key) {
                        if (isset($fresh['restriction']['before'][$key])) {
                            $feedback['restriction'][$field][$key] = $fresh['restriction']['before'][$key];
                        }
                    }
                }
            }
            unset($feedback);
        }
        return json_encode($data);
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
    public static function render_placeholders_single_restriction($string, $id, $nodes, $condition = []) {
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
        return self::strip_unresolved_placeholders($string);
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
    public static function render_placeholders($string, $placeholders, $id, $nodes) {
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
        return self::strip_unresolved_placeholders($string);
    }

    /**
     * Remove any placeholder token ({snake_case}) that no condition resolved.
     *
     * A criterion with no node selected (now prevented on save, see #450) or one that
     * references a since-deleted node never sets its placeholder, so the literal token
     * would otherwise reach the student's feedback. Strip leftovers so they never show
     * a raw {node_name} (GitHub #456).
     *
     * @param string $string The already-substituted feedback string.
     * @return string The string with any remaining placeholder tokens removed.
     */
    public static function strip_unresolved_placeholders($string) {
        return preg_replace('/\{[a-z0-9_]+\}/i', '', (string)$string);
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
     * Reschedule the timed-restriction adhoc tasks for every node in the user path.
     *
     * enrol_user_into_node() only schedules for nodes it actually (re-)enrols, so a
     * child node that was already unlocked earlier would never have its task updated
     * when a restriction date is moved in the editor. This iterates ALL non-dropzone
     * nodes; the per-slot dedup key makes reschedule_or_queue_adhoc_task() idempotent,
     * so re-covering the enrolled nodes here is harmless.
     *
     * @param object $userpath The user-path object (json already decoded as array).
     * @return void
     */
    public static function reschedule_timed_restrictions_for_all_nodes(&$userpath) {
        if (empty($userpath->json['tree']['nodes'])) {
            return;
        }
        foreach ($userpath->json['tree']['nodes'] as $node) {
            if (($node['type'] ?? '') === 'dropzone') {
                continue;
            }
            adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);
        }
    }

    /**
     * Subscribe to starting nodes
     * @param object $userpath
     */
    public static function subscribe_user_starting_node(&$userpath) {
        if (!empty($userpath->json['tree']['nodes'])) {
            foreach ($userpath->json['tree']['nodes'] as &$node) {
                // The '??' fallback avoids "Undefined array key 'type'" on any
                // node without an explicit type; the sibling check in
                // set_scheduled_adhoc_tasks() above uses the same guard.
                if (
                    ($node['type'] ?? '') != 'dropzone' && isset($node['parentCourse']) &&
                    in_array('starting_node', $node['parentCourse'])
                ) {
                    self::enrol_user_into_node($node, $userpath);
                }
            }
        }
    }

    /**
     * Enrol the user into every course backing a single node (idempotently).
     *
     * Extracted verbatim from the former starting-node loop so that both
     * starting nodes (subscribed up front) and any node the recompute finds
     * 'accessible' share one enrolment path — keeping displayed accessibility
     * and real enrolment in sync (GitHub #452 / #443).
     *
     * @param array $node Node array, modified by reference (stamps first_enrolled).
     * @param object $userpath The user path the node belongs to.
     * @return void
     */
    public static function enrol_user_into_node(&$node, $userpath) {
        if (!isset($node['data']['course_node_id']) || is_int($node['data']['course_node_id'])) {
            return;
        }
        foreach ($node['data']['course_node_id'] as $courseid) {
            if (!isset($node['data']['first_enrolled'])) {
                $node['data']['first_enrolled'] = \core\di::get(\core\clock::class)->time();
            }
            // Schedule tasks for any future restriction boundaries. The helper skips
            // past boundaries internally, so calling this on every evaluation handles
            // both initial enrolment and later restriction-date edits; the dedup key
            // keeps it idempotent.
            adhoc_task_helper::set_scheduled_adhoc_tasks($node, $userpath);
            // Target course enrolment is owned exclusively by enrol_adele.
            // The reconcile_user() call after this loop (via
            // enrol_state::request_reconcile(), called once per user path
            // recompute in updated_learning_path()/revision_user_path_relation()
            // callers) is the only enrolment path and warns clearly via
            // enrol_state::warn_enrol_adele_missing() when enrol_adele is
            // absent or inactive.
        }
    }
}
