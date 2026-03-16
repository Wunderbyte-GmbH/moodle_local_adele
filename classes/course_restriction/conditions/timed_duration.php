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
 * Timed duration restriction condition for learning path nodes.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2026 Wunderbyte GmbH
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
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timed_duration implements course_restriction {
    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_TIMED;
    /** @var string $type of the redered condition in frontend. */
    public $label = 'timed_duration';
    /** @var array $time span array. */
    private $durationplaceholder;

    /**
     * Maps duration types to their equivalent durations in seconds.
     *
     * @var array
     */
    private $durationvaluearray = [
        '0' => 86400,   // Days.
        '1' => 604800,  // Weeks.
        '2' => 2629746, // Months (average).
    ];

    /**
     * Entities constructor.
     */
    public function __construct() {
        $this->durationplaceholder = [
            '0' => get_string('course_select_condition_timed_duration_days', 'local_adele'),
            '1' => get_string('course_select_condition_timed_duration_weeks', 'local_adele'),
            '2' => get_string('course_select_condition_timed_duration_months', 'local_adele'),
        ];
    }

    /**
     * Obtains a string describing this restriction.
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
            'information' => $this->get_information_string(),
        ];
    }

    /**
     * Helper function to return localized information strings.
     *
     * @return string
     */
    private function get_information_string() {
        return get_string('course_information_condition_timed_duration', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_description_string() {
        return get_string('course_description_condition_timed_duration', 'local_adele');
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
        return get_string('course_name_condition_timed_duration', 'local_adele');
    }

    /**
     * Get the Moodle server timezone object for consistent date handling.
     *
     * @return \DateTimeZone
     */
    private function get_timezone(): \DateTimeZone {
        return \core_date::get_server_timezone_object();
    }

    /**
     * Evaluate the timed_duration restriction status for a node.
     *
     * @param array $node
     * @param object $userpath
     * @return array
     */
    public function get_restriction_status($node, $userpath) {
        $timed = [];
        $tz = $this->get_timezone();
        $currenttime = new DateTime('now', $tz);

        if (isset($node['restriction']) && isset($node['restriction']['nodes'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if (isset($restrictionnode['data']['label']) && $restrictionnode['data']['label'] == 'timed_duration') {
                    $iscurrenttimeinrange = false;
                    $isbeforerange = false;
                    $isafterrange = false;
                    $starttime = new DateTime('now', $tz);
                    $endtime = null;
                    $durationvalue = '';
                    $selectedduration = '';

                    if (isset($restrictionnode['data']['value']['selectedOption'])) {
                        if ($restrictionnode['data']['value']['selectedOption'] == '1') {
                            if (isset($node['data']['first_enrolled'])) {
                                $starttime->setTimestamp($node['data']['first_enrolled']);
                            } else {
                                $starttime = get_string('course_condition_timed_duration_start', 'local_adele');
                            }
                        } else {
                            $starttime->setTimestamp($userpath->timecreated);
                        }
                        $durationvalue = $restrictionnode['data']['value']['durationValue'];
                        $selectedduration = $restrictionnode['data']['value']['selectedDuration'];

                        // Check if the duration type is valid and calculate the end time.
                        if (
                            isset($this->durationvaluearray[$durationvalue]) &&
                            !is_string($starttime)
                        ) {
                            $totalseconds = $this->durationvaluearray[$durationvalue] * $selectedduration;
                            $endtime = clone $starttime;
                            $endtime->modify("+{$totalseconds} seconds");
                            // Check if the current timestamp is between the start and end timestamps.
                            $iscurrenttimeinrange = $currenttime >= $starttime && $currenttime <= $endtime;
                            $isafterrange = $currenttime > $endtime;
                            $isbeforerange = $currenttime < $starttime;
                        }
                    }

                    if ($endtime) {
                        $endtime = $endtime->format('d.m.Y H:i');
                    }
                    if (is_string($starttime)) {
                        $timed[$restrictionnode['id']]['placeholders']['timed_condition'] =
                            $starttime;
                        $timed[$restrictionnode['id']]['inbetween_info'] = [
                            'starttime' => $starttime,
                            'endtime' => $endtime,
                        ];
                    } else {
                        $timed[$restrictionnode['id']]['placeholders']['timed_condition'] =
                            get_string('course_condition_timed_duration_since', 'local_adele') .
                            $starttime->format('d.m.Y H:i');
                        $timed[$restrictionnode['id']]['inbetween_info'] = [
                            'starttime' => $starttime->format('d.m.Y H:i') ?? null,
                            'endtime' => $endtime,
                        ];
                    }
                    $timed[$restrictionnode['id']]['placeholders']['duration_period'] =
                        $selectedduration . ' ' . ($this->durationplaceholder[$durationvalue] ?? '');
                    $timed[$restrictionnode['id']]['completed'] = $iscurrenttimeinrange;
                    $timed[$restrictionnode['id']]['inbetween'] = $iscurrenttimeinrange;
                    $timed[$restrictionnode['id']]['isbefore'] = $isbeforerange;
                    $timed[$restrictionnode['id']]['isafter'] = $isafterrange;
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
}
