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

namespace local_adele\course_restriction;

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
class course_restriction_status {
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
     * @param array $node
     * @param object $userpath
     * @return array
     */
    public function get_restriction_status($node, $userpath): array {
        global $CFG;
        // First, we get all the available conditions from our directory.
        $path = $CFG->dirroot . '/local/adele/classes/course_restriction/conditions/*.php';
        $filelist = glob($path);
        $completionstatus = [];
        // We just want filenames, as they are also the classnames.
        foreach ($filelist as $filepath) {
            $path = pathinfo($filepath);
            $filename = 'local_adele\\course_restriction\\conditions\\' . $path['filename'];
            // We instantiate all the classes, because we need some information.
            if (class_exists($filename)) {
                $conditionclass = new $filename();
                $completionstatus[$path['filename']] = $conditionclass->get_restriction_status($node, $userpath);
            }
        }
        return $completionstatus;
    }
}
