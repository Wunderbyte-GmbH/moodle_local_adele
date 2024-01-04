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
            foreach ($userpath->json['tree']['nodes'] as $node) {
                $completioncriteria = course_completion_status::get_condition_status($node, $userpath->user_id);
                $completionnodepaths = [];
                $singlecompletionnode = [];
                if (isset($node['completion'])) {
                    foreach ($node['completion']['nodes'] as $completionnode) {
                        $failedcompletion = false;
                        $validationconditionstring = [];
                        if ($completionnode['parentCondition'][0] == 'starting_condition') {
                            $currentcondition = $completionnode;
                            $validationcondition = false;
                            while ( $currentcondition ) {
                                if ($currentcondition['data']['label'] == 'catquiz' ||
                                    $currentcondition['data']['label'] == 'modquiz') {
                                    $validationcondition =
                                        $completioncriteria[$currentcondition['data']['label']][$currentcondition['id']];
                                    $singlecompletionnode[$currentcondition['data']['label']
                                        . '_' . $currentcondition['id']] = $validationcondition;
                                    $validationconditionstring[] = $currentcondition['data']['label']
                                        . '_' . $currentcondition['id'];
                                } else {
                                    $validationcondition = $completioncriteria[$currentcondition['data']['label']];
                                    $singlecompletionnode[$currentcondition['data']['label']] = $validationcondition;
                                    $validationconditionstring[] = $currentcondition['data']['label'];
                                }
                                // Check if the conditon is true and break if one condition is not met.
                                if (!$validationcondition) {
                                    $failedcompletion = true;
                                }
                                // Get next Condition and return null if no child node exsists.
                                $currentcondition = self::searchnestedarray($node['completion']['nodes'],
                                    $currentcondition['childCondition'], 'id');
                            }
                            if ($validationcondition && !$failedcompletion) {
                                $completionnodepaths[] = $validationconditionstring;
                            }
                        }
                    }
                }
                $completionnode = self::getcompletionnode($completionnodepaths);
                $userpath->json['user_path_relation'][$node['id']] = [
                    'completioncriteria' => $completioncriteria,
                    'completionnode' => $completionnode,
                    'singlecompletionnode' => $singlecompletionnode,
                ];
                // Match completions.
            }
            $userpathrelationhelper = new user_path_relation();
            $userpathrelationhelper->revision_user_path_relation($userpath);
        }
    }

    /**
     * Observer for course completed
     *
     * @param array $completionnodepaths
     * @return array
     */
    public static function getcompletionnode($completionnodepaths) {
        // TODO sort the valid completion paths.
        $valid = count($completionnodepaths) ? true : false;
        return [
            'valid' => $valid,
            'conditions' => $completionnodepaths,
        ];
    }

    /**
     * Observer for course completed
     *
     * @param array $haystack
     * @param array $needle
     * @param string $key
     * @return array
     */
    public static function searchnestedarray($haystack, $needle, $key) {
        foreach ($haystack as $item) {
            foreach ($needle as $need) {
                if ( !str_contains($need, '_feedback' )) {
                    if (isset($item[$key]) && $item[$key] === $need) {
                        return $item;
                    }
                }
            }
        }
        return null;
    }
}
