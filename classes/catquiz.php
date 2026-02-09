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

use local_catquiz\catquiz as Local_catquizCatquiz;
use local_catquiz\testenvironment;

/**
 * Class learning_paths
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catquiz {
    /**
     * Entities constructor.
     */
    public function __construct() {
    }

    /**
     * Get all tests.
     * @param array $availablecourses
     * @return array
     */
    public static function get_catquiz_tests($availablecourses) {
        if (!class_exists('local_catquiz\testenvironment')) {
            return [];
        }
        $availablecourseids = array_map(function ($course) {
            return $course['course_node_id'][0];
        }, $availablecourses);
        $records = testenvironment::get_environments('mod_adaptivequiz', 0, 2, true);
        $records = array_filter(array_map(function ($record) {
            $record = (array)$record;
            $record['json'] = json_decode($record['json']);
            $record['name'] = $record['json']->name;
            $record['coursename'] = $record['fullname'];
            $record['courseid'] = $record['courseid'];
            unset($record['json']);
            return $record;
        }, $records), function ($record) use ($availablecourseids) {
            return in_array($record['courseid'], $availablecourseids);
        });
        return array_values($records);
    }

    /**
     * Get all scales.
     * @param array $params
     * @return array
     */
    public static function get_catquiz_scales($params) {
        global $DB;
        if (!class_exists('local_catquiz\catquiz')) {
            return [];
        }
        $catquiz = $DB->get_record('local_catquiz_tests', ['id' => $params['testid']]);
        $scales = [];
        if ($catquiz) {
            $scaleids = [$catquiz->catscaleid];
            $catquiz->json = json_decode($catquiz->json);
            foreach ($catquiz->json as $key => $subscale) {
                if (strpos($key, 'catquiz_subscalecheckbox_') !== false && $subscale == '1') {
                    $scaleids[] = str_replace('catquiz_subscalecheckbox_', '', $key);
                }
            }
            if (!empty($scaleids)) {
                [$insql, $params] = $DB->get_in_or_equal($scaleids);
                $scales['sub'] = $DB->get_records_select('local_catquiz_catscales', "id $insql", $params);
                $scales['sub'] = array_map(function ($scale) {
                    return (array)$scale;
                }, $scales['sub']);
                $scales['parent'] = $scales['sub'][$catquiz->catscaleid];
                unset($scales['sub'][$catquiz->catscaleid]);
            }
        }
        return $scales;
    }


    /**
     * Get all parent scales.
     * @return array
     */
    public static function get_catquiz_parent_scales() {
        if (!class_exists('local_catquiz\catquiz')) {
            return [];
        }
        $records = Local_catquizCatquiz::get_all_parent_catscales();
        return $records;
    }
}
