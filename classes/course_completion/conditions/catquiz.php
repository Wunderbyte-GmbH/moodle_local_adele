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
use local_catquiz\catquiz as Local_catquizCatquiz;
use local_catquiz\catscale;

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
class catquiz implements course_completion {

    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_CATQUIZ;
    /** @var string $label of the redered condition in frontend. */
    public $label = 'catquiz';
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
        $description = get_string('course_description_condition_catquiz', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_completion_description_before() {
        return get_string('course_description_before_condition_catquiz', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_completion_description_after() {
        return get_string('course_description_after_condition_catquiz', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    public function get_completion_description_inbetween() {
        return get_string('course_description_inbetween_condition_catquiz', 'local_adele');
    }

    /**
     * Helper function to return localized description strings.
     *
     * @return string
     */
    private function get_name_string() {
        $description = get_string('course_name_condition_catquiz', 'local_adele');
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
        $catquizzes = [
          'completed' => [],
          'inbetween_info' => 'testing ifno',
        ];
        if (isset($node['completion']) && isset($node['completion']['nodes'])) {
            foreach ($node['completion']['nodes'] as $complitionnode) {
                if (isset($complitionnode['data']) && isset($complitionnode['data']['label'])
                  && $complitionnode['data']['label'] == 'catquiz' &&isset($complitionnode['data']['value']['testid'])
                ) {
                    $validationtype = get_config('local_adele', 'quizsettings');
                    $componentid = $complitionnode['data']['value']['componentid'];
                    $testidcourseid = $complitionnode['data']['value']['testid_courseid'];
                    $scales = $complitionnode['data']['value']['scales'] ?? null;
                    $scaleids = self::get_scale_ids($scales);
                    $parentscaleglobal = $scales['parent']['scale'] ?? false;

                    $records = Local_catquizCatquiz::return_data_from_attemptstable(
                      100,
                      $componentid,
                      $testidcourseid,
                      $userid
                    );
                    $allpassedrecords = [];
                    $partialpassedrecords = [];
                    foreach ($records as $record) {
                        $personabilityresults = Local_catquizCatquiz::get_personabilityresults_of_quizattempt($record);
                        $rightanswerspercentage =
                          Local_catquizCatquiz::get_percentage_of_right_answers_by_scale($scaleids, $record);
                        $invalidattempt = false;
                        $parentscalerecord = $personabilityresults->{$scales['parent']['id']};
                        foreach ($scales as $type => $scaletype) {
                            if ($type == 'parent') {
                                if (
                                    self::check_scale(
                                        $personabilityresults, $scaletype, $validationtype,
                                        $invalidattempt, $partialpassedrecords, $record,
                                        $parentscaleglobal, $parentscalerecord
                                    ) ||
                                    self::check_attempts(
                                        $rightanswerspercentage, $scaletype, $validationtype,
                                        $invalidattempt, $partialpassedrecords, $record,
                                        $parentscaleglobal, $parentscalerecord
                                    )
                                ) {
                                    break;
                                }
                            } else {
                                foreach ($scaletype as $scale) {
                                    if (
                                        self::check_scale(
                                            $personabilityresults, $scale, $validationtype,
                                            $invalidattempt, $partialpassedrecords, $record,
                                            $parentscaleglobal, $parentscalerecord
                                        ) ||
                                        self::check_attempts(
                                            $rightanswerspercentage, $scale, $validationtype,
                                            $invalidattempt, $partialpassedrecords, $record,
                                            $parentscaleglobal, $parentscalerecord
                                        )
                                    ) {
                                        break;
                                    }
                                }
                            }
                            if ($invalidattempt) {
                                break;
                            }
                        }
                        if (!$invalidattempt && $validationtype == 'single_quiz') {
                            $allpassedrecords['single'] = $record;
                        }
                    }
                    if (
                        $validationtype == 'single_quiz' &&
                        !empty($allpassedrecords)
                    ) {
                        $catquizzes['completed'][$complitionnode['id']] = $allpassedrecords;
                    } else if (
                        $partialpassedrecords &&
                        count($partialpassedrecords['scale']) == count($scaleids) &&
                        count($partialpassedrecords['percentage']) == count($scaleids)
                    ) {
                        $catquizzes['completed'][$complitionnode['id']] = $partialpassedrecords;
                    } else {
                        $catquizzes['completed'][$complitionnode['id']] = false;
                    }
                } else {
                    $catquizzes['completed'][$complitionnode['id']] = false;
                }
            }
        }
        return $catquizzes;
    }

    /**
     * Check if the sclae of quiz was reached.
     * @param object $personabilityresults
     * @param array $scale
     * @param string $validationtype
     * @param bool $invalidattempt
     * @param array $partialpassedrecords
     * @param object $record
     * @param string $parentscaleglobal
     * @param string $parentscalerecord
     * @return bool
     */
    private function check_scale(
        $personabilityresults, $scale, $validationtype, &$invalidattempt,
        &$partialpassedrecords, $record, $parentscaleglobal, $parentscalerecord
    ) {
        if (isset($scale['scale']) && $scale['scale']) {
            if (!(isset($personabilityresults->{$scale['id']}) && $personabilityresults->{$scale['id']} >= $scale['scale'])) {
                if ($validationtype == 'single_quiz') {
                    $invalidattempt = true;
                    return true;
                }
            } else {
                if (
                    $validationtype != 'all_quiz_global' ||
                    $parentscaleglobal < $parentscalerecord
                ) {
                    $partialpassedrecords['scale'][$scale['id']][] = $record->attemptid;
                }
            }
        }
        return false;
    }

    /**
     * Check if the percentage of correct answers was reached.
     * @param object $attempts
     * @param array $scale
     * @param string $validationtype
     * @param bool $invalidattempt
     * @param array $partialpassedrecords
     * @param object $record
     * @param string $parentscaleglobal
     * @param string $parentscalerecord
     * @return bool
     */
    private function check_attempts(
        $attempts, $scale, $validationtype, &$invalidattempt,
        &$partialpassedrecords, $record, $parentscaleglobal, $parentscalerecord
    ) {
        if (isset($scale['attempts'])) {
            if (!(isset($attempts[$scale['id']]) && $attempts[$scale['id']]['percentage'] >= $scale['attempts'])) {
                if ($validationtype == 'single_quiz') {
                    $invalidattempt = true;
                    return true;
                }
            } else {
                if (
                    $validationtype != 'all_quiz_global' ||
                    !$parentscaleglobal ||
                    $parentscaleglobal < $parentscalerecord
                ) {
                    $partialpassedrecords['percentage'][$scale['id']][] = $record->attemptid;
                }
            }
        }
        return false;
    }

    /**
     * Helper function to return localized description strings.
     * @param array $scaletypes
     * @return array
     */
    private function get_scale_ids($scaletypes) {
        $scaleids = [];
        foreach ($scaletypes as $type => $subscales) {
            if ( $type == 'parent' ) {
                if (
                isset($subscales['attempts']) &&
                $subscales['attempts'] != ''
                ) {
                    $scaleids[] = $subscales['id'];
                }
            } else {
                foreach ($subscales as $subscale) {
                    if (
                    isset($subscale['attempts']) &&
                    $subscale['attempts'] != ''
                    ) {
                        $scaleids[] = $subscale['id'];
                    }
                }
            }
        }
        return $scaleids;
    }

    /**
     * Helper function to return localized description strings.
     * @param array $quizzesconditions
     * @return array
     */
    private function conditionsummary($quizzesconditions) {
        $quizsummary = [];
        foreach ($quizzesconditions as $id => $quizzes) {
            $valid = false;
            foreach ($quizzes as $quiz) {
                if ($quiz) {
                    $valid = true;
                }
            }
            $quizsummary[$id] = $valid;
        }
        return $quizsummary;
    }

    /**
     * Helper function to return localized description strings.
     * @param int $instanceid
     * @param int $courseid
     * @param int $userid
     * @return object
     */
    private function get_modquiz_records($instanceid, $courseid, $userid) {
        global $DB;
        return $DB->get_records_select(
            'local_catquiz_attempts',
            'instanceid = :instanceid AND courseid = :courseid AND userid = :userid',
            ['instanceid' => $instanceid, 'courseid' => $courseid, 'userid' => $userid],
            'timemodified DESC',
            'attemptid, contextid, userid, endtime, timemodified, json'
        );
    }

    /**
     * Helper function to return localized description strings.
     * @return int
     */
    public function get_completion_priority() {
        return $this->priority;
    }
}
