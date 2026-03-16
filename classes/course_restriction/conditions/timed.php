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
 * Timed restriction condition for learning path nodes.
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
class timed implements course_restriction {
    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_TIMED;
    /** @var string $type of the redered condition in frontend. */
    public $label = 'timed';

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
        return get_string('course_information_condition_timed', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_description_string() {
        return get_string('course_description_condition_timed', 'local_adele');
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
        return get_string('course_name_condition_timed', 'local_adele');
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
     * Evaluate the timed restriction status for a node.
     *
     * The timed condition supports three scenarios:
     * 1. Only start date: Access opens at start date, never closes.
     * 2. Only end date: Access is open from the beginning, closes at end date.
     * 3. Both start and end date: Access is open between start and end.
     *
     * State flags:
     * - isbefore: true ONLY if a start date exists AND has not been reached yet.
     * - isafter: true ONLY if an end date exists AND has been passed.
     * - completed/inbetween: true if the current time is within the valid window.
     *
     * @param array $node
     * @param object $userpath
     * @return array
     */
    public function get_restriction_status($node, $userpath) {
        $timed = [];
        if (isset($node['restriction']) && isset($node['restriction']['nodes'])) {
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if (isset($restrictionnode['data']['label']) && $restrictionnode['data']['label'] == 'timed') {
                    $tz = $this->get_timezone();
                    $currenttimestamp = new DateTime('now', $tz);

                    // Parse start and end dates.
                    $startdate = $this->isvaliddate(
                        $restrictionnode['data']['value']['start'] ?? null,
                        'Y-m-d\TH:i',
                        $tz
                    );
                    $enddate = $this->isvaliddate(
                        $restrictionnode['data']['value']['end'] ?? null,
                        'Y-m-d\TH:i',
                        $tz
                    );

                    // Determine the state flags.
                    // isbefore: Only true if a start date exists and is in the future.
                    $isbeforerange = false;
                    // isafter: Only true if an end date exists and has been passed.
                    $isafterrange = false;
                    // validtime/completed: true if current time is within the valid window.
                    $validtime = false;

                    // Evaluate start date.
                    $startreached = true; // No start date = start is always reached.
                    if ($startdate) {
                        if ($startdate <= $currenttimestamp) {
                            $startreached = true;
                        } else {
                            $startreached = false;
                            $isbeforerange = true;
                        }
                    }

                    // Evaluate end date.
                    $endnotpassed = true; // No end date = end never passes.
                    if ($enddate) {
                        if ($enddate >= $currenttimestamp) {
                            $endnotpassed = true;
                        } else {
                            $endnotpassed = false;
                            $isafterrange = true;
                        }
                    }

                    // The time window is valid if start has been reached AND end has not passed.
                    $validtime = $startreached && $endnotpassed;

                    // Build placeholders for display.
                    $startdateformatted = false;
                    $enddateformatted = false;

                    if ($startdate) {
                        $startdateformatted = $startdate->format('d.m.Y H:i');
                        $timed[$restrictionnode['id']]['placeholders']['start_date'] = $startdateformatted;
                    } else {
                        $timed[$restrictionnode['id']]['placeholders']['start_date'] =
                            get_string('course_restricition_timed_no_date', 'local_adele');
                    }

                    if ($enddate) {
                        $enddateformatted = $enddate->format('d.m.Y H:i');
                        $timed[$restrictionnode['id']]['placeholders']['end_date'] = $enddateformatted;
                    } else {
                        $timed[$restrictionnode['id']]['placeholders']['end_date'] =
                            get_string('course_restricition_timed_no_date', 'local_adele');
                    }

                    $timed[$restrictionnode['id']]['completed'] = $validtime;
                    $timed[$restrictionnode['id']]['inbetween'] = $validtime;
                    $timed[$restrictionnode['id']]['isbefore'] = $isbeforerange;
                    $timed[$restrictionnode['id']]['isafter'] = $isafterrange;
                    $timed[$restrictionnode['id']]['inbetween_info'] = [
                        'starttime' => $startdateformatted,
                        'endtime' => $enddateformatted,
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
     * Validate and parse a date string.
     *
     * @param string|null $datestring
     * @param string $format
     * @param \DateTimeZone|null $tz
     * @return DateTime|false
     */
    public function isvaliddate($datestring, $format = 'Y-m-d\TH:i', $tz = null) {
        if ($datestring !== null) {
            if ($tz === null) {
                $tz = $this->get_timezone();
            }
            $datetime = DateTime::createFromFormat($format, $datestring, $tz);
            if ($datetime && $datetime->format($format) === $datestring) {
                return $datetime;
            }
        }
        return false;
    }
}
