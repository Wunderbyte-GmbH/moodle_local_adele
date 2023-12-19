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

use local_adele\helper\user_path_relation;

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
class completion {
    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function completed($event) {
        global $DB;
        $params = $event;
        $userpathrelation = new user_path_relation();
        $learningpaths = $userpathrelation->get_learning_paths($params->courseid);
        if ($learningpaths) {
            foreach ($learningpaths as $learningpath) {
                $learningpath->json = json_decode($learningpath->json, true);
                $userpath = $userpathrelation->get_user_path_relation($learningpath, $params->relateduserid);
                if ($userpath) {
                    $userpath->json = json_decode($userpath->json, true);
                    foreach ($learningpath->json['tree']['nodes'] as $node) {
                        if ($node['data']['course_node_id'] == $params->courseid) {
                            if ($node['completion'] && $node['completion']['nodes']) {
                                foreach ($node['completion']['nodes'] as $completionnode) {
                                    if ($completionnode['parentCondition'] == 'starting_condition') {
                                        $currentcondition = $node;
                                        while ( $currentcondition ) {
                                            // Check if the conditon is true and break if one condition is not met.
                                            if (!$learningpath->json['user_path_relation'][$node['id']]) {
                                                break;
                                            }
                                            // Get next Condition and return null if no child node exsists.
                                            $currentcondition = self::searchnestedarray($node['completion']['nodes'], $currentcondition['childCondition'], 'childCondition');
                                        }
                                    }
                                }
                            }
                            $learningpath->json['user_path_relation'][$node['id']] = true;
                            // Revision old user path relation.
                            $data = [
                                'id' => $userpath->id,
                                'status' => 'revision',
                                'timemodified' => time(),
                            ];
                            $DB->update_record('local_adele_path_user', $data);
                            // Save new user path relation.
                            $DB->insert_record('local_adele_path_user', [
                                'user_id' => $userpath->user_id,
                                'learning_path_id' => $userpath->learning_path_id,
                                'status' => 'active',
                                'timecreated' => $userpath->timecreated,
                                'timemodified' => time(),
                                'createdby' => $userpath->createdby,
                                'json' => json_encode([
                                    'tree' => $learningpath->json['tree'],
                                    'user_path_relation' => $learningpath->json['user_path_relation'],
                                ]),
                            ]);
                        }
                    }
                }
            }
        }
    }

    public function searchnestedarray($haystack, $needle, $key) {
        foreach ($haystack as $item) {
            if (isset($item[$key]) && $item[$key] === $needle) {
                return $item;
            }
        }
        return null;
    }
}
