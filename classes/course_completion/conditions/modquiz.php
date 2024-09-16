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
 * All bo condition labels must extend this class.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\course_completion\conditions;

use local_adele\course_completion\course_completion;

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
class modquiz implements course_completion {

    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_MODQUIZ;
    /** @var string $label of the redered condition in frontend. */
    public $label = 'modquiz';
    /** @var int $id Standard Conditions have hardcoded ids. */
    public $priority = COURSES_PRIORITY_SECOND;
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
            'description_before' => self::get_completion_description_before(),
            'description_after' => self::get_completion_description_after(),
            'description_inbetween' => self::get_completion_description_inbetween(),
            'priority' => self::get_completion_priority(),
            'label' => $label,
        ];
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_description_string() {
        $description = get_string('course_description_condition_modquiz', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_completion_description_before() {
        return get_string('course_description_before_condition_modquiz', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_completion_description_after() {
        return get_string('course_description_after_condition_modquiz', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_completion_description_inbetween() {
        return get_string('course_description_inbetween_condition_modquiz', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_name_string() {
        $description = get_string('course_name_condition_modquiz', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     * TODO check if get_strategy_selectcontext suits.
     * @param array $node
     * @param int $userid
     * @return boolean
     */
    public function get_completion_status($node, $userid) {
        global $DB;
        $modquizzes = [
          'completed' => [],
          'inbetween_info' => '',
        ];
        $bestgrade = 0;
        $maxgrade = 0;
        if (isset($node['completion']) && isset($node['completion']['nodes'])) {
            $completions = $node['completion']['nodes'];
            foreach ($completions as $completion) {
                if ( isset($completion['data']) && isset($completion['data']['label'])
                  && $completion['data']['label'] == 'modquiz') {
                    $validcatquiz = false;
                    // Get grade and check if valid.
                    $data = $this->get_modquiz_records($completion, $userid);
                    $sql = "SELECT q.name, cm.id AS cmid
                        FROM {quiz} q
                        JOIN {course_modules} cm ON cm.instance = q.id
                        JOIN {modules} m ON m.id = cm.module
                        WHERE m.name = 'quiz' AND q.id = :quizid";
                    $quizid = $completion['data']['value']['quizid'] ?? 0;
                    $record = $DB->get_record_sql($sql, ['quizid' => $quizid]);
                    if ($record) {
                        $modquizzes[$completion['id']]['placeholders']['quiz_name_link'] =
                          '<a href="/mod/quiz/view.php?id=' . $record->cmid . '" target="_blank">' . $record->name .'</a>';
                    } else {
                        $modquizzes[$completion['id']]['placeholders']['quiz_name_link'] =
                          'Mod Quiu';
                    }
                    $modquizzes[$completion['id']]['placeholders']['scale_min'] = $completion['data']['value']['grade'] ?? 0;
                    foreach ($data as $key => $lastgrade) {
                        if ((float)$key >= $bestgrade) {
                            $bestgrade = (float)$key;
                            $maxgrade = $completion['data']['value']['grade'];
                        }
                        if ((float)$key >= (float)$completion['data']['value']['grade']) {
                            $validcatquiz = true;
                        }
                    }
                    $modquizzes[$completion['id']]['placeholders']['current_best'] = $bestgrade;
                    $modquizzes['completed'][$completion['id']] = $validcatquiz;
                } else {
                    $modquizzes['completed'][$completion['id']] = false;
                }
            }
        }
        $modquizzes['inbetween_info'] = $bestgrade . '/' . $maxgrade;
        return $modquizzes;
    }

    /**
     * Helper function to return localized description strings.
     * @return int
     */
    public function get_completion_priority() {
        return $this->priority;
    }

    /**
     * Helper function to return localized description strings.
     * @param int $completion
     * @param int $userid
     * @return object
     */
    protected function get_modquiz_records($completion, $userid) {
        global $DB;
        return $DB->get_records_select(
            'quiz_grades',
            'quiz = :quiz AND userid = :userid',
            ['quiz' => $completion['data']['value']['quizid'] ?? 0, 'userid' => $userid],
            'timemodified DESC',
            'grade'
        );
    }
}
