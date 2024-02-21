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
class completion {
    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function completed($event) {
        $params = $event;
        $userpathrelation = new user_path_relation();
        $learningpaths = $userpathrelation->get_learning_paths($params->userid);
        if ($learningpaths) {
            foreach ($learningpaths as $learningpath) {
                $learningpath->json = json_decode($learningpath->json, true);
                foreach ($learningpath->json['tree']['nodes'] as $node) {
                    if (is_array($node['data']['course_node_id']) && in_array($params->courseid, $node['data']['course_node_id'])) {
                        $eventsingle = user_path_updated::create([
                            'objectid' => $learningpath->id,
                            'context' => context_system::instance(),
                            'other' => [
                                'userpath' => $learningpath,
                            ],
                        ]);
                        $eventsingle->trigger();
                    }
                }
            }
        }
    }

    /**
     * Observer for course completed
     *
     * @param array $haystack
     * @param string $needle
     * @param string $key
     */
    public static function searchnestedarray($haystack, $needle, $key) {
        foreach ($haystack as $item) {
            if (isset($item[$key]) && $item[$key] === $needle) {
                return $item;
            }
        }
        return null;
    }
}
