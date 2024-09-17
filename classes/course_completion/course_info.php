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

namespace local_adele\course_completion;

use core_plugin_manager;

/**
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * class for conditional availability information of a condition
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_info {

    /** @var int userid for a given user */
    protected $userid;

    /**
     * Constructs with item details.
     *
     */
    public function __construct() {
        global $USER;
        $this->userid = $USER->id;
    }

    /**
     * Returns conditions depending on the conditions param.
     *
     * @return array
     */
    public static function get_conditions(): array {

        global $CFG;
        // First, we get all the available conditions from our directory.
        $path = $CFG->dirroot . '/local/adele/classes/course_completion/conditions/*.php';
        $filelist = glob($path);
        $conditions = [];

        $pluginman = core_plugin_manager::instance();
        $plugins = $pluginman->get_installed_plugins('local');

        // We just want filenames, as they are also the classnames.
        foreach ($filelist as $filepath) {
            $addcondition = true;
            $path = pathinfo($filepath);
            $filename = 'local_adele\\course_completion\\conditions\\' . $path['filename'];
            if (
              $path['filename'] == 'catquiz' &&
              !array_key_exists('catquiz', $plugins)
            ) {
                $addcondition = false;
            }
            // We instantiate all the classes, because we need some information.
            if (class_exists($filename) && $addcondition) {
                $conditionclass = new $filename();
                $conditions[] = $conditionclass->get_description();
            }
        }
        usort($conditions, function($a, $b) {
            return $b['id'] <=> $a['id'];
        });
        return $conditions;
    }
}
