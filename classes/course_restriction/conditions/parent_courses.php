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
class parent_courses implements course_restriction {

    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_TIMED;
    /** @var string $type of the redered condition in frontend. */
    public $label = 'parent_courses';

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
        $description = get_string('course_description_condition_parent_courses', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_restriction_description_before() {
        return get_string('course_restricition_before_condition_parent_courses', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_name_string() {
        $description = get_string('course_name_condition_parent_courses', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     * @param array $node
     * @param object $userpath
     * @return boolean
     */
    public function get_restriction_status($node, $userpath) {
        $parentcourses = [];
        if (isset($node['restriction']) && isset($node['restriction']['nodes'])) {
            $restrictions = $node['restriction']['nodes'];
            foreach ($restrictions as $restriction) {
                $courselist = [];
                if ( isset($restriction['data']['label']) && $restriction['data']['label'] == 'parent_courses') {
                    $coursescompleted = false;
                    $coursestable = [];
                    if (isset($restriction['data']['value']['courses_id'])) {
                        foreach ($restriction['data']['value']['courses_id'] as $coursesid) {
                            foreach ($userpath->json['tree']['nodes'] as $usernode) {
                                if ($usernode['id'] == $coursesid) {
                                    $courselist[] = $usernode['data']['fullname'];
                                    if (
                                        isset($usernode['data']['completion']) &&
                                        $usernode['data']['completion']['feedback']['status'] == 'completed'
                                    ) {
                                        $coursestable[] = $coursesid;
                                    }
                                }
                            }
                        }
                        if ($restriction['data']['value']['min_courses'] <= count($coursestable)) {
                            $coursescompleted = true;
                        }
                    }
                    $parentcourses[$restriction['id']]['placeholders']['numb_courses'] =
                        $restriction['data']['value']['min_courses'];
                    $parentcourses[$restriction['id']]['placeholders']['node_name'] = $courselist;
                    $parentcourses[$restriction['id']]['completed'] = $coursescompleted;
                    $parentcourses[$restriction['id']]['inbetween_info'] = null;
                } else {
                    $parentcourses[$restriction['id']] = [
                      'completed' => false,
                      'inbetween_info' => null,
                    ];
                }
            }
        }
        return $parentcourses;
    }
}
