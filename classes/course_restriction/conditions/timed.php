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
use local_adele\helper\time_value;

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
            'information' => $this->get_information_string(),
        ];
    }

    /**
     * Helper function to return localized information strings.
     *
     * @return string
     */
    private function get_information_string() {
        $information = get_string('course_information_condition_timed', 'local_adele');
        return $information;
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
            // One reading of the clock for every condition of this node, so two
            // windows of the same node are always judged at the same instant.
            $now = \core\di::get(\core\clock::class)->time();
            $userid = isset($userpath->user_id) ? (int) $userpath->user_id : null;
            foreach ($node['restriction']['nodes'] as $restrictionnode) {
                if (isset($restrictionnode['data']['label']) && $restrictionnode['data']['label'] == 'timed') {
                    // Timestamps, compared as integers (#581). Values stored
                    // before #581 are wall-clock strings; time_value reads them
                    // in the site's time zone with the seconds set to zero.
                    $start = time_value::to_timestamp($restrictionnode['data']['value']['start'] ?? null);
                    $end = time_value::to_timestamp($restrictionnode['data']['value']['end'] ?? null);
                    $state = time_value::window_state($start, $end, $now);

                    // A window may be open at one end (the editor asks for at
                    // least one boundary). The templates read "{start_date} to
                    // {end_date}", also where an author wrote their own - so the
                    // open end gets words that fit there: "now to ...",
                    // "... to open end" (#590).
                    $timed[$restrictionnode['id']]['placeholders']['start_date'] = $start === null
                        ? get_string('course_restricition_timed_no_start', 'local_adele')
                        : time_value::display($start, $userid);
                    $timed[$restrictionnode['id']]['placeholders']['end_date'] = $end === null
                        ? get_string('course_restricition_timed_no_end', 'local_adele')
                        : time_value::display($end, $userid);
                    $timed[$restrictionnode['id']]['completed'] = $state['inside'];
                    $timed[$restrictionnode['id']]['inbetween'] = $state['inside'];
                    $timed[$restrictionnode['id']]['isbefore'] = $state['isbefore'];
                    $timed[$restrictionnode['id']]['isafter'] = $state['isafter'];
                    // Timestamps, not formatted strings: the frontend formats them
                    // in the viewer's own time zone, and relation_update compares
                    // them without parsing.
                    $timed[$restrictionnode['id']]['inbetween_info'] = [
                      'starttime' => $start,
                      'endtime' => $end,
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
     * @return boolean
     */
    public function isvaliddate($datestring, $format = 'Y-m-d\TH:i') {
        if ($datestring !== null) {
            $datetime = DateTime::createFromFormat($format, $datestring);
            if ($datetime && $datetime->format($format) === $datestring) {
                $datetime->format('d.m.Y H:i');
                return $datetime;
            }
        }
        return false;
    }
}
