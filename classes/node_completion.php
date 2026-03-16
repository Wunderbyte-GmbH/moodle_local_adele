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
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_adele;

use completion_info;
use context_system;
use DateTime;
use DateTimeZone;
use local_adele\course_restriction\course_restriction_status;
use local_adele\event\user_path_updated;
use local_adele\helper\adhoc_task_helper;
use local_adele\task\update_user_path;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * External Service for local adele.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class node_completion {

    /**
     * Convert a date string (without timezone info, e.g. "2026-03-16T18:00")
     * to a UTC timestamp, interpreting the date in Moodle's server timezone.
     *
     * @param string $datestring The date string in Y-m-d\TH:i format
     * @return int|false UTC timestamp or false on failure
     */
    private static function date_to_timestamp($datestring) {
        if (empty($datestring)) {
            return false;
        }
        try {
            $tz = \core_date::get_server_timezone_object();
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $datestring, $tz);
            if ($dt === false) {
                $dt = new DateTime($datestring, $tz);
            }
            return $dt->getTimestamp();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Convert a formatted date string (d.m.Y H:i) to a UTC timestamp,
     * interpreting the date in Moodle's server timezone.
     *
     * @param string $datestring The date string in d.m.Y H:i format
     * @return int|false UTC timestamp or false on failure
     */
    private static function date_to_timestamp_formatted($datestring) {
        if (empty($datestring)) {
            return false;
        }
        try {
            $tz = \core_date::get_server_timezone_object();
            $dt = DateTime::createFromFormat('d.m.Y H:i', $datestring, $tz);
            if ($dt === false) {
                return false;
            }
            return $dt->getTimestamp();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check whether a restriction node is a feedback node.
     * Feedback nodes can be identified by three criteria:
     * 1. type field is "feedback"
     * 2. data.label contains "feedback"
     * 3. id contains "_feedback"
     *
     * @param array $rnode The restriction node
     * @return bool
     */
    private static function is_feedback_node($rnode) {
        $nodetype = $rnode['type'] ?? '';
        $nodelabel = $rnode['data']['label'] ?? '';
        $nodeid = $rnode['id'] ?? '';

        return ($nodetype === 'feedback')
            || (strpos($nodelabel, 'feedback') !== false)
            || (strpos($nodeid, '_feedback') !== false);
    }

    /**
     * Observer for node_finished event. Enrols users into child courses
     * if the child node's restrictions are met. If a timed restriction
     * has a future start date that prevents enrolment, an adhoc task is
     * scheduled to re-evaluate at that time.
     *
     * @param object $event
     */
    public static function enrol_child_courses($event) {
        global $DB;

        $userpath = $event->other['userpath']->json;
        // Ensure $userpath is always an object for consistent ->property access.
        if (is_string($userpath)) {
            $userpath = json_decode($userpath, false);
        } else if (is_array($userpath)) {
            $userpath = json_decode(json_encode($userpath), false);
        }

        $uniquechildcourses = [];
        foreach ($event->other['node'] as $signlenode) {
            $uniquechildcourses = array_merge($uniquechildcourses, $signlenode['childCourse']);
        }
        $uniquechildcourses = array_unique($uniquechildcourses);

        // Prepare a normalised copy of the userpath record for restriction checks.
        // The condition classes (parent_courses, specific_course, timed_duration)
        // expect $userpath->json to be an associative array, not a string or object.
        $userpathrecord = clone $event->other['userpath'];
        if (is_string($userpathrecord->json)) {
            $userpathrecord->json = json_decode($userpathrecord->json, true);
        } else if (is_object($userpathrecord->json)) {
            $userpathrecord->json = json_decode(json_encode($userpathrecord->json), true);
        }

        $firstenrollededit = false;
        $instances = [];

        foreach ($userpath->tree->nodes as &$node) {
            if (in_array($node->id, $uniquechildcourses)) {

                // Convert node to array for restriction evaluation.
                $nodearray = json_decode(json_encode($node), true);

                // Check restrictions using the existing course_restriction_status class.
                if (isset($nodearray['restriction']) && !empty($nodearray['restriction']['nodes'])) {
                    $restrictionresult = self::check_node_restrictions(
                        $nodearray,
                        $userpathrecord
                    );

                    if (!$restrictionresult['met']) {
                        // Restrictions not met. Schedule adhoc task for future start dates.
                        if (!empty($restrictionresult['next_start_date'])) {
                            self::schedule_enrolment_retry(
                                $event->other['userpath'],
                                $event->other['node'],
                                $restrictionresult['next_start_date']
                            );
                        }
                        continue; // Skip enrolment for this node.
                    }
                }

                // Restrictions met (or none defined) – proceed with enrolment.
                foreach ($node->data->course_node_id as $subscribecourse) {
                    if (isset($instances[$subscribecourse])) {
                        $instance = $instances[$subscribecourse];
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
                                'courseid' => $subscribecourse,
                                'enrol' => 'manual',
                            ]
                        );
                        $instances[$subscribecourse] = $instance;
                    }

                    if (!$instance) {
                        continue;
                    }

                    if (!isset($node->data->first_enrolled)) {
                        $node->data->first_enrolled = time();
                        adhoc_task_helper::set_scheduled_adhoc_tasks(
                            json_decode(json_encode($node), true),
                            $event->other['userpath']
                        );
                        $firstenrollededit = true;
                    }
                    $selectedrole = get_config('local_adele', 'enroll_as_setting');
                    $context = \context_course::instance($subscribecourse);
                    $isenrolled = is_enrolled($context, $event->other['userpath']->user_id);
                    if (!$isenrolled) {
                        $enrol->enrol_user($instance, $event->other['userpath']->user_id, $selectedrole);
                    }
                    self::enrol_user_group(
                        $userpath->tree->nodes,
                        $subscribecourse,
                        $event->other['userpath']->user_id
                    );
                }
            }
        }
        if ($firstenrollededit) {
            self::trigger_user_path_update_new_enrollments($event, $userpath);
        }
        self::is_user_path_completed($userpath->tree, $event->other['userpath']);
    }

    /**
     * Check whether a child node's restrictions are met using the existing
     * course_restriction_status class and its condition plugins.
     *
     * Uses the isbefore flag from the timed/timed_duration condition evaluation
     * to determine if a future start date exists, avoiding timezone inconsistencies
     * between date_to_timestamp() and the DateTime-based evaluation in timed.php.
     *
     * @param array $nodearray The child node as associative array
     * @param object $userpathrecord The user path record with ->json as array
     * @return array ['met' => bool, 'next_start_date' => string|null]
     */
    private static function check_node_restrictions($nodearray, $userpathrecord) {
        $result = [
            'met' => false,
            'next_start_date' => null,
        ];

        // Delegate to the existing restriction evaluation framework.
        $restrictionstatus = new course_restriction_status();
        $allcriteria = $restrictionstatus->get_restriction_status($nodearray, $userpathrecord);

        // Check for master override.
        if (isset($allcriteria['master']) && $allcriteria['master']) {
            $result['met'] = true;
            return $result;
        }

        // Check for manual restriction override.
        if (isset($allcriteria['manual']) && !empty($allcriteria['manual']['completed'])) {
            $result['met'] = true;
            return $result;
        }

        // Walk the restriction paths to check if at least one is fully satisfied.
        $restrictionnodes = $nodearray['restriction']['nodes'] ?? [];
        $nodemap = [];
        foreach ($restrictionnodes as $rnode) {
            $nodemap[$rnode['id']] = $rnode;
        }

        $paths = self::get_restriction_paths($restrictionnodes, $nodemap);
        $earlieststartdate = null;

        foreach ($paths as $path) {
            $pathmet = true;

            foreach ($path as $conditionnode) {
                $label = $conditionnode['data']['label'] ?? '';
                $conditionid = $conditionnode['id'];

                $conditionmet = self::is_condition_met($label, $conditionid, $allcriteria);

                if (!$conditionmet) {
                    $pathmet = false;

                    // Use the already-evaluated isbefore flag from course_restriction_status
                    // to determine if a future start date exists. This avoids timezone
                    // inconsistencies between date_to_timestamp() and the DateTime-based
                    // evaluation in timed.php.
                    if ($label === 'timed') {
                        $timeddata = $allcriteria['timed'][$conditionid] ?? null;
                        if ($timeddata && !empty($timeddata['isbefore'])) {
                            // isbefore = true means the start date has NOT been reached yet.
                            $startdate = $conditionnode['data']['value']['start'] ?? null;
                            if ($startdate) {
                                if ($earlieststartdate === null) {
                                    $earlieststartdate = $startdate;
                                } else if (strcmp($startdate, $earlieststartdate) < 0) {
                                    // Both dates in Y-m-d\TH:i format – string comparison
                                    // gives correct chronological order.
                                    $earlieststartdate = $startdate;
                                }
                            }
                        }
                    }

                    // Check for future start dates in timed_duration conditions.
                    if ($label === 'timed_duration') {
                        $timeddata = $allcriteria['timed_duration'][$conditionid] ?? null;
                        if ($timeddata && !empty($timeddata['isbefore'])) {
                            $startinfo = $timeddata['inbetween_info']['starttime'] ?? null;
                            if (is_string($startinfo) && !empty($startinfo)) {
                                if ($earlieststartdate === null) {
                                    $earlieststartdate = $startinfo;
                                }
                            }
                        }
                    }

                    break; // Rest of this path cannot be satisfied.
                }
            }

            if ($pathmet) {
                $result['met'] = true;
                return $result;
            }
        }

        $result['next_start_date'] = $earlieststartdate;
        return $result;
    }

    /**
     * Check whether a single condition is met based on the evaluation results
     * from the course_restriction_status class.
     *
     * @param string $label The condition type label
     * @param string $conditionid The condition node id
     * @param array $allcriteria The full evaluation results from course_restriction_status
     * @return bool
     */
    private static function is_condition_met($label, $conditionid, $allcriteria) {
        if (!isset($allcriteria[$label])) {
            // Condition not evaluated – treat as NOT met to prevent premature enrolment.
            return false;
        }

        $criteria = $allcriteria[$label];

        // Master returns a plain boolean.
        if (is_bool($criteria)) {
            return $criteria;
        }

        // Conditions indexed by condition node id.
        if (isset($criteria[$conditionid])) {
            return !empty($criteria[$conditionid]['completed']);
        }

        // Flat structure (manual).
        if (isset($criteria['completed'])) {
            return !empty($criteria['completed']);
        }

        return false;
    }

    /**
     * Extract all restriction paths from the restriction nodes.
     * Each path is a chain of condition nodes starting from 'starting_condition',
     * following childCondition links, and skipping feedback nodes.
     *
     * Feedback nodes are identified by three criteria (OR):
     * 1. type field is "feedback"
     * 2. data.label contains "feedback"
     * 3. id contains "_feedback"
     *
     * @param array $restrictionnodes All restriction nodes
     * @param array $nodemap Lookup map of restriction nodes by id
     * @return array Array of paths, each path is an array of condition nodes
     */
    private static function get_restriction_paths($restrictionnodes, $nodemap) {
        $paths = [];

        foreach ($restrictionnodes as $rnode) {
            $parentconditions = $rnode['parentCondition'] ?? [];
            if (!is_array($parentconditions)) {
                $parentconditions = [$parentconditions];
            }

            if (in_array('starting_condition', $parentconditions)) {
                $path = [];
                $current = $rnode;

                while ($current) {
                    // Only add non-feedback nodes to the path.
                    if (!self::is_feedback_node($current)) {
                        $path[] = $current;
                    }

                    // Find the next non-feedback condition in the chain.
                    $nextid = null;
                    $childconditions = $current['childCondition'] ?? [];
                    if (is_array($childconditions)) {
                        foreach ($childconditions as $childid) {
                            if (isset($nodemap[$childid])) {
                                if (!self::is_feedback_node($nodemap[$childid])) {
                                    $nextid = $childid;
                                    break;
                                }
                            }
                        }
                    }

                    $current = ($nextid && isset($nodemap[$nextid])) ? $nodemap[$nextid] : null;
                }

                if (!empty($path)) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /**
     * Schedule an adhoc task to re-trigger the evaluation and enrolment
     * when a timed restriction's start date is reached.
     *
     * @param object $userpathrecord The user path DB record
     * @param array $completednodes The completed node data from the event
     * @param string $startdate The future start date string
     */
    private static function schedule_enrolment_retry($userpathrecord, $completednodes, $startdate) {
        // Try Y-m-d\TH:i format first (from timed restriction value).
        $timestamp = self::date_to_timestamp($startdate);
        if (!$timestamp) {
            // Try d.m.Y H:i format (from timed_duration inbetween_info).
            $timestamp = self::date_to_timestamp_formatted($startdate);
        }

        // Safety net: if date_to_timestamp returns false or a past timestamp
        // (due to timezone inconsistencies between PHP default timezone and
        // Moodle server timezone), but we know the timed condition said
        // isbefore=true, schedule for 2 minutes from now as fallback.
        if (!$timestamp || $timestamp <= time()) {
            $runtime = time() + 120;
        } else {
            // Schedule 2 minutes after the start date to ensure the condition is met.
            $runtime = $timestamp + 120;
        }

        $taskdata = new stdClass();
        $taskdata->learning_path_id = $userpathrecord->learning_path_id;
        $taskdata->user_id = $userpathrecord->user_id;
        $taskdata->userpath = $userpathrecord;
        $taskdata->time = $runtime . $userpathrecord->id;

        $task = new update_user_path();
        $task->set_userid($taskdata->user_id);
        $task->set_custom_data($taskdata);
        $task->set_next_run_time($runtime);

        \core\task\manager::reschedule_or_queue_adhoc_task($task);
    }

    /**
     * Check if the user path is fully completed and mark mod_adele
     * activity completion accordingly.
     *
     * @param object $userpath
     * @param object $learningpath
     * @return bool
     */
    private static function is_user_path_completed($userpath, $learningpath) {
        $paths = self::get_possible_paths($userpath->nodes);
        if (self::check_user_path_completed($userpath, $paths)) {
            global $DB;

            $adeleinstances = $DB->get_records(
                'adele',
                ['learningpathid' => $learningpath->learning_path_id]
            );
            if (!$adeleinstances) {
                return false;
            }
            $completed = false;
            foreach ($adeleinstances as $adeleinstance) {
                $cm = get_coursemodule_from_instance('adele', $adeleinstance->id, $adeleinstance->course);
                if (!$cm) {
                    continue;
                }
                $completion = new completion_info(get_course($adeleinstance->course));
                if (!$completion->is_enabled($cm)) {
                    continue;
                }
                if (!$adeleinstance->completionlearningpathfinished) {
                    continue;
                }
                $completion->update_state($cm, COMPLETION_COMPLETE, $learningpath->user_id);
                $completed = true;
            }
            return $completed;
        }
        return false;
    }

    /**
     * Find all possible paths through the learning path tree.
     *
     * @param array $nodes
     * @return array
     */
    public static function get_possible_paths($nodes) {
        $startingcondition = "starting_node";
        $paths = [];
        foreach ($nodes as $node) {
            if (
                isset($node->parentCourse) &&
                is_array($node->parentCourse) &&
                in_array($startingcondition, $node->parentCourse)
            ) {
                $paths = array_merge($paths, self::findpaths($node, [], $nodes));
            }
        }
        return $paths;
    }

    /**
     * Check if at least one complete path through the learning path is finished.
     *
     * @param object $userpath
     * @param array $paths
     * @return bool
     */
    public static function check_user_path_completed($userpath, $paths) {
        foreach ($paths as $path) {
            $validpath = true;
            foreach ($path as $nodeid) {
                $node = self::findnodebyid($nodeid, $userpath->nodes);
                if (
                    isset($node->data->completion->completionnode->valid) &&
                    !$node->data->completion->completionnode->valid
                ) {
                    $validpath = false;
                    break;
                }
            }
            if ($validpath) {
                return $validpath;
            }
        }
        return false;
    }

    /**
     * Find a node by its id.
     *
     * @param int $id
     * @param array $nodes
     * @return mixed
     */
    public static function findnodebyid($id, $nodes) {
        foreach ($nodes as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Recursively find all paths from a given node to leaf nodes.
     *
     * @param object $node
     * @param array $currentpath
     * @param array $nodes
     * @return array
     */
    public static function findpaths($node, $currentpath, $nodes) {
        $currentpath[] = $node->id;

        if (empty($node->childCourse)) {
            return [$currentpath];
        }

        $allpaths = [];
        foreach ($node->childCourse as $childid) {
            $childnode = self::findnodebyid($childid, $nodes);
            if ($childnode) {
                $childpaths = self::findpaths($childnode, $currentpath, $nodes);
                $allpaths = array_merge($allpaths, $childpaths);
            }
        }

        return $allpaths;
    }

    /**
     * Trigger a user_path_updated event after new enrolments have been
     * recorded in the tree (first_enrolled timestamps).
     *
     * @param object $event
     * @param object $userpath
     */
    private static function trigger_user_path_update_new_enrollments($event, $userpath) {
        global $DB;
        $latestrecord = $DB->get_record(
            'local_adele_path_user',
            [
              'status' => 'active',
              'user_id' => $event->other['userpath']->user_id,
              'learning_path_id' => $event->other['userpath']->learning_path_id,
            ]
        );

        if (!$latestrecord) {
            return;
        }

        $fulljson = json_decode($latestrecord->json, true);
        $updatedtree = json_decode(json_encode($userpath), true);
        if (isset($updatedtree['tree'])) {
            $fulljson['tree'] = $updatedtree['tree'];
        } else {
            $fulljson['tree'] = $updatedtree;
        }
        $latestrecord->json = $fulljson;

        $eventsingle = user_path_updated::create([
            'objectid' => $event->other['userpath']->id,
            'context' => context_system::instance(),
            'other' => [
                'userpath' => $latestrecord,
            ],
        ]);
        $eventsingle->trigger();
    }

    /**
     * Enrol user into groups in the destination course.
     *
     * @param array $nodes
     * @param int $newcourseid
     * @param int $userid
     */
    private static function enrol_user_group($nodes, $newcourseid, $userid) {
        $startinggroups = self::get_groups_for_multiple_courses($nodes);
        $currentgroups = groups_get_all_groups($newcourseid);
        $currentgroupnames = [];
        if (!empty($currentgroups)) {
            foreach ($currentgroups as $group) {
                $currentgroupnames[] = $group->name;
            }
        }
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
     * Check if a group exists in the destination course and add the user.
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
        if (groups_is_member($group->id, $userid)) {
            if (!in_array($group->name, $currentgroupnames)) {
                $newgroupdata = new stdClass();
                $newgroupdata->courseid = $newcourseid;
                $newgroupdata->name = $group->name;
                $newgroupdata->description = isset($group->description) ? $group->description : '';

                $newgroupid = groups_create_group($newgroupdata);
                groups_add_member($newgroupid, $userid);
            } else {
                $existinggroupid = array_search($group->name, array_column($currentgroups, 'name', 'id'));
                if ($existinggroupid) {
                    groups_add_member($existinggroupid, $userid);
                }
            }
        }
    }

    /**
     * Get all groups for courses in starting nodes.
     *
     * @param array $nodes Array of nodes
     * @return array Groups indexed by course id
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
