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
     * @return bool
     */
    public static function save_learning_path($params) {
        global $DB, $USER;
        $data = new stdClass;
        $data->name = $params['name'];
        $data->description = $params['description'];
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
                'objectid' => $data->id ,
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
        $data = new stdClass;
        $data->id = $params['id'];
        $data->json = $params['json'];
        $data->createdby = '100';
        $data->timemodified = time();
        return $DB->update_record('local_adele_learning_paths', $data);
    }

    /**
     * Get all learning paths.
     *
     * @return array
     */
    public static function get_learning_paths() {
        global $DB;
        $learningpaths = $DB->get_records('local_adele_learning_paths', null, '' , 'id, name, description');
        return array_map(fn($a) => (array)$a, $learningpaths);
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
                'json' => '',
            ];
            return $learningpath;
        }
        global $DB;
        $learningpath = $DB->get_record('local_adele_learning_paths', ['id' => $params['learningpathid']],
            'id, name, description, json');
        $learningpath = self::get_image_paths($learningpath);
        return (array) $learningpath;
    }

    public static function get_image_paths($learningpath) {
        $learningpathjson = json_decode($learningpath->json);
        foreach ($learningpathjson->tree->nodes as $nodes) {
            $imagepaths = [];
            foreach ($nodes->data->course_node_id as $coursenodeid) {
                $context = context_course::instance($coursenodeid);
                $fs = get_file_storage();
                $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'itemid, filepath, filename', false);
                if ($file = reset($files)) {
                    $path = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                                                             $file->get_itemid(), $file->get_filepath(), $file->get_filename());
                    $imagepaths[$coursenodeid] = str_replace('/0/', '/', $path->out());
                }
            }
            $nodes->data->imagepaths = $imagepaths;
        }
        $learningpath->json = json_encode($learningpathjson);
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
            'name, description, json');

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
        ];

        $sql = "SELECT lpu.user_id, lpu.status, lpu.json, usr.username,
            usr.firstname, usr.lastname, usr.email
            FROM {local_adele_path_user} lpu
            LEFT JOIN {user} usr ON lpu.user_id = usr.id
            WHERE lpu.learning_path_id = :learning_path_id
            AND lpu.status = 'active'";

        $userpathlist = [];
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $record->json = json_decode($record->json);
            $progress = self::getnodeprogress($record->json);
            $userpathlist[] = [
                'id' => (int)$record->user_id,
                'username' => $record->username,
                'firstname' => $record->firstname,
                'lastname' => $record->lastname,
                'progress' => $progress,
            ];
        }
        return $userpathlist;
    }

    /**
     * Get user path relation.
     *
     * @param object $relationnodes
     * @return array
     */
    public static function getnodeprogress($relationnodes) {
        $validnodes = 0;
        $totalnodes = 0;
        foreach ($relationnodes->user_path_relation as $key => $node) {
            if (strstr($key , '_module') == false) {
                if ($node->completionnode->valid) {
                    $validnodes++;
                }
                $totalnodes++;
            }
        }
        $pathnodes = $relationnodes->tree->nodes;
        $startingcondition = "starting_node";
        $paths = [];

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
            'completed_nodes' => $validnodes . '/' . $totalnodes,
            'progress' => round(100 * $progress, 2),
        ];
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
        ];

        $sql = "SELECT lpu.id, lpu.user_id, lpu.json, usr.username,
            usr.firstname, usr.lastname, usr.email, lpu.json
            FROM {local_adele_path_user} lpu
            LEFT JOIN {user} usr ON lpu.user_id = usr.id
            WHERE lpu.learning_path_id = :learning_path_id
            AND lpu.status = 'active'
            AND lpu.user_id = :userpathid ";

        $record = $DB->get_record_sql($sql, $params);
        if ($record) {
            $record->json = self::addnodemanualcondition($record->json, $record->user_id);
            return (array)$record;
        }
        return [
            'user_id' => 0,
            'username' => 'not found',
            'firstname' => 'not found',
            'lastname' => 'not found',
            'email' => 'not found',
            'json' => 'not found',
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
        $params = json_decode($params['params']);
        $userpath = $userpathrelation->get_user_path_relation($params->route->learningpathId, $params->route->userId);
        if ($userpath) {
            $userpath->json = json_decode($userpath->json, true);
            $userpath->json['tree']['nodes'] = json_decode(json_encode($params->nodes), true);
            $event = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
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
     * @param String $userid
     * @return array
     */
    public static function checknodeprogression($node, $userid) {
        $progress = 0;
        foreach ($node->data->course_node_id as $coursenodeid) {
            $course = get_course($coursenodeid);
            $tmpprogress = (int) progress::get_course_progress_percentage($course, $userid);
            if ($tmpprogress > $progress) {
                $progress = $tmpprogress;
            }
        }
        $node->data->progress = $progress;
        return $node;
    }
}
