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

    /** @var array percentage. */
    public $percentageattempts = [];
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
    public function get_description_string() {
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
        global $DB, $CFG;
        $catquizzes = [];
        if (!class_exists('local_catquiz\catquiz')) {
            return [];
        }
        if (isset($node['completion']) && isset($node['completion']['nodes'])) {
            foreach ($node['completion']['nodes'] as $complitionnode) {
                if (isset($complitionnode['data']) && isset($complitionnode['data']['label'])
                  && $complitionnode['data']['label'] == 'catquiz' &&isset($complitionnode['data']['value']['testid'])
                ) {
                    $validationtype = get_config('local_adele', 'quizsettings');
                    $testidcourseid = $complitionnode['data']['value']['testid_courseid'];
                    $scales = $complitionnode['data']['value']['scales'] ?? null;
                    $scaleattemptset = [
                        'scales' => 0,
                        'attempts' => 0,
                    ];
                    $scaleids = self::get_scale_ids($scales, $scaleattemptset);
                    $parentscaleglobal = $scales['parent']['scale'] ?? false;
                    $componentid = $complitionnode['data']['value']['componentid'];

                    $records = Local_catquizCatquiz::return_data_from_attemptstable(
                      100,
                      $componentid,
                      $testidcourseid,
                      $userid
                    );
                    $catquizzes['inbetween'][$complitionnode['id']] = false;
                    if (count($records) > 0) {
                        $catquizzes['inbetween'][$complitionnode['id']] = true;
                    }
                    $allpassedrecords = [];
                    $partialpassedrecords = [];
                    $partialpassedattemptids = [];
                    $percentageofrightanswersbyscalekeyid = [];
                    $subscaleids = [];
                    $bestresult = null;
                    $bestsubscaleids = [];
                    $bestpartialpassedrecords = [];
                    $bestpartialpassedattemptids = [];
                    $test = $DB->get_record(
                      'adaptivequiz',
                      ['id' => $componentid],
                      'name, course'
                    );
                    if ($test != null) {
                        $coursemoduleid = get_coursemodule_from_instance('adaptivequiz', $componentid, $test->course);
                        $catquizzes[$complitionnode['id']]['placeholders']['quiz_name'] =
                        '<a href="' . $CFG->wwwroot . '/mod/adaptivequiz/view.php?id=' .
                        $coursemoduleid->id .
                        '" target="_blank">' . $test->name .'</a>';
                    } else {
                        $catquizzes[$complitionnode['id']]['placeholders']['quiz_name'] = 'Test';
                    }
                    $catquizzes[$complitionnode['id']]['placeholders']['quiz_attempts_best'] = '';

                    foreach ($records as $record) {
                        $personabilityresults = Local_catquizCatquiz::get_personabilityresults_of_quizattempt($record);
                        if (
                          isset($scales['parent']['id']) &&
                          (
                              is_null($bestresult) ||
                              $personabilityresults->{$scales['parent']['id']} > $bestresult['scale']
                          )
                        ) {
                            $bestresult['scale'] = $personabilityresults->{$scales['parent']['id']};
                            $bestresult['scaleid'] = $scales['parent']['id'];
                            $bestresult['attemptid'] = $record->attemptid;
                        }
                        // Ist Prozentwert.
                        $rightanswerspercentage =
                          Local_catquizCatquiz::get_percentage_of_right_answers_by_scale($scaleids, $record);
                        $this->percentageattempts[$record->attemptid] = $rightanswerspercentage;
                        $invalidattempt = false;
                        $parentscalerecord = 0;
                        if (isset($scales['parent']['id'])) {
                            $parentscalerecord = $personabilityresults->{$scales['parent']['id']};
                        }
                        $percentageofrightanswersbyscalekeyid[$record->attemptid] = (array)$personabilityresults;
                        foreach ($scales as $type => $scaletype) {
                            if ($type == 'parent') {
                                self::check_scale_best_attempt($scaletype, $bestpartialpassedrecords, $record,
                                $bestpartialpassedattemptids,
                                $bestsubscaleids
                                );
                                if (
                                    self::check_scale(
                                        $personabilityresults, $scaletype, $validationtype,
                                        $invalidattempt, $partialpassedrecords, $record,
                                        $parentscaleglobal, $parentscalerecord, $partialpassedattemptids, $subscaleids
                                    ) ||
                                    self::check_attempts(
                                        $rightanswerspercentage, $scaletype, $validationtype,
                                        $invalidattempt, $partialpassedrecords, $record,
                                        $parentscaleglobal, $parentscalerecord, $partialpassedattemptids, $subscaleids
                                    )
                                ) {
                                    break;
                                }
                            } else {
                                foreach ($scaletype as $scale) {
                                    self::check_scale_best_attempt($scale, $bestpartialpassedrecords, $record,
                                    $bestpartialpassedattemptids,
                                    $bestsubscaleids
                                    );
                                    if (
                                        self::check_scale(
                                            $personabilityresults, $scale, $validationtype,
                                            $invalidattempt, $partialpassedrecords, $record,
                                            $parentscaleglobal, $parentscalerecord, $partialpassedattemptids, $subscaleids
                                        ) ||
                                        self::check_attempts(
                                            $rightanswerspercentage, $scale, $validationtype,
                                            $invalidattempt, $partialpassedrecords, $record,
                                            $parentscaleglobal, $parentscalerecord, $partialpassedattemptids, $subscaleids
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
                        isset($partialpassedrecords['scale']) &&
                        isset($partialpassedrecords['percentage']) &&
                        count($partialpassedrecords['scale']) == $scaleattemptset['scales'] &&
                        count($partialpassedrecords['percentage']) == $scaleattemptset['attempts']
                    ) {
                        $catquizzes['completed'][$complitionnode['id']] = $partialpassedrecords;
                        $filteredpercentageofrightanswers = array_intersect_key(
                            $percentageofrightanswersbyscalekeyid,
                            array_flip($partialpassedattemptids)
                        );
                        $catquizzes[$complitionnode['id']]['placeholders']['quiz_attempts_list'] =
                          self::get_record_list(
                            $scales,
                            $partialpassedattemptids,
                            $filteredpercentageofrightanswers,
                            $subscaleids
                          );
                    } else {
                        $catquizzes['completed'][$complitionnode['id']] = false;
                        if ($bestresult) {
                            $catquizzes[$complitionnode['id']]['placeholders']['quiz_attempts_list'] =
                            self::get_record_list(
                                $scales,
                                $bestpartialpassedattemptids,
                                $percentageofrightanswersbyscalekeyid,
                                $bestsubscaleids
                              );
                        }
                    }
                } else {
                    $catquizzes['completed'][$complitionnode['id']] = false;
                }
            }
        }
        return $catquizzes;
    }

    /**
     * Helper function to return localized description strings.
     * @param array $attemptid
     * @return object
     */
    private function get_attempt_information($attemptid) {
        global $DB;
        return $DB->get_record(
          'local_catquiz_attempts',
          ['attemptid' => $attemptid],
          'attemptid, instanceid, endtime, timemodified'
        );
    }

    /**
     * Helper function to return localized description strings.
     * @param array $attemptids
     * @return object
     */
    private function get_attempts_information($attemptids) {
        global $DB;

        // Convert the $attemptids array to a comma-separated list for use in the SQL query.
        list($insql, $params) = $DB->get_in_or_equal(array_values($attemptids), SQL_PARAMS_NAMED);

        // Construct the SQL query string.
        $sql = "
            SELECT
                lca.attemptid, lca.instanceid, lca.endtime, lca.timemodified
            FROM
                {local_catquiz_attempts} lca
            JOIN
                {adaptivequiz_attempt} aa ON lca.attemptid = aa.id
            WHERE
                aa.attemptstate = 'complete'
               AND lca.attemptid $insql
        ";

        // Execute the query and return the results.
        return $DB->get_records_sql($sql, $params);
    }



    /**
     * Helper function to return localized description strings.
     * @param array $scales
     * @param array $attemptids
     * @param array $percentageofrightanswersbyscalekeyid
     * @param array $subscaleids
     * @return array
     */
    private function get_record_list(
      $scales,
      $attemptids,
      $percentageofrightanswersbyscalekeyid,
      $subscaleids
    ) {
        global $CFG;
        $recordlist = [];
        $attemptsentries = $this->get_attempts_information($attemptids);
        $scalemap = [];
        $scalemap[$scales['parent']['id']] = [
            'name' => $scales['parent']['name'],
            'scale' => $scales['parent']['scale'],
            'attempts' => $scales['parent']['attempts'],
        ];
        foreach ($scales['sub'] as $subscale) {
            $scalemap[$subscale['id']] = [
                'name' => $subscale['name'],
                'scale' => $subscale['scale'] ?? '',
                'attempts' => $subscale['attempts'] ?? '',
            ];
        }

        foreach ($subscaleids as $subscaleid) {
            foreach ($percentageofrightanswersbyscalekeyid as $attempt => $scalevalues) {
                if (
                  (!isset($bestattemptpescale[$subscaleid]) ||
                  $scalevalues[$subscaleid] > $bestattemptpescale[$subscaleid]) &&
                  isset($this->percentageattempts[$attempt]) &&
                  isset($this->percentageattempts[$attempt][$subscaleid]) &&
                  isset($this->percentageattempts[$attempt][$subscaleid]['percentage'])
                ) {
                    $percentage = $this->percentageattempts[$attempt][$subscaleid]['percentage'];
                    $bestattemptpescale[$subscaleid] = [
                      'scale' => $scalevalues[$subscaleid],
                      'attemptid' => $attempt,
                      'currentpercentage' => $percentage,
                    ];
                }
            }
        }
        foreach ($bestattemptpescale as $scale => $attempt) {
            $recordlist[] = [
              'targetperc' => $scalemap[$scale]['attempts'],
              'currentperc' => $attempt['currentpercentage'],
              'targetlogit' => $scalemap[$scale]['scale'],
              'currentlogit' => $attempt['scale'],
              'time' => date("j.n.y", $attemptsentries[$attempt['attemptid']]->endtime !== '0'
              ? $attemptsentries[$attempt['attemptid']]->endtime
              : $attemptsentries[$attempt['attemptid']]->timemodified),
              'scale' => $scalemap[$scale]['name'],
              'link' =>
                $CFG->wwwroot . '/mod/adaptivequiz/attemptfinished.php?attempt=' .
                $attempt['attemptid'] .
                '&instance=' .
                $attemptsentries[$attempt['attemptid']]->instanceid,
            ];
        }
        return $recordlist;
    }


    /**
     * Check if the current attempt is the best attempt for a given scale.
     * @param array $scale The scale information array containing scale details
     * @param array $bestpartialpassedrecords Reference to array storing the best partial passed records
     * @param object $record The attempt record object
     * @param array $bestpartialpassedattemptids Reference to array storing the best partial passed attempt IDs
     * @param array $bestsubscaleids Reference to array storing the best subscale IDs
     * @return bool Returns false after processing
     */
    private function check_scale_best_attempt($scale, &$bestpartialpassedrecords, $record, &$bestpartialpassedattemptids,
    &$bestsubscaleids
    ) {
        if (isset($scale['scale']) && is_numeric($scale['scale'])) {

            $bestpartialpassedrecords['scale'][$scale['id']][] = $record->attemptid;
            if (!in_array($record->attemptid, $bestpartialpassedattemptids)) {
                $bestpartialpassedattemptids[] = $record->attemptid;
            }
            if (!in_array($scale['id'], $bestsubscaleids)) {
                $bestsubscaleids[] = $scale['id'];
            }
        }
        return false;
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
     * @param array $partialpassedattemptids
     * @param array $subscaleids
     * @return bool
     */
    private function check_scale(
        $personabilityresults, $scale, $validationtype, &$invalidattempt,
        &$partialpassedrecords, $record, $parentscaleglobal, $parentscalerecord, &$partialpassedattemptids, &$subscaleids
    ) {
        if (isset($scale['scale']) && is_numeric($scale['scale'])) {
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
                    if (!in_array($record->attemptid, $partialpassedattemptids)) {
                        $partialpassedattemptids[] = $record->attemptid;
                    }
                    if (!in_array($scale['id'], $subscaleids)) {
                        $subscaleids[] = $scale['id'];
                    }
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
     * @param array $partialpassedattemptids
     * @param array $subscaleids
     * @return bool
     */
    private function check_attempts(
        $attempts, $scale, $validationtype, &$invalidattempt,
        &$partialpassedrecords, $record, $parentscaleglobal, $parentscalerecord, &$partialpassedattemptids, &$subscaleids
    ) {
        if (isset($scale['attempts']) && is_numeric($scale['attempts'])) {
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
                    if (!in_array($record->attemptid, $partialpassedattemptids)) {
                        $partialpassedattemptids[] = $record->attemptid;
                    }
                    if (!in_array($scale['id'], $subscaleids)) {
                        $subscaleids[] = $scale['id'];
                    }
                }
            }
        }
        return false;
    }

    /**
     * Helper function to return localized description strings.
     * @param array $scaletypes
     * @param array $scaleattemptset
     * @return array
     */
    private function get_scale_ids($scaletypes, &$scaleattemptset) {
        $scaleids = [];
        foreach ($scaletypes as $type => $subscales) {
            if ( $type == 'parent' ) {
                if (
                isset($subscales['attempts']) &&
                $subscales['attempts'] != ''
                ) {
                    $scaleids[] = $subscales['id'];
                    $scaleattemptset['attempts'] += 1;
                }
                if (
                  isset($subscales['scale']) &&
                  is_numeric($subscales['scale'])
                ) {
                    $scaleattemptset['scales'] += 1;
                }
            } else {
                foreach ($subscales as $subscale) {
                    if (
                    isset($subscale['attempts']) &&
                    $subscale['attempts'] != ''
                    ) {
                        $scaleids[] = $subscale['id'];
                        $scaleattemptset['attempts'] += 1;
                    }
                    if (
                      isset($subscale['scale']) &&
                      is_numeric($subscale['scale'])
                    ) {
                        $scaleattemptset['scales'] += 1;
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
