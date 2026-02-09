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
 * Helper functions for user path relation.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\helper;

/**
 * The learnpath created event class.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class node_times_subscription {
    /**
     * Get learning paths that contain course.
     *
     * @param array $node
     * @return object
     *
     */
    public static function get_node_times_subscription($node) {
        $times = [
            'start' => null,
            'end' => null,
        ];
        if (isset($node['data']['completion']['restrictioncriteria'])) {
            foreach ($node['data']['completion']['restrictioncriteria'] as $restrictioncriteria) {
                $test = 'testing';
            }
        }
        return $times;
    }
}
