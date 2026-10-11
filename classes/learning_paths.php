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
use required_capability_exception;

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
        // Refuse to persist a path with an incomplete condition (e.g. a
        // "specific node completed" criterion with no node selected) - it would
        // render the {node_name} placeholder and break the path (#450).
        self::assert_conditions_complete($params['json']);
        // Reject a name already taken by another path (#492).
        self::assert_name_unique($params['name'], $params['learningpathid']);
        $data = new stdClass();
        $data->name = $params['name'];
        $data->description = $params['description'];
        $data->image = $params['image'];
        $data->timemodified = \core\di::get(\core\clock::class)->time();
        $data->json = $params['json'];
        $id = 0;
        if ($params['learningpathid'] == 0) {
            $data->timecreated = \core\di::get(\core\clock::class)->time();
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
     * Assert that every condition in the learning-path tree is fully configured.
     *
     * Currently guards the "specific node completed" restriction (label
     * specific_course): it must have a node selected (data.value.courseid).
     * An unselected node renders the literal {node_name} placeholder and breaks
     * the path (#450). Throws so the save web service is rejected.
     *
     * @param string $jsonstring the learning-path json
     * @return void
     * @throws \moodle_exception when a condition is incomplete
     */
    public static function assert_conditions_complete($jsonstring) {
        $json = json_decode($jsonstring, true);
        if (!isset($json['tree']['nodes']) || !is_array($json['tree']['nodes'])) {
            return;
        }
        // Criteria that require a manual selection: label => the data.value sub-field
        // that must be set. An empty selection would leak its placeholder ({node_name},
        // {quiz_name}) and/or be unsatisfiable, so saving it is refused (#450, #456).
        $requiredvalue = [
            'specific_course' => 'courseid', // Specific node completed - needs a node.
            'modquiz' => 'quizid', // Mod quiz completion - needs a quiz.
            'catquiz' => 'testid', // Catquiz completion - needs a test.
        ];
        foreach ($json['tree']['nodes'] as $node) {
            foreach (['restriction', 'completion'] as $type) {
                if (!isset($node[$type]['nodes']) || !is_array($node[$type]['nodes'])) {
                    continue;
                }
                foreach ($node[$type]['nodes'] as $condition) {
                    $label = $condition['data']['label'] ?? '';
                    if (!isset($requiredvalue[$label])) {
                        continue;
                    }
                    // Not-selected = null / unset / empty string. NOT a numeric 0,
                    // which is a legitimate selection for catquiz (parent-scale mode).
                    $selected = $condition['data']['value'][$requiredvalue[$label]] ?? null;
                    if ($selected === null || $selected === '') {
                        throw new \moodle_exception(
                            'error_condition_incomplete',
                            'local_adele',
                            '',
                            $node['data']['fullname'] ?? ($node['id'] ?? '')
                        );
                    }
                }
            }
        }
    }

    /**
     * Refuse to save a learning path whose name is already used by another path, so
     * duplicate names (and the confusion they cause) are prevented (#492). The match is
     * case-insensitive; the path being edited is excluded so a plain re-save is allowed.
     *
     * @param string $name The proposed learning-path name.
     * @param int $learningpathid The id being saved (0 for a new path); excluded from the check.
     * @throws \moodle_exception When another learning path already uses this name.
     */
    public static function assert_name_unique($name, $learningpathid) {
        global $DB;
        $select = $DB->sql_equal('name', ':name', false);
        $params = ['name' => $name];
        if (!empty($learningpathid)) {
            $select .= ' AND id <> :id';
            $params['id'] = $learningpathid;
        }
        if ($DB->record_exists_select('local_adele_learning_paths', $select, $params)) {
            throw new \moodle_exception('error_learningpath_name_exists', 'local_adele');
        }
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
        $data->timemodified = \core\di::get(\core\clock::class)->time();
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
            '',
            'id, name, description, image, visibility, createdby'
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
        // DISTINCT so duplicate editor rows for the same path don't fan out the
        // JOIN and collide the get_records_sql key on lp.id.
        $sql = "SELECT DISTINCT lp.id, lp.id as learningpathid, lp.name
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
                'ownerid' => 0,
                'ownername' => '',
                'ownerless' => false,
            ];
            return $learningpath;
        }
        global $DB;
        $learningpath = $DB->get_record(
            'local_adele_learning_paths',
            ['id' => $params['learningpathid']],
            'id, name, description, image, json, createdby'
        );
        $learningpath = self::get_image_paths($learningpath);
        $learningpath = (array) $learningpath;
        // Owner info for the editors panel: golden crown + "Owner:" label (#488),
        // orphaned marker when the owner vanished (#571).
        $ownerid = (int) ($learningpath['createdby'] ?? 0);
        unset($learningpath['createdby']);
        $fields = 'id, deleted, ' . implode(', ', \core_user\fields::get_name_fields());
        $owner = $ownerid ? $DB->get_record('user', ['id' => $ownerid], $fields) : null;
        $learningpath['ownerid'] = $ownerid;
        $learningpath['ownerless'] = ownership::is_vanished($owner);
        $learningpath['ownername'] = $learningpath['ownerless'] ? '' : fullname($owner);
        return $learningpath;
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
                            $file->get_contextid(),
                            $file->get_component(),
                            $file->get_filearea(),
                            $file->get_itemid(),
                            $file->get_filepath(),
                            $file->get_filename()
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

        $learningpath = $DB->get_record(
            'local_adele_learning_paths',
            ['id' => $params['learningpathid']],
            'name, description, image, json'
        );
        if (isset($learningpath)) {
            $copyindex = 1;
            $copiedname = $learningpath->name .= ' copy';
            while (true) {
                if ($copyindex > 1) {
                    $existinglearningpath = $DB->get_record(
                        'local_adele_learning_paths',
                        ['name' => $copiedname . ' ' . $copyindex],
                        'id'
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
            $learningpath->timecreated = \core\di::get(\core\clock::class)->time();
            $learningpath->timemodified = \core\di::get(\core\clock::class)->time();
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
            // The duplicating user owns the copy, so it appears in their editable
            // list and they can edit it (mirrors create on save) (#458).
            learning_path_editors::create_editors($id, $USER->id);
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

        // Remove every enrolment this learning path ever created,
        // BEFORE the path record disappears (no-op when enrol_adele is absent).
        enrol_state::request_purge((int) $params['learningpathid']);

        $result = $DB->delete_records('local_adele_learning_paths', ['id' => $params['learningpathid']]);
        if ($result) {
            // Clean up editor membership for the deleted path (the per-user path_user
            // snapshots are deliberately kept - see #446 - so students still get the
            // "not found" notice instead of silently losing progress). They are
            // archived so no later event or task treats them as an active
            // enrolment source; enrol_state also guards against missing paths.
            $DB->set_field(
                'local_adele_path_user',
                'status',
                'archived',
                ['learning_path_id' => $params['learningpathid'], 'status' => 'active']
            );
            $DB->delete_records('local_adele_lp_editors', ['learningpathid' => $params['learningpathid']]);
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

        // The course_id column is removed from the WHERE clause,
        // same reasoning as get_learning_user_relation() above. Here it matters
        // for the teacher view specifically: filtering all learners of a path by
        // a single course_id wrongly dropped every learner whose snapshot happens
        // to carry a different (arbitrary, first-trigger) course_id, so the
        // teacher saw an incomplete learner list. The unique index guarantees one
        // snapshot per (user, path), so keying on learning_path_id alone returns
        // exactly one row per learner.
        $params = [
            'learning_path_id' => (int)$data['learningpathid'],
        ];

        // The roster of an embedding belongs to its course (#588): learners
        // of the path AND enrolled in that course - not the learners another
        // course brought to the same path. Enrolled means enrolled, active or
        // suspended: host mode "hidden" keeps its learners suspended in the
        // host course, and their teacher must still see them. This is NOT the
        // course_id column discussed above (that one only records where the
        // snapshot was first triggered); it is the course's real enrolments.
        // Without a scope - path authority outside any course - the roster is
        // the path's. Ranks are given below, within whatever set this yields.
        $scopejoin = '';
        if (!empty($data['scopecourseid'])) {
            [$enrolledsql, $enrolledparams] = get_enrolled_sql(
                \context_course::instance((int) $data['scopecourseid']),
                '',
                0,
                false
            );
            $scopejoin = "JOIN ({$enrolledsql}) scope ON scope.id = lpu.user_id";
            $params = array_merge($params, $enrolledparams);
        }

        $sql = "SELECT lpu.user_id, lpu.status, lpu.json, usr.username,
            usr.firstname, usr.lastname, usr.email
            FROM {local_adele_path_user} lpu
            LEFT JOIN {user} usr ON lpu.user_id = usr.id
            {$scopejoin}
            WHERE lpu.learning_path_id = :learning_path_id
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
            // Percent first (#461 revisit: equal relative progress ties), raw
            // completed count as tie-break (more absolute work ranks higher).
            usort($userpathlist, function ($a, $b) {
                if ($a['progress']['progress'] === $b['progress']['progress']) {
                    return $b['progress']['completed_nodes'] <=> $a['progress']['completed_nodes'];
                }
                return $b['progress']['progress'] <=> $a['progress']['progress'];
            });
            $rank = 1;
            $prevuser = null;
            foreach ($userpathlist as $index => &$user) {
                if (
                    $prevuser &&
                    $prevuser['progress']['completed_nodes'] === $user['progress']['completed_nodes'] &&
                    $prevuser['progress']['progress'] === $user['progress']['progress']
                ) {
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
     * Roster progress for one learner's snapshot (#461, revisited).
     *
     * The percentage measures the learner's BEST ROUTE through the graph: the
     * maximum over all root->terminal routes of completed-on-route divided by
     * route length. On OR-branched paths finishing the short branch therefore
     * reads 100% (and half of it 50%), independent of how long the other
     * branches are; it reaches 100% exactly when the path is completed. The
     * raw completed/total counts keep their meaning and feed the roster
     * tooltip and the ranking tie-break. Routes come from the cycle-safe
     * enumerator in node_completion (#447). Graphs without any route (empty
     * tree, no starting node, dead-end cycle) fall back to the raw semantics,
     * mirrored into the route fields so the display stays consistent.
     *
     * @param object $relationnodes Decoded path_user snapshot.
     * @return array completed_nodes, progress, route_completed, route_total, total_nodes
     */
    public static function getnodeprogress($relationnodes) {
        try {
            $validnodes = 0;
            $totalnodes = 0;
            $valid = [];
            if (isset($relationnodes->user_path_relation)) {
                foreach ($relationnodes->user_path_relation as $key => $node) {
                    if (strstr($key, '_module') == false) {
                        if ($node->completionnode->valid) {
                            $validnodes++;
                            $valid[$key] = true;
                        }
                        $totalnodes++;
                    }
                }
            }

            // Best route: highest completed/length ratio (integer cross-
            // multiplication, no float equality); equal ratios resolve toward
            // the route with the fewest REMAINING nodes - "1/2" communicates
            // "one node to go" better than "5/10".
            $best = null;
            foreach (self::get_route_candidates($relationnodes) as $route) {
                $length = 0;
                $completed = 0;
                foreach ($route as $nodeid) {
                    // Module overlay nodes are not part of the path (mirrors
                    // the raw-count exclusion above).
                    if (strstr((string) $nodeid, '_module') != false) {
                        continue;
                    }
                    $length++;
                    if (!empty($valid[$nodeid])) {
                        $completed++;
                    }
                }
                if ($length === 0) {
                    continue;
                }
                if (
                    $best === null
                    || $completed * $best['length'] > $best['completed'] * $length
                    || ($completed * $best['length'] === $best['completed'] * $length
                        && $length - $completed < $best['length'] - $best['completed'])
                ) {
                    $best = ['completed' => $completed, 'length' => $length];
                }
            }

            if ($best !== null) {
                $routecompleted = $best['completed'];
                $routetotal = $best['length'];
                $progress = $best['completed'] / $best['length'];
            } else {
                $routecompleted = $validnodes;
                $routetotal = $totalnodes;
                $progress = $totalnodes > 0 ? ($validnodes / $totalnodes) : 0;
            }
            return [
                'completed_nodes' => $validnodes,
                'progress' => round(100 * $progress, 2),
                'route_completed' => $routecompleted,
                'route_total' => $routetotal,
                'total_nodes' => $totalnodes,
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
     * The snapshot's root->terminal routes, memoised per tree topology.
     *
     * Learners of one path normally share an identical snapshot topology, so
     * a teacher roster of N learners enumerates the routes once instead of N
     * times; snapshots with a diverging tree get their own cache entry.
     *
     * @param object $relationnodes Decoded path_user snapshot.
     * @return array List of routes (lists of node ids); empty when the tree is unusable.
     */
    protected static function get_route_candidates($relationnodes): array {
        static $memo = [];
        if (
            !isset($relationnodes->tree->nodes)
            || !is_array($relationnodes->tree->nodes)
            || empty($relationnodes->tree->nodes)
        ) {
            return [];
        }
        $projection = [];
        foreach ($relationnodes->tree->nodes as $node) {
            $projection[] = [
                $node->id ?? '',
                $node->parentCourse ?? [],
                $node->childCourse ?? [],
            ];
        }
        $key = md5(json_encode($projection));
        if (!array_key_exists($key, $memo)) {
            $memo[$key] = node_completion::get_possible_paths($relationnodes->tree->nodes);
        }
        return $memo[$key];
    }

    /**
     * Get user path relation.
     *
     * @param array $data
     * @return array
     */
    public static function get_learning_user_relation($data) {
        global $DB;

        // The course_id column is removed from the WHERE clause.
        // The local_adele_path_user unique index is (user_id, learning_path_id)
        // "independent of the host course" (its own db/install.xml comment, from
        // the enrol_adele decoupling, spec 2.1), so there is at most ONE active
        // snapshot per (user, path). But subscribe stores course_id as whatever
        // course first triggered it ($courseid ?? 0) and never updates it after
        // (the unique index makes later subscribes reuse the row), so that stored
        // value is essentially arbitrary. Filtering the fetch by the VIEWING
        // course_id therefore hid the one valid snapshot whenever the student
        // opened the activity from a different course context than the one that
        // first created the snapshot -> get_record_sql returned false -> the
        // student view fell through to its lp_deleted="not found" branch and
        // rendered NO nodes at all (the runtime Behat scenarios timing out on
        // [data-id='dndnode_2'] were this: the whole path failed to render, not
        // just that one node). Keying the lookup on (learning_path_id, user_id)
        // alone matches the unique index exactly and is unambiguous.
        $params = [
            'learning_path_id' => (int)$data['learningpathid'],
            'userpathid' => (int)$data['userpathid'],
        ];

        $sql = "SELECT lpu.id, lpu.user_id, lpu.json, lpu.last_seen_by_owner, usr.username,
            usr.firstname, usr.lastname, usr.email, lpu.json, lap.image
            FROM {local_adele_path_user} lpu
            LEFT JOIN {user} usr ON lpu.user_id = usr.id
            LEFT JOIN {local_adele_learning_paths} lap ON lpu.learning_path_id = lap.id
            WHERE lpu.learning_path_id = :learning_path_id
            AND lpu.status = 'active'
            AND lpu.user_id = :userpathid ";

        $record = $DB->get_record_sql($sql, $params);
        if ($record) {
            // The per-user path snapshot is deliberately kept when the master learning
            // path is deleted (it holds the user's progress). Signal the deletion so the
            // student view shows the same "not found" notice the editor shows, instead of
            // rendering a path that no longer exists (GitHub #446). json is emptied so the
            // frontend does not try to JSON.parse a stale snapshot.
            $masterlp = $DB->get_record('local_adele_learning_paths', ['id' => $params['learning_path_id']], 'json');
            if (!$masterlp) {
                return [
                    'id' => (int)$record->id,
                    'user_id' => (int)$record->user_id,
                    'username' => $record->username ?? '',
                    'firstname' => $record->firstname ?? '',
                    'lastname' => $record->lastname ?? '',
                    'email' => $record->email ?? '',
                    'json' => '',
                    'last_seen_by_owner' => (string)($record->last_seen_by_owner ?? ''),
                    'image' => '',
                    'lp_deleted' => true,
                ];
            }
            // Reflect the CURRENT learning-path-wide settings (feedback/info toggles) rather than the
            // frozen per-user snapshot, so toggling them takes effect for already-subscribed students (#474).
            $masterjson = json_decode($masterlp->json, true);
            $mastersettings = $masterjson['settings'] ?? null;
            $decoded = json_decode($record->json, true);
            if (is_array($decoded)) {
                if ($mastersettings !== null) {
                    $decoded['settings'] = $mastersettings;
                }
                self::overlay_master_display_fields($decoded, $masterjson);
                $record->json = json_encode($decoded);
            }
            // Re-render the feedback/info strings in the CURRENT (viewer's) language so they follow
            // a language switch instead of the language the recompute happened to run in (#493).
            $record->json = relation_update::rerender_feedback_language($record->json, $record->user_id);
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
            'json' => '',
            'last_seen_by_owner' => 'not found',
            'image' => 'not found',
            'lp_deleted' => true,
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
        global $DB;
        $json = json_decode($json);

        // Resolve the real names of every course referenced in the path, so the
        // views can label nodes/cards without depending on the editor's
        // availablecourses pool. That pool is filtered server-side (e.g.
        // activefilter=only_subscribed), so students saw "Subcourse" for courses
        // they were not enrolled in (issue #457). A user assigned to the path is
        // entitled to see the names of the courses it contains.
        $courseids = [];
        foreach ($json->tree->nodes as $node) {
            if (!empty($node->data->course_node_id) && is_array($node->data->course_node_id)) {
                foreach ($node->data->course_node_id as $courseid) {
                    $courseids[(int)$courseid] = (int)$courseid;
                }
            }
        }
        $coursenames = $courseids
            ? $DB->get_records_list('course', 'id', array_values($courseids), '', 'id, fullname')
            : [];

        foreach ($json->tree->nodes as $node) {
            $node->draggable = false;
            $node->deletable = false;
            $node->data->completion = $json->user_path_relation->{$node->id} ?? false;
            $node->data->manual = false;
            self::attachcoursenames($node, $coursenames);
            $node = self::checkmanualcondition($node);
            $node = self::checknodeprogression($node, $userid);
        }
        return json_encode($json);
    }

    /**
     * Overlay display-only node texts from the master learning path onto a
     * per-user snapshot tree.
     *
     * The snapshot copies the tree at subscription time, so a node description
     * (or per-course text of a stack) the editor adds LATER never reaches
     * already-subscribed students (GitHub #484). These fields carry no
     * progress/verdict state, so the master is authoritative for them - the
     * same rule the settings toggles already follow (#474).
     *
     * @param array $decoded The decoded per-user snapshot json (modified in place).
     * @param array|null $masterjson The decoded master learning-path json.
     * @return void
     */
    public static function overlay_master_display_fields(array &$decoded, ?array $masterjson): void {
        $masternodes = [];
        foreach (($masterjson['tree']['nodes'] ?? []) as $masternode) {
            if (isset($masternode['id'])) {
                $masternodes[$masternode['id']] = $masternode['data'] ?? [];
            }
        }
        if (!$masternodes || empty($decoded['tree']['nodes']) || !is_array($decoded['tree']['nodes'])) {
            return;
        }
        $displayfields = ['fullname', 'description', 'estimate_duration', 'course_node_id_description'];
        foreach ($decoded['tree']['nodes'] as $i => $snapshotnode) {
            $nodeid = $snapshotnode['id'] ?? null;
            if ($nodeid === null || !isset($masternodes[$nodeid])) {
                continue;
            }
            foreach ($displayfields as $field) {
                if (array_key_exists($field, $masternodes[$nodeid])) {
                    $decoded['tree']['nodes'][$i]['data'][$field] = $masternodes[$nodeid][$field];
                }
            }
        }
    }

    /**
     * Attach resolved course full names to a node's course_node_id_description,
     * so the frontend can label course cards independently of the filtered
     * availablecourses pool (issue #457). Existing (teacher-given) names are kept.
     *
     * @param object $node The decoded learning-path node.
     * @param array $coursenames Map of courseid => record with ->fullname.
     * @return void
     */
    public static function attachcoursenames($node, $coursenames) {
        if (empty($node->data->course_node_id) || !is_array($node->data->course_node_id)) {
            return;
        }
        if (!isset($node->data->course_node_id_description) || !is_object($node->data->course_node_id_description)) {
            $node->data->course_node_id_description = new \stdClass();
        }
        foreach ($node->data->course_node_id as $courseid) {
            $courseid = (int)$courseid;
            if (!isset($coursenames[$courseid])) {
                continue;
            }
            $key = (string)$courseid;
            if (!isset($node->data->course_node_id_description->{$key})) {
                $node->data->course_node_id_description->{$key} = new \stdClass();
            }
            if (empty($node->data->course_node_id_description->{$key}->fullname)) {
                // Use the raw fullname, matching how get_availablecourses already
                // exposes course names (c.fullname, PARAM_TEXT, no format_string).
                $node->data->course_node_id_description->{$key}->fullname = $coursenames[$courseid]->fullname;
            }
        }
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
            if (!empty($node->{$condition}) && !empty($node->{$condition}->nodes)) {
                foreach ($node->{$condition}->nodes as $conditionnode) {
                    if (!empty($conditionnode->data->label) && $conditionnode->data->label == 'manual') {
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
        if (!empty($node->data->completion->master->completion)) {
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
        // A node with no completion criteria has no progress entries; default to
        // 0 instead of reading an undefined index 0 (warning / round(null)).
        $node->data->progress = round($completioncolumnprogress[0] ?? 0, 2);
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
        $record->last_seen_by_owner = \core\di::get(\core\clock::class)->time();
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
        $params = [
            'userid' => (int)$USER->id,
        ];

        // DISTINCT so a user with duplicate editor rows for the same path does not
        // collide the get_records_sql key (learningpathid is not unique here).
        $sql = "SELECT DISTINCT lpe.learningpathid
            FROM {local_adele_lp_editors} lpe
            WHERE lpe.userid = :userid";
        $records = $DB->get_records_sql($sql, $params);

        $cache->set('localadeleeditor', $records);

        return $records ?? [];
    }


    /**
     * Retrieves a list of learning path IDs owned by the current user.
     *
     * This function queries the database to find all learning paths where the
     * current user is an editor and also the creator, and returns their IDs.
     *
     * @return array An associative array of records containing learning path IDs owned by the current user.
     */
    public static function return_learningpaths_owned() {

        global $USER, $DB;

        $params = [
            'userid1' => (int)$USER->id,
            'userid2' => (int)$USER->id,
        ];

        $sql = "SELECT lpe.learningpathid
            FROM {local_adele_lp_editors} lpe
            JOIN {local_adele_learning_paths} lp on lp.id = lpe.learningpathid
            WHERE lpe.userid = :userid1 AND lp.createdby = :userid2";
        $records = $DB->get_records_sql($sql, $params);
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
        if (
            has_capability('local/adele:canmanage', context_system::instance())
            || has_capability('local/adele:assist', context_system::instance())
        ) {
            $iseditor = true;
        } else {
            $learningpaths = self::return_learningpaths();
            $iseditor = !empty($learningpaths);
        }

        return $iseditor;
    }

    /**
     * Whether a learning path is embedded in a course: whether a mod_adele
     * activity of that course shows it.
     *
     * This is what ties a path to a course. The web services that hand out
     * learner data take the context from the client; rights that come from a
     * course (teaching it, being enrolled in it) may only count where the
     * path is actually shown - otherwise any course somebody belongs to would
     * open the learner data of every path.
     *
     * @param int $learningpathid The learning path.
     * @param int $courseid The course.
     * @return bool
     */
    public static function is_embedded_in_course(int $learningpathid, int $courseid): bool {
        global $DB;
        if ($learningpathid <= 0 || $courseid <= 0) {
            return false;
        }
        // The embedding lives in mod_adele; without it nothing is embedded.
        if (!$DB->get_manager()->table_exists('adele')) {
            return false;
        }
        return $DB->record_exists('adele', ['course' => $courseid, 'learningpathid' => $learningpathid]);
    }

    /**
     * Whether the current user may SEE the learners of a learning path in a
     * course: local/adele:viewparticipants - or local/adele:teacheredit,
     * since who may change a learner's path may look at it.
     *
     * Seeing and changing are two rights (mod_adele #39): a teacher without
     * editing rights sees everything and changes nothing. Changing is
     * local/adele:teacheredit alone.
     *
     * @param \context $context A course or module context.
     * @return bool
     */
    public static function can_view_participants(\context $context): bool {
        return has_capability('local/adele:viewparticipants', $context)
            || has_capability('local/adele:teacheredit', $context);
    }

    /**
     * Whether the current user has authority over a learning path AS A PATH,
     * independent of any course: site administrators and managers
     * (local/adele:canmanage in the system) over every path, everybody else
     * over exactly the paths they are an editor of (lp_editors: the creator
     * and the people added to it).
     *
     * This is what decides about the learner data of a path where no course
     * right applies. check_access() is not enough for that: it only says the
     * person may edit SOME path, and so gave every assistant and every
     * collaborator of any path the learners of all paths.
     *
     * @param int $learningpathid The learning path.
     * @return bool
     */
    public static function has_path_authority(int $learningpathid): bool {
        global $DB, $USER;
        if (is_siteadmin() || has_capability('local/adele:canmanage', context_system::instance())) {
            return true;
        }
        return $learningpathid > 0 && $DB->record_exists('local_adele_lp_editors', [
            'learningpathid' => $learningpathid,
            'userid' => $USER->id,
        ]);
    }

    /**
     * Require that the current user may edit the given learning path.
     *
     * Managers and site admins have full access; everyone else must be an editor
     * of THIS specific path (lp_editors membership - i.e. its creator or a named
     * person added to it). check_access() alone is not enough: it only proves the
     * user is an editor/assistant of *something*, which let any editor overwrite
     * any path by id via the web service (IDOR, #458).
     *
     * @param int $learningpathid
     * @param \context $context
     * @return void
     * @throws required_capability_exception when the user does not own the path
     */
    public static function require_lp_editor_access($learningpathid, $context) {
        global $USER, $DB;

        if (has_capability('local/adele:canmanage', $context) || is_siteadmin()) {
            return;
        }
        // A brand-new, unsaved learning path (id 0) has no owner yet, so any
        // editor/assistant may work with it (creating one is gated by check_access;
        // the creator is then auto-added as its editor on save).
        if (empty($learningpathid)) {
            if (!empty(self::check_access())) {
                return;
            }
        } else if (
            $DB->record_exists('local_adele_lp_editors', [
            'learningpathid' => $learningpathid,
            'userid' => $USER->id,
            ])
        ) {
            return;
        }
        throw new required_capability_exception($context, 'local/adele:canmanage', 'nopermissions', '');
    }

    /**
     * Require that the current user OWNS (created) the given learning path.
     *
     * Managers and site admins keep full access; everyone else must be the creator.
     * Being merely an editor (lp_editors membership) is NOT enough - actions like
     * duplicating are reserved for the path's owner (#471).
     *
     * @param int $learningpathid
     * @param \context $context
     * @return void
     * @throws required_capability_exception when the user does not own the path
     */
    public static function require_lp_owner_access($learningpathid, $context) {
        global $USER, $DB;

        if (has_capability('local/adele:canmanage', $context) || is_siteadmin()) {
            return;
        }
        $createdby = $DB->get_field('local_adele_learning_paths', 'createdby', ['id' => $learningpathid]);
        if ($createdby !== false && (int) $createdby === (int) $USER->id) {
            return;
        }
        throw new required_capability_exception($context, 'local/adele:canmanage', 'nopermissions', '');
    }

    /**
     * Scope the full set of learning paths to what the requesting user may see.
     *
     * - Managers/admins ($privileged) see everything, all marked as owner.
     * - Assistants see every VISIBLE path plus their own (even if hidden); paths they
     *   may edit (lp_editors membership) land in 'edit', the rest in 'view' (#472).
     * - Everyone else sees only the paths they may edit.
     *
     * @param array $allpaths ['edit' => [...], 'view' => [...]] as built by get_learning_paths().
     * @param array $editablekeys Learning-path ids the user may edit (lp_editors membership).
     * @param array $ownedkeys Learning-path ids the user created.
     * @param bool $isassistant Whether the user holds local/adele:assist (Adele Assistant).
     * @param bool $privileged Whether the user is a manager or site admin (sees all).
     * @return array ['edit' => [...], 'view' => [...]] with each path's 'isowner' set.
     */
    public static function scope_paths_for_user(
        array $allpaths,
        array $editablekeys,
        array $ownedkeys,
        bool $isassistant,
        bool $privileged
    ): array {
        if ($privileged) {
            foreach ($allpaths as &$typepaths) {
                foreach ($typepaths as &$path) {
                    if (is_array($path)) {
                        $path['isowner'] = "true";
                    }
                }
                unset($path);
            }
            unset($typepaths);
            return $allpaths;
        }

        $scoped = ['edit' => [], 'view' => []];
        // The get_learning_paths(true, ...) master set places every path in 'edit'; iterate it.
        foreach ($allpaths['edit'] as $lp) {
            if (!is_array($lp)) {
                continue;
            }
            $id = (int) $lp['id'];
            $iseditor = in_array($id, $editablekeys);
            $isowner = in_array($id, $ownedkeys);
            if ($isassistant) {
                $visible = ((int) ($lp['visibility'] ?? 0)) === 1;
                // Hidden paths a non-owner did not create stay hidden (#472).
                if (!$visible && !$isowner) {
                    continue;
                }
            } else if (!$iseditor) {
                continue;
            }
            $lp['isowner'] = $isowner ? "true" : "false";
            if ($iseditor) {
                $scoped['edit'][] = $lp;
            } else {
                $scoped['view'][] = $lp;
            }
        }
        return $scoped;
    }

    /**
     * Annotate each learning path with its owner (creator) and editors, so the overview
     * tiles can show who is responsible - e.g. so an assistant knows whom to ask for
     * edit rights (#487). Adds 'owner' => ['name', 'email'] and 'editors' => [['name'], ...],
     * and drops the internal 'createdby'.
     *
     * @param array $paths ['edit' => [...], 'view' => [...]] path structure to decorate in place.
     * @return void
     */
    public static function add_path_people(array &$paths): void {
        global $DB;
        $usercache = [];
        foreach ($paths as &$typepaths) {
            foreach ($typepaths as &$path) {
                if (!is_array($path)) {
                    continue;
                }
                $createdby = (int) ($path['createdby'] ?? 0);
                unset($path['createdby']);
                $owner = ['name' => '', 'email' => ''];
                $ownerless = true;
                if ($createdby) {
                    if (!isset($usercache[$createdby])) {
                        // Select all name fields so fullname() has everything it may format;
                        // deleted for the ownerless flag (#571).
                        $fields = 'id, email, deleted, ' . implode(', ', \core_user\fields::get_name_fields());
                        $usercache[$createdby] = $DB->get_record('user', ['id' => $createdby], $fields);
                    }
                    // A vanished owner shows no stale name; the flag drives the
                    // "ownerless" warning for Admin/Adele Manager (#571).
                    $ownerless = ownership::is_vanished($usercache[$createdby]);
                    if (!$ownerless) {
                        $owner = [
                            'name' => fullname($usercache[$createdby]),
                            'email' => $usercache[$createdby]->email,
                        ];
                    }
                }
                $path['owner'] = $owner;
                $path['ownerless'] = $ownerless;
                $editors = [];
                foreach (learning_path_editors::get_editors((int) $path['id']) as $editor) {
                    $editors[] = ['name' => trim(($editor['firstname'] ?? '') . ' ' . ($editor['lastname'] ?? ''))];
                }
                $path['editors'] = $editors;
            }
            unset($path);
        }
        unset($typepaths);
    }
}
