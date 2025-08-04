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
 * Entities Class to display list of entity records.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use local_adele\event\learnpath_created;
use local_adele\event\learnpath_updated;
use stdClass;
use context_system;
use context_course;
use local_adele\event\learnpath_deleted;
use local_adele\event\user_path_updated;
use local_adele\helper\user_path_relation;
use core_completion\progress;
use Exception;
use moodle_url;

/**
 * Class learning_paths
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_paths {

    /**
     * Entities constructor.
     */
    public function __construct() {

    }

    /**
     * Save learning path.
     *
     * @param array $params
     * @return mixed
     */
    public static function save_learning_path($params) {
        global $DB, $USER;
        $data = new stdClass();
        $data->name = $params['name'];
        $data->description = $params['description'];
        $data->image = $params['image'];
        $data->timemodified = time();
        $data->json = $params['json'];
        $id = 0;
        if ($params['learningpathid'] == 0) {
            $data->timecreated = time();
            $data->createdby = $params['userid'] ?? 0;
            $id = $DB->insert_record('local_adele_learning_paths', (object)$data);
            // Trigger catscale created event.
            $event = learnpath_created::create([
                'objectid' => $id,
                'context' => context_system::instance(),
                'other' => [
                    'learningpathname' => $data->name,
                    'learningpathid' => $id,
                    'userid' => $data->createdby,
                ],
            ]);
        } else {
            $id = $params['learningpathid'];
            $data->id = $id;
            $DB->update_record('local_adele_learning_paths', $data);
            // Trigger catscale created event.
            $event = learnpath_updated::create([
                'objectid' => $data->id,
                'context' => context_system::instance(),
                'other' => [
                    'learningpathname' => $data->name,
                    'learningpathid' => $data->id,
                    'userid' => $USER->id,
                    'json' => $data->json,
                ],
            ]);
        }
        $event->trigger();

        if ($id > 0) {
            return $DB->get_record(
                'local_adele_learning_paths',
                ['id' => $id]
            );
        }
        return 0;
    }

    /**
     * Save learning path.
     *
     * @param array $params
     * @return bool
     */
    public static function update_learning_path($params) {
        global $DB;
        $data = new stdClass();
        $data->id = $params['id'];
        $data->json = $params['json'];
        $data->createdby = '100';
        $data->timemodified = time();
        return $DB->update_record('local_adele_learning_paths', $data);
    }

    /**
     * Get all learning paths.
     *
     * @param bool $hascapability
     * @param array $sessionvalue
     * @return array
     */
    public static function get_learning_paths($hascapability, $sessionvalue) {
        global $DB;
        $response = $DB->get_records(
          'local_adele_learning_paths',
          null,
          '' ,
          'id, name, description, image, visibility'
        );
        $learningpaths = [
            'edit' => [],
            'view' => [],
        ];
        if ($hascapability) {
            $learningpaths['edit'] = array_map(fn($a) => (array)$a, $response);
        } else {
            foreach ($response as $lpid => $lp) {
                if (isset($sessionvalue[$lpid])) {
                    $learningpaths['edit'][] = (array) $lp;
                } else {
                    $learningpaths['view'][] = (array) $lp;
                }
            }
        }
        return $learningpaths;
    }

    /**
     * Get all learning paths.
     *
     * @return array
     */
    public static function get_editable_learning_paths() {
        global $DB, $USER;
        $sql = "SELECT lp.id, lp.id as learningpathid, lp.name
            FROM {local_adele_learning_paths} lp";

        if (!is_siteadmin()) {
            $sql .= "
                    JOIN  {local_adele_lp_editors} lpe ON lp.id = lpe.learningpathid
                    WHERE lpe.userid = :userid ";
            $params = ['userid' => $USER->id];
        } else {
            $params = [];
        }

        $learningpaths = $DB->get_records_sql($sql, $params);
        return $learningpaths;
    }

    /**
     * Get one specific learning path.
     *
     * @param array $params
     * @return array
     */
    public static function get_learning_path($params) {
        if ($params['learningpathid'] == 0) {
            $learningpath = [
                'id' => 0,
                'name' => '',
                'description' => '',
                'image' => '',
                'json' => '',
            ];
            return $learningpath;
        }
        global $DB;
        $learningpath = $DB->get_record('local_adele_learning_paths', ['id' => $params['learningpathid']],
            'id, name, description, image, json');
        $learningpath = self::get_image_paths($learningpath);
        return (array) $learningpath;
    }

    /**
     * Get one specific learning path.
     *
     * @param object $learningpath
     * @return object
     */
    public static function get_image_paths($learningpath) {
        if ($learningpath) {
            $learningpathjson = json_decode($learningpath->json);
            foreach ($learningpathjson->tree->nodes as $nodes) {
                $imagepaths = [];
                foreach ($nodes->data->course_node_id as $coursenodeid) {
                    $context = context_course::instance($coursenodeid);
                    $fs = get_file_storage();
                    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'itemid, filepath, filename', false);
                    if ($file = reset($files)) {
                        $path = moodle_url::make_pluginfile_url(
                          $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                          $file->get_itemid(), $file->get_filepath(), $file->get_filename()
                        );
                        $imagepaths[$coursenodeid] = str_replace('/0/', '/', $path->out());
                    }
                }
                $nodes->data->imagepaths = $imagepaths;
            }
            $learningpath->json = json_encode($learningpathjson);
        }
        return $learningpath;
    }
    /**
     * Get one specific learning path by id.
     *
     * @param string $id
     * @return object
     */
    public static function get_learning_path_by_id($id) {
        global $DB;
        return $DB->get_record('local_adele_learning_paths', ['id' => $id]);
    }

    /**
     * Duplicate a learning path.
     *
     * @param array $params
     * @return array
     */
    public static function duplicate_learning_path($params) {
        global $DB, $USER;

        $learningpath = $DB->get_record('local_adele_learning_paths', ['id' => $params['learningpathid']],
            'name, description, image, json');
        if (isset($learningpath)) {
            $copyindex = 1;
            $copiedname = $learningpath->name .= ' copy';
            while (true) {
                if ($copyindex > 1) {
                    $existinglearningpath = $DB->get_record(
                        'local_adele_learning_paths',
                        ['name' => $copiedname . ' ' . $copyindex], 'id'
                    );
                } else {
                    $existinglearningpath = $DB->get_record('local_adele_learning_paths', ['name' => $copiedname], 'id');
                }
                if (!$existinglearningpath) {
                    break;
                }
                $copyindex++;
            }
            $learningpath->id = null;
            $learningpath->createdby = $USER->id;
            if ($copyindex > 1) {
                $learningpath->name .= ' ' . $copyindex;
            }
            $learningpath->timecreated = time();
            $learningpath->timemodified = time();
            $id = $DB->insert_record('local_adele_learning_paths', $learningpath);
            // Trigger catscale created event.
            $event = learnpath_created::create([
                'objectid' => $id,
                'context' => context_system::instance(),
                'other' => [
                    'learningpathname' => $learningpath->name,
                    'learningpathid' => $id,
                    'userid' => $USER->id,
                ],
            ]);
            $event->trigger();
            return ['success' => true];
        }
        return ['success' => false];
    }

    /**
     * Delete a learning path.
     *
     * @param array $params
     * @return array
     */
    public static function delete_learning_path($params) {
        global $DB, $USER;

        $result = $DB->delete_records('local_adele_learning_paths', ['id' => $params['learningpathid']]);
        if ($result) {
            // Trigger catscale created event.
            $event = learnpath_deleted::create([
                'objectid' => $params['learningpathid'],
                'context' => context_system::instance(),
                'other' => [
                    'learningpathname' => $params['name'] ?? 'TBD',
                    'learningpathid' => $params['learningpathid'],
                    'userid' => $USER->id,
                ],
            ]);
            $event->trigger();
            return [
                'success' => true,
            ];
        } else {
            return [
                'success' => false,
            ];
        }
    }

    /**
     * Get user path relations.
     *
     * @param array $data
     * @return array
     */
    public static function get_learning_user_relations($data) {
        global $DB;

        $params = [
            'learning_path_id' => (int)$data['learningpathid'],
            'course_id' => (int)$data['courseid'],
        ];

        $sql = "SELECT lpu.user_id, lpu.status, lpu.json, usr.username,
            usr.firstname, usr.lastname, usr.email
            FROM {local_adele_path_user} lpu
            LEFT JOIN {user} usr ON lpu.user_id = usr.id
            WHERE lpu.learning_path_id = :learning_path_id
            AND lpu.course_id = :course_id
            AND lpu.status = 'active'";

        $userpathlist = [];
        $records = $DB->get_records_sql($sql, $params);
        try {
            foreach ($records as $record) {
                $record->json = json_decode($record->json);
                $progress = self::getnodeprogress($record->json);
                $userpathlist[] = [
                    'id' => (int)$record->user_id ?? null,
                    'username' => $record->username ?? null,
                    'firstname' => $record->firstname ?? null,
                    'lastname' => $record->lastname ?? null,
                    'progress' => $progress ?? null,
                ];
            }
            usort($userpathlist, function($a, $b) {
                if ($a['progress']['completed_nodes'] === $b['progress']['completed_nodes']) {
                    return $b['progress']['progress'] <=> $a['progress']['progress'];
                }
                return $b['progress']['completed_nodes'] <=> $a['progress']['completed_nodes'];
            });
            $rank = 1;
            $prevuser = null;
            foreach ($userpathlist as $index => &$user) {
                if ($prevuser &&
                    $prevuser['progress']['completed_nodes'] === $user['progress']['completed_nodes'] &&
                    $prevuser['progress']['progress'] === $user['progress']['progress']) {
                    $user['rank'] = $prevuser['rank'];
                } else {
                    $user['rank'] = $rank;
                }
                $prevuser = $user;
                $rank++;
            }
            return $userpathlist;
        } catch (Exception $e) {
            debugging('Error in getnodeprogress: ' . $e->getMessage());
            return [
                'error' => 'An error occurred while calculating node progress',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user path relation.
     *
     * @param object $relationnodes
     * @return array
     */
    public static function getnodeprogress($relationnodes) {
        try {
            $validnodes = 0;
            $totalnodes = 0;
            if (isset($relationnodes->user_path_relation)) {
                foreach ($relationnodes->user_path_relation as $key => $node) {
                    if (strstr($key, '_module') == false) {
                        if ($node->completionnode->valid) {
                            $validnodes++;
                        }
                        $totalnodes++;
                    }
                }
            }
            $pathnodes = $relationnodes->tree->nodes ?? null;
            $startingcondition = "starting_node";
            $paths = [];
            if ($pathnodes) {
                foreach ($pathnodes as $node) {
                    $node = (array)$node;
                    if (
                        isset($node['parentCourse']) &&
                        is_array($node['parentCourse']) &&
                        in_array($startingcondition, $node['parentCourse'])
                    ) {
                        self::findpaths($node, [], $paths, $pathnodes);
                    }
                }
            }
            // Filter paths ending with childCondition null.
            $filteredpaths = array_filter($paths, function ($path) use ($pathnodes) {
                $lastnode = self::findNodeById(end($path), $pathnodes);
                return isset($lastnode['childCourse']) && empty($lastnode['childCourse']);
            });
            $progress = 0;
            foreach ($filteredpaths as $filteredpath) {
                $completednodes = 0;
                foreach ($filteredpath as $node) {
                    if ($relationnodes->user_path_relation->{$node}->completionnode->valid) {
                        $completednodes++;
                    }
                }
                $pathprogression = $completednodes / count($filteredpath);
                if ($pathprogression > $progress) {
                    $progress = $pathprogression;
                }
            }
            return [
                'completed_nodes' => $validnodes,
                'progress' => round(100 * $progress, 2),
            ];

        } catch (Exception $e) {
            debugging('Error in getnodeprogress: ' . $e->getMessage());
            return [
                'error' => 'An error occurred while calculating node progress',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find a paths in learning path.
     *
     * @param array $node
     * @param array $currentpath
     * @param array $paths
     * @param array $nodes
     * @return array
     */
    public static function findpaths($node, $currentpath, &$paths, $nodes) {
        $currentpath[] = $node['id'];

        if (isset($node['childCourse']) && empty($node['childCourse'])) {
            $paths[] = $currentpath;
            return;
        }

        foreach ($node['childCourse'] as $childid) {
            $childnode = self::findnodebyid($childid, $nodes);
            if ($childnode) {
                self::findpaths($childnode, $currentpath, $paths, $nodes);
            }
        }
    }

    /**
     * Find a node by its id.
     *
     * @param int $id
     * @param array $nodes
     * @return array
     */
    public static function findnodebyid($id, $nodes) {
        global $data;
        foreach ($nodes as $node) {
            $node = (array)$node;
            if ($node['id'] === $id) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Get user path relation.
     *
     * @param array $data
     * @return array
     */
    public static function get_learning_user_relation($data) {
        global $DB;

        $params = [
            'learning_path_id' => (int)$data['learningpathid'],
            'userpathid' => (int)$data['userpathid'],
            'courseid' => (int)$data['courseid'],
        ];

        $sql = "SELECT lpu.id, lpu.user_id, lpu.json, lpu.last_seen_by_owner, usr.username,
            usr.firstname, usr.lastname, usr.email, lpu.json, lap.image
            FROM {local_adele_path_user} lpu
            LEFT JOIN {user} usr ON lpu.user_id = usr.id
            LEFT JOIN {local_adele_learning_paths} lap ON lpu.learning_path_id = lap.id
            WHERE lpu.learning_path_id = :learning_path_id
            AND lpu.status = 'active'
            AND lpu.course_id = :courseid
            AND lpu.user_id = :userpathid ";

        $record = $DB->get_record_sql($sql, $params);
        if ($record) {
            $record->json = self::addnodemanualcondition($record->json, $record->user_id);
            return (array)$record;
        }
        return [
            'id' => 0,
            'user_id' => 0,
            'username' => 'not found',
            'firstname' => 'not found',
            'lastname' => 'not found',
            'email' => 'not found',
            'json' => 'not found',
            'last_seen_by_owner' => 'not found',
            'image' => 'not found',
        ];
    }

    /**
     * Get user path relation.
     *
     * @param string $json
     * @param string $userid
     * @return array
     */
    public static function addnodemanualcondition($json, $userid) {
        $json = json_decode($json);
        foreach ($json->tree->nodes as $node) {
            $node->draggable = false;
            $node->deletable = false;
            $node->data->completion = $json->user_path_relation->{$node->id} ?? false;
            $node->data->manual = false;
            $node = self::checkmanualcondition($node);
            $node = self::checknodeprogression($node, $userid);
        }
        return json_encode($json);
    }

    /**
     * Get user path relation.
     *
     * @param object $node
     * @return array
     */
    public static function checkmanualcondition($node) {
        $conditions = [
            'completion',
            'restriction',
        ];
        foreach ($conditions as $condition) {
            if ($node->{$condition} && $node->{$condition}->nodes) {
                foreach ($node->{$condition}->nodes as $conditionnode) {
                    if ($conditionnode->data->label == 'manual') {
                        $node->data->{ 'manual' . $condition} = true;
                    }
                }
            }
        }
        return $node;
    }


    /**
     * Duplicate a learning path.
     *
     * @param array $params
     * @return array
     */
    public static function save_learning_user_relation($params) {
        $userpathrelation = new user_path_relation();
        $courseid = $params['courseid'] ?: 0;
        $params = json_decode($params['params']);
        $userpath = $userpathrelation->get_user_path_relation($params->route->learningpathId, $params->route->userId, $courseid);
        if ($userpath) {
            $userpath->json = json_decode($userpath->json, true);
            $userpath->json['tree']['nodes'] = json_decode(json_encode($params->nodes), true);
            $event = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
                    'courseid' => $courseid,
                ],
            ]);
            $event->trigger();
            return ['success' => true];
        }
        return ['success' => false];
    }

    /**
     * Get user path relation.
     *
     * @param object $node
     * @param string $userid
     * @return stdClass
     */
    public static function checknodeprogression($node, $userid) {
        $courseprogrressarray = [];
        $completioncolumnprogress = [];
        foreach ($node->data->course_node_id as $coursenodeid) {
            $course = learning_path_update::get_course($coursenodeid);
            $courseprogrressarray[$coursenodeid] = (int) progress::get_course_progress_percentage($course, $userid);
        }
        arsort($courseprogrressarray);
        $sortedcourseprogress = array_values($courseprogrressarray);
        if ($node->data->completion->master->completion) {
            $completioncolumnprogress[] = 100;
        } else {
            foreach ($node->completion->nodes as $completionnode) {
                if (
                    isset($completionnode->parentCondition) &&
                    $completionnode->parentCondition[0] == 'starting_condition'
                ) {
                    $currentcondition = $completionnode;
                    $completionprogressarray = [];
                    while ($currentcondition) {
                        $completionnodeid = $currentcondition->id;
                        $label = $currentcondition->data->label;
                        $progress = 0;
                        if (
                            $label == 'manual'
                        ) {
                            if (
                                isset($node->data->completion->completioncriteria->$label->completed) &&
                                $node->data->completion->completioncriteria->$label->completed == true
                            ) {
                                $progress = 100;
                            }
                        } else if (
                            $label == 'catquiz' ||
                            $label == 'modquiz'
                        ) {
                            if (
                                isset($node->data->completion->completioncriteria->$label->completed->$completionnodeid) &&
                                $node->data->completion->completioncriteria->$label->completed->$completionnodeid == true
                            ) {
                                $progress = 100;
                            }
                        } else if ($label == 'course_completed') {
                            $minvalue = $currentcondition->data->value->min_courses ?? 1;
                            $positioncount = 0;
                            $nodecompletionprogress = 0;
                            while ($positioncount < $minvalue) {
                                $nodecompletionprogress += $sortedcourseprogress[$positioncount];
                                $positioncount++;
                            }
                            $progress = $nodecompletionprogress / $minvalue;
                        }
                        $completionprogressarray[] = $progress;
                        $currentcondition = self::searchnestedarray(
                            $node->completion->nodes,
                            $currentcondition->childCondition,
                            'id'
                        );
                    }

                    $completioncolumnprogress[] = array_sum($completionprogressarray) / count($completionprogressarray);
                }
            }
        }
        arsort($completioncolumnprogress);
        $completioncolumnprogress = array_values($completioncolumnprogress);
        $node->data->progress = round($completioncolumnprogress[0], 2);
        return $node;
    }



    /**
     * Observer for course completed
     *
     * @param array $haystack
     * @param array $needle
     * @param string $key
     * @param bool $returnfeedack
     * @return mixed
     */
    public static function searchnestedarray($haystack, $needle, $key, $returnfeedack = false) {
        foreach ($haystack as $item) {
            foreach ($needle as $need) {
                if (strpos($need, '_feedback') == $returnfeedack) {
                    if (isset($item->$key) && $item->$key === $need) {
                        return $item;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Get user path relation.
     *
     * @param array $params
     * @return array
     */
    public static function update_learning_user_relation($params) {
        global $DB;
        $record = new stdClass();
        $record->id = $params['lpuserpathid'];
        $record->last_seen_by_owner = time();
        $DB->update_record('local_adele_path_user', $record);

        return ['last_seen' => $record->last_seen_by_owner];
    }

    /**
     * Central function to check access to learning path.
     *
     * @return array
     *
     */
    public static function return_learningpaths() {

        global $USER, $DB;

        $cache = \cache::make('local_adele', 'navisteacher');
        // $records = $cache->get('localadeleeditor');

            // If we don't have the capability, we check with cache if we are editor.
        $params = [
            'userid' => (int)$USER->id,
        ];

        $sql = "SELECT lpe.learningpathid
            FROM {local_adele_lp_editors} lpe
            WHERE lpe.userid = :userid";
        $records = $DB->get_records_sql($sql, $params);

        $cache->set('localadeleeditor', $records);


        return $records ?? [];
    }

    /**
     * Just checks access.
     *
     * @return bool
     *
     */
    public static function check_access() {

        // First fast check if we show the button in the navbar.
        if (has_capability('local/adele:canmanage', context_system::instance())) {
            $iseditor = true;
        } else {
            $learningpaths = self::return_learningpaths();
            $iseditor = !empty($learningpaths);
        }

        return $iseditor;
    }
}
