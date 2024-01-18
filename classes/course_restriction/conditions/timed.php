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
 * Base class for a single booking option availability condition.
 *
 * All bo condition types must extend this class.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace local_adele\course_restriction\conditions;

use DateTime;
use local_adele\course_restriction\course_restriction;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/adele/lib.php');

/**
 * Class for a single learning path course condition.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timed implements course_restriction {

    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_TIMED;
    /** @var string $type of the redered condition in frontend. */
    public $label = 'timed';

    /**
     * Obtains a string describing this restriction (whether or not
     * it actually applies). Used to obtain information that is displayed to
     * students if the activity is not available to them, and for staff to see
     * what conditions are.
     *
     * The $full parameter can be used to distinguish between 'staff' cases
     * (when displaying all information about the activity) and 'student' cases
     * (when displaying only conditions they don't meet).
     *
     * @return array availability and Information string (for admin) about all restrictions on
     *   this item
     */
    public function get_description():array {
        $description = $this->get_description_string();
        $name = $this->get_name_string();
        $label = $this->label;

        return [
            'id' => $this->id,
            'name' => $name,
            'description' => $description,
            'label' => $label,
        ];
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_description_string() {
        $description = get_string('course_description_condition_timed', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_name_string() {
        $description = get_string('course_name_condition_timed', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     * @param array $node
     * @param int $userid
     * @return boolean
     */
    public function get_restriction_status($node, $userid) {
        $timed = [];
        foreach ($node['restriction']['nodes'] as $restrictionnode) {
            if ($restrictionnode['data']['label'] == 'timed') {
                $validstart = true;
                $validtime = false;
                if (self::isvaliddate($restrictionnode['data']['value']['start'])) {
                    $datetimestamp = strtotime($restrictionnode['data']['value']['start']);
                    $currenttimestamp = strtotime(date('Y-m-d'));
                    if ($datetimestamp <= $currenttimestamp) {
                        $validtime = true;
                    } else {
                        $validstart = false;
                    }
                }
                if (self::isvaliddate($restrictionnode['data']['value']['end'])) {
                    $datetimestamp = strtotime($restrictionnode['data']['value']['end']);
                    $currenttimestamp = strtotime(date('Y-m-d'));
                    if ($datetimestamp >= $currenttimestamp &&
                        $validstart) {
                        $validtime = true;
                    }
                }
                $timed[$restrictionnode['id']] = $validtime;
            }
        }
        return $timed;
    }

    /**
     * Helper function to return localized description strings.
     * @param string $datestring
     * @param string $format
     * @return boolean
     */
    public function isvaliddate($datestring, $format = 'Y-m-d') {
        $datetime = DateTime::createFromFormat($format, $datestring);
        return $datetime && $datetime->format($format) === $datestring;
  }
}
