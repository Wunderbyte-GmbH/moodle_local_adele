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
 * All bo condition label must extend this class.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\course_completion\conditions;

use completion_info;
use core_completion\progress;
use local_adele\course_completion\course_completion;
use local_adele\learning_path_update;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/adele/lib.php');
require_once("{$CFG->libdir}/completionlib.php");

/**
 * Class for a single learning path course condition.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_completed implements course_completion {

    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_NODE_FINISHED;
    /** @var string $label of the redered condition in frontend. */
    public $label = 'course_completed';
    /** @var int $id Standard Conditions have hardcoded ids. */
    public $priority = COURSES_PRIORITY_BEST;
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
            'description_before' => $this->get_completion_description_before(),
            'description_after' => $this->get_completion_description_after(),
            'description_inbetween' => $this->get_completion_description_inbetween(),
            'priority' => $this->get_completion_priority(),
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
        $information = get_string('course_information_condition_course_completed', 'local_adele');
        return $information;
    }
    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_description_string() {
        $description = get_string('course_description_condition_course_completed', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_completion_description_before() {
        return get_string('course_description_before_condition_course_completed', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_completion_description_after() {
        return get_string('course_description_after_condition_course_completed', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_completion_description_inbetween() {
        return get_string('course_description_inbetween_condition_course_completed', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_name_string() {
        $description = get_string('course_name_condition_course_completed', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     *
     * @param array $node
     * @param int $userid
     * @return array
     */
    public function get_completion_status($node, $userid) {
        $courses = $node['data']['course_node_id'];
        $coursecompletion = [];
        $finished = 0;
        $courseprogresslist = [];
        $progresses = [];
        $minvalue = 1;
        if (is_int($courses)) {
            return $coursecompletion;
        }
        $isinbetween = false;
        foreach ($courses as $courseid) {
            $course = learning_path_update::get_course($courseid);
            $completed = false;
            if ($course->enablecompletion) {
                // Get the course completion instance.
                $completion = new completion_info($course);
                $progress = progress::get_course_progress_percentage($course, $userid) ?? 0;
                if ($progress !== null) {
                    $isinbetween = true;
                }
                // Check if the user has completed the course.
                $coursecompleted = $completion->is_course_complete($userid);
                if ($coursecompleted) {
                    $progress = 100;
                    $completed = true;
                    $finished++;
                }
                $progresses[] = $progress;
                $courseprogresslist[] = $course->fullname . ' - ' . $progress . '%';
            }
            $coursecompletion['completed'][$courseid] = $completed;
        }
        if (isset($node['completion']) && isset($node['completion']['nodes'])) {
            foreach ($node['completion']['nodes'] as $complitionnode) {
                if (
                    isset($complitionnode['data']) &&
                    isset($complitionnode['data']['label']) &&
                    $complitionnode['data']['label'] == 'course_completed'
                ) {
                    $minvalue = $complitionnode['data']['value']['min_courses'] ?? 1;
                    $coursecompletion[$complitionnode['id']]['placeholders']['numb_courses'] = $minvalue;
                    $coursecompletion[$complitionnode['id']]['placeholders']['course_list'] = $courseprogresslist;
                    $coursecompletion[$complitionnode['id']]['placeholders']['numb_courses_total'] = count($courseprogresslist);
                    $coursecompletion['completed'][$complitionnode['id']] = $finished >= $minvalue ? true : false;
                    $coursecompletion['inbetween'][$complitionnode['id']] = $isinbetween;
                    if (count($courses) > 1) {
                        $counttodo = $minvalue;
                        $numbcourses = count($courses);
                        if ($finished <= $minvalue) {
                            $counttodo -= $finished;
                        }
                        if ($finished >= $minvalue) {
                            $string = $finished . ' ' . get_string('course_restricition_before_condition_from', 'local_adele') .
                            $numbcourses . ' '
                            . get_string('course_description_before_condition_course_completed_kursen', 'local_adele');
                        } else if ($isinbetween) {
                            $string = $counttodo . ' '
                            . get_string('course_restricition_before_condition_from', 'local_adele') .
                            $numbcourses . ' '
                            . get_string('course_description_before_condition_course_completed_kursen', 'local_adele');
                        } else {
                            $string = $counttodo
                            . get_string('course_description_before_condition_course_completed_aus', 'local_adele') .
                            $numbcourses . get_string('course_description_before_condition_course_completed_kursen', 'local_adele');
                        }
                        $coursecompletion[$complitionnode['id']]['placeholders']['item'] = $string;
                    } else {
                        $coursecompletion[$complitionnode['id']]['placeholders']['item'] =
                        get_string('course_description_before_condition_course_completed_item', 'local_adele');
                    }
                }
            }
        }

        $coursecompletion['inbetween_info'] = $this->get_node_progress($progresses, $minvalue);
        return $coursecompletion;
    }

    /**
     * Get the average of the furthest nodes.
     * @param array $progresses
     * @param int $minvalue
     * @return int
     */
    public function get_node_progress($progresses, $minvalue) {
        if (count($progresses) == 0) {
            return 0;
        }
        if (count($progresses) == 1) {
            return $progresses[0];
        }
        rsort($progresses);
        $strtcount = 0;
        $alloverprogress = 0;
        while ($strtcount < $minvalue) {
            $alloverprogress += $progresses[$strtcount];
            $strtcount++;
        }
        return $alloverprogress / $minvalue;
    }

    /**
     * Helper function to return localized description strings.
     * @return int
     */
    public function get_completion_priority() {
        return $this->priority;
    }
}
