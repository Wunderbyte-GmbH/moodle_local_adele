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
class manual implements course_restriction {

    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_MANUALLY;
    /** @var string $label of the redered condition in frontend. */
    public $label = 'manual';
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
        $information = get_string('course_information_condition_manual', 'local_adele');
        return $information;
    }


    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_description_string() {
        $description = get_string('course_description_condition_restriction_manual', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_restriction_description_before() {
        return get_string('course_restricition_before_condition_manual', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_name_string() {
        $description = get_string('course_name_condition_restriction_manual', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     * @param array $node
     * @param object $userpath
     * @return boolean
     */
    public function get_restriction_status($node, $userpath) {
        $coursecompletion = [
          'completed' => false,
          'inbetween_info' => 'unchecked',
        ];
        if (isset($node['data']['manualrestriction'] ) && $node['data']['manualrestriction']
            && $node['data']['manualrestrictionvalue']) {
            $coursecompletion['completed'] = true;
            $coursecompletion['inbetween_info'] = 'checked';
        }
        return $coursecompletion;
    }
}
