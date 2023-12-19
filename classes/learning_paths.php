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
use local_adele\event\learnpath_deleted;
use local_adele\event\user_path_updated;
use local_adele\helper\user_path_relation;

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

        if ($params['learninggoalid'] == 0) {
            $data->timecreated = time();
            $data->createdby = $params['userid'];
            $id = $DB->insert_record('local_adele_learning_paths', (object)$data);
            // Trigger catscale created event.
            $event = learnpath_created::create([
                'objectid' => $id,
                'context' => context_system::instance(),
                'other' => [
                    'learningpathname' => $data->name,
                    'learningpathid' => $id,
                    'userid' => $data->createdb,
                ],
            ]);
        } else {
            $data->id = $params['learninggoalid'];
            $id = $DB->update_record('local_adele_learning_paths', $data);
            // Trigger catscale created event.
            $event = learnpath_updated::create([
                'objectid' => $id,
                'context' => context_system::instance(),
                'other' => [
                    'learningpathname' => $data->name,
                    'learningpathid' => $id,
                    'userid' => $USER->id,
                ],
            ]);
        }
        $event->trigger();

        if ($id > 0) {
            return 1;
        }
        return 0;
    }

    /**
     * Get all learning paths.
     *
     * @return array
     */
    public static function get_learning_paths() {
        global $DB;
        $learninggoals = $DB->get_records('local_adele_learning_paths', null, '' , 'id, name, description');
        return array_map(fn($a) => (array)$a, $learninggoals);
    }

    /**
     * Get one specific learning path.
     *
     * @param array $params
     * @return array
     */
    public static function get_learning_path($params) {
        if ($params['learninggoalid'] == 0) {
            $learninggoal = [
                'id' => 0,
                'name' => '',
                'description' => '',
                'json' => '',
            ];
            return [$learninggoal];
        }
        global $DB;
        $learninggoal = $DB->get_record('local_adele_learning_paths', ['id' => $params['learninggoalid']],
            'id, name, description, json');
        return [(array) $learninggoal];
    }

    /**
     * Duplicate a learning path.
     *
     * @param array $params
     * @return array
     */
    public static function duplicate_learning_path($params) {
        global $DB, $USER;

        $learningpath = $DB->get_record('local_adele_learning_paths', ['id' => $params['learninggoalid']],
            'name, description, json');

        if (isset($learningpath)) {
            $learningpath->id = null;
            $learningpath->createdby = $USER->id;
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

        $result = $DB->delete_records('local_adele_learning_paths', ['id' => $params['learninggoalid']]);
        if ($result) {
            // Trigger catscale created event.
            $event = learnpath_deleted::create([
                'objectid' => $params['learninggoalid'],
                'context' => context_system::instance(),
                'other' => [
                    'learningpathname' => $params['name'] ?? 'TBD',
                    'learningpathid' => $params['learninggoalid'],
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
     * @param array $params
     * @return array
     */
    public static function get_learning_user_relations($data) {
        global $DB;

        $params = [
            'learning_path_id' => (int)$data['learninggoalid']
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
            $progress = 0;
            $record->json = json_decode($record->json);
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
     * @param array $params
     * @return array
     */
    public static function get_learning_user_relation($data) {
        global $DB;

        $params = [
            'learning_path_id' => (int)$data['learningpathid'],
            'userpathid' => (int)$data['userpathid'],
        ];

        $sql = "SELECT lpu.user_id, lpu.json, usr.username,
            usr.firstname, usr.lastname, usr.email, lpu.json
            FROM {local_adele_path_user} lpu
            LEFT JOIN {user} usr ON lpu.user_id = usr.id
            WHERE lpu.learning_path_id = :learning_path_id
            AND lpu.status = 'active'
            AND lpu.user_id = :userpathid ";

        $record = $DB->get_record_sql($sql, $params);
        if ($record) {
            $record->json = self::addnodemanualcondition($record->json);
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
     * @param array $params
     * @return array
     */
    public static function addnodemanualcondition($json) {
        $json = json_decode($json);
        foreach ($json->tree->nodes as $node) {
            $node->draggable = false;
            $node->deletable = false;
            $node->data->completion = $json->user_path_relation->{$node->id} ?? false;
            $node->data->manual = false;
            if ($node->completion && $node->completion->nodes) {
                foreach ($node->completion->nodes as $completionnode) {
                    if ($completionnode->data->type == 'manual') {
                        $node->data->manual = true;
                    }
                }
            }
        }
        return json_encode($json);
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
        $userpath = $userpathrelation->get_user_path_relation($params->route->learninggoalId, $params->route->userId);
        if ($userpath) {
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


}
