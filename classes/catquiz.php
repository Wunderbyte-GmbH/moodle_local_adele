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

use local_catquiz\catquiz_handler;
use local_catquiz\data\dataapi;
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
     *
     * @return array
     */
    public static function get_catquiz_tests() {
        $records = testenvironment::get_environments('mod_adaptivequiz', 0, 2);
        $records = array_map(function ($record) {
            $record = (array)$record;
            $record['json'] = json_decode($record['json']);
            $record['name'] = $record['json']->name;
            unset($record['json']);
            return $record;
        }, $records);
        return $records;
    }

    /**
     * Get all scales.
     * @param array $params
     * @return array
     */
    public static function get_catquiz_scales($params) {
        $records = testenvironment::get_environments('mod_adaptivequiz', $params['testid']);
        if ($records) {
            $record = reset($records);
            $testdata = json_decode($record->json);
            $selectedsubscales = catquiz_handler::get_selected_subscales($testdata);
            $records = dataapi::get_catscale_and_children($params['testid'], true);
            $records = array_filter($records, function ($record) use ($selectedsubscales) {
                return in_array($record->id, $selectedsubscales);
            });
            $records = array_map(function ($record) {
                return (array)$record;
            }, $records);
        }
        return $records;
    }
}
