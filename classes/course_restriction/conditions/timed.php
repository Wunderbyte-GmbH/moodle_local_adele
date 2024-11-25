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
    public function get_description(): array {
        $description = $this->get_description_string();
        $name = $this->get_name_string();
        $label = $this->label;

        return [
            'id' => $this->id,
            'name' => $name,
            'description' => $description,
            'description_before' => $this->get_restriction_description_before(),
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
    public function get_restriction_description_before() {
        return get_string('course_restricition_before_condition_timed', 'local_adele');
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
     * @param object $userpath
     * @return boolean
     */
    public function get_restriction_status($node, $userpath) {
        $timed = [];
        if (isset($node['restriction']) && isset($node['restriction']['nodes'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if (isset($restrictionnode['data']['label']) && $restrictionnode['data']['label'] == 'timed') {
                    $validstart = true;
                    $validtime = false;
                    $isbeforerange = true;
                    $isafterrange = false;
                    $currenttimestamp = new DateTime();
                    $startdate = $this->isvaliddate($restrictionnode['data']['value']['start']);
                    if ($startdate) {
                        if ($startdate <= $currenttimestamp) {
                            $validtime = true;
                            $isbeforerange = false;
                        } else {
                            $validstart = false;
                        }
                    }
                    $enddate = $this->isvaliddate($restrictionnode['data']['value']['end']);
                    if ($enddate) {
                        if ($enddate < $currenttimestamp) {
                            $isafterrange = true;
                        }
                        if (
                            $enddate >= $currenttimestamp &&
                            $validstart
                        ) {
                            $validtime = true;
                        } else {
                            $validtime = false;
                        }
                    }
                    if ($startdate) {
                        $startdate = $startdate->format('Y-m-d H:i:s');
                        $timed[$restrictionnode['id']]['placeholders']['start_date'] =
                            $startdate;
                    }
                    if ($enddate) {
                        $enddate = $enddate->format('Y-m-d H:i:s');
                        $timed[$restrictionnode['id']]['placeholders']['end_date'] =
                            get_string('course_restricition_before_condition_to', 'local_adele') . $enddate;
                    }
                    $timed[$restrictionnode['id']]['completed'] = $validtime;
                    $timed[$restrictionnode['id']]['inbetween'] = $validtime;
                    $timed[$restrictionnode['id']]['isbefore'] = $isbeforerange;
                    $timed[$restrictionnode['id']]['isafter'] = $isafterrange;
                    $timed[$restrictionnode['id']]['inbetween_info'] = [
                      'starttime' => $startdate,
                      'endtime' => $enddate,
                    ];
                } else {
                    $timed[$restrictionnode['id']] = [
                      'completed' => false,
                      'inbetween_info' => null,
                    ];
                }
            }
        }
        return $timed;
    }

    /**
     * Helper function to return localized description strings.
     * @param string $datestring
     * @param string $format
     * @return object
     */
    public function isvaliddate($datestring, $format = 'Y-m-d\TH:i') {
        $datetime = DateTime::createFromFormat($format, $datestring);
        if ($datetime && $datetime->format($format) === $datestring) {
            $datetime->format('Y-m-d H:i:s');
            return $datetime;
        }
        return false;
    }
}
