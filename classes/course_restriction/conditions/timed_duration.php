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

use local_adele\helper\time_value;
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
    /** @var array $time span array. */
    private $durationplaceholder;

    /**
     * Entities constructor.
     */
    public function __construct() {
        $this->durationplaceholder = [
            '0' => get_string('course_select_condition_timed_duration_days', 'local_adele'), // Days.
            '1' => get_string('course_select_condition_timed_duration_weeks', 'local_adele'), // Weeks.
            '2' => get_string('course_select_condition_timed_duration_months', 'local_adele'), // Months.
        ];
    }

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
            'information' => $this->get_information_string(),
        ];
    }

    /**
     * Helper function to return localized information strings.
     *
     * @return string
     */
    private function get_information_string() {
        $information = get_string('course_information_condition_timed_duration', 'local_adele');
        return $information;
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
        if (isset($node['restriction']) && isset($node['restriction']['nodes'])) {
            // One reading of the clock for every condition of this node.
            $now = \core\di::get(\core\clock::class)->time();
            $userid = isset($userpath->user_id) ? (int) $userpath->user_id : null;
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if (isset($restrictionnode['data']['label']) && $restrictionnode['data']['label'] == 'timed_duration') {
                    $value = $restrictionnode['data']['value'] ?? [];
                    $durationvalue = $value['durationValue'] ?? '';
                    $selectedduration = $value['selectedDuration'] ?? '';

                    // Where the window starts: at the first enrolment into the
                    // node, or when the user path was created. A missing first
                    // enrolment leaves a message instead of a time.
                    $start = null;
                    $startmessage = null;
                    if (isset($value['selectedOption'])) {
                        if ($value['selectedOption'] == '1') {
                            if (isset($node['data']['first_enrolled'])) {
                                $start = (int) $node['data']['first_enrolled'];
                            } else {
                                $startmessage = get_string('course_condition_timed_duration_start', 'local_adele');
                            }
                        } else {
                            $start = (int) ($userpath->timecreated ?? 0);
                        }
                    }

                    // Half-open like the fixed window of `timed`: open from its
                    // first second up to, but not including, its end (#581).
                    $end = null;
                    $state = ['isbefore' => false, 'inside' => false, 'isafter' => false];
                    if ($start !== null && isset(self::DURATION_SECONDS[$durationvalue]) && is_numeric($selectedduration)) {
                        $end = $start + (int) (self::DURATION_SECONDS[$durationvalue] * $selectedduration);
                        $state = time_value::window_state($start, $end, $now);
                    }

                    if ($startmessage !== null) {
                        $timed[$restrictionnode['id']]['placeholders']['timed_condition'] = $startmessage;
                        $timed[$restrictionnode['id']]['inbetween_info'] = [
                          'starttime' => $startmessage,
                          'endtime' => null,
                        ];
                    } else {
                        $timed[$restrictionnode['id']]['placeholders']['timed_condition'] =
                          get_string('course_condition_timed_duration_since', 'local_adele') .
                          ($start !== null ? time_value::display($start, $userid) : '');
                        // Timestamps, like `timed` (#581): relation_update compares
                        // them, the frontend formats them for the viewer.
                        $timed[$restrictionnode['id']]['inbetween_info'] = [
                          'starttime' => $start,
                          'endtime' => $end,
                        ];
                    }
                    $timed[$restrictionnode['id']]['placeholders']['duration_period'] =
                    $selectedduration . ' ' . ($this->durationplaceholder[$durationvalue] ?? '');
                    $timed[$restrictionnode['id']]['completed'] = $state['inside'];
                    $timed[$restrictionnode['id']]['inbetween'] = $state['inside'];
                    $timed[$restrictionnode['id']]['isbefore'] = $state['isbefore'];
                    $timed[$restrictionnode['id']]['isafter'] = $state['isafter'];
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
    public const DURATION_SECONDS = [
        '0' => 86400, // Days.
        '1' => 604800, // Weeks.
        '2' => 2629746, // Months (average).
    ];
}
