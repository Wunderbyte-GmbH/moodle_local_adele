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

use DateInterval;
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
class timed_duration implements course_restriction {
    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_TIMED;
    /** @var string $type of the redered condition in frontend. */
    public $label = 'timed_duration';

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
            'description_before' => self::get_restriction_description_before(),
            'label' => $label,
        ];
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_description_string() {
        $description = get_string('course_description_condition_timed_duration', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_restriction_description_before() {
        return get_string('course_restricition_before_condition_timed_duration', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_name_string() {
        $description = get_string('course_name_condition_timed_duration', 'local_adele');
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
        $currenttime = new DateTime();
        $currenttime->setTimestamp(time());
        if (isset($node['restriction']) && isset($node['restriction']['nodes'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if (isset($restrictionnode['data']['label']) && $restrictionnode['data']['label'] == 'timed_duration') {
                    $iscurrenttimeinrange = false;
                    $starttime = new DateTime();
                    $endtime = null;
                    if (isset($restrictionnode['data']['value']['selectedOption'])) {
                        if ($restrictionnode['data']['value']['selectedOption'] == '1' && $node['data']['first_enrolled']) {
                            $starttime->setTimestamp($node['data']['first_enrolled']);
                        }
                        $durationvalue = $restrictionnode['data']['value']['durationValue'];
                        $selectedduration = $restrictionnode['data']['value']['selectedDuration'];
                        // Check if the duration type is valid and calculate the end time.
                        if (isset($this->durationvaluearray[$durationvalue])) {
                            $totalseconds = $this->durationvaluearray[$durationvalue] * $selectedduration;
                            $endtime = clone $starttime;
                            $endtime->modify("+{$totalseconds} seconds");
                            // Check if the current timestamp is between the start and end timestamps.
                            $iscurrenttimeinrange = $currenttime >= $starttime && $currenttime <= $endtime;
                        }
                    }
                    if ($endtime) {
                        $endtime = $endtime->format('Y-m-d H:i:s');
                        $timed[$restrictionnode['id']]['placeholders']['timed_condition'] = $endtime;
                    }
                    $timed[$restrictionnode['id']]['placeholders']['duration_period'] =
                        $selectedduration . ' ' .$this->durationplaceholder[$durationvalue];
                    $timed[$restrictionnode['id']]['completed'] = $iscurrenttimeinrange;
                    $timed[$restrictionnode['id']]['inbetween_info'] = [
                      'starttime' => $starttime->format('Y-m-d H:i:s') ?? null,
                      'endtime' => $endtime,
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
     * Maps duration types to their equivalent durations in seconds.
     *
     * @var array The keys represent the duration types as follows:
     *            '0' for days, with each day being 86400 seconds;
     *            '1' for weeks, with each week being 604800 seconds;
     *            '2' for months, with each month approximated to 2629746 seconds (considering an average month duration).
     */
    private $durationvaluearray = [
        '0' => 86400, // Days.
        '1' => 604800, // Weeks.
        '2' => 2629746, // Months.
    ];

    /**
     * Maps duration types to their equivalent durations in seconds.
     *
     * @var array The keys represent the duration types as follows:
     *            '0' for days, with each day being 86400 seconds;
     *            '1' for weeks, with each week being 604800 seconds;
     *            '2' for months, with each month approximated to 2629746 seconds (considering an average month duration).
     */
    private $durationplaceholder = [
        '0' => 'Days', // Days.
        '1' => 'Weeks', // Weeks.
        '2' => 'Months', // Months.
    ];
}
