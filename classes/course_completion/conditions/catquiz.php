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
class catquiz implements course_completion {

    /** @var int $id Standard Conditions have hardcoded ids. */
    public $id = COURSES_COND_MANUALLY;
    /** @var string $type of the redered condition in frontend. */
    public $type = 'catquiz';
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
    public function get_description():array {
        $description = $this->get_description_string();
        $name = $this->get_name_string();
        $label = $this->type;

        return [
            'id' => $this->id,
            'name' => $name,
            'description' => $description,
            'label' => $label,
            'type' => $this->type,
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
    private function get_name_string() {
        $description = get_string('course_name_condition_catquiz', 'local_adele');
        return $description;
    }

    /**
     * Helper function to return localized description strings.
     * @param array $node
     * @param int $userid
     * @return boolean
     */
    public function get_completion_status($node, $userid) {
        global $DB;
        $catquizzes = [];
        foreach ($node['completion']['nodes'] as $complitionnode) {
            if ($complitionnode['data']['type'] == 'catquiz') {
                $validcatquiz = false;
                $testid = $complitionnode['data']['value']['test_id'];
                $scales = $complitionnode['data']['value']['scales'];
                foreach ($scales as $scale) {
                    if (isset($scale['scale']) && $scale['scale'] != '') {
                        // Check if scale matches.
                        $params = [
                            'userid' => $userid,
                            'scaleid' => $scale['id'],
                        ];
                        $record = $DB->get_record('local_catquiz_attempts', $params, 'personability_after_attempt');
                        if ($record && $record->personability_after_attempt >= $scale['scale']) {
                            $validcatquiz = true;
                        }
                    }
                    if (isset($scale['attempts']) && (!isset($scale['scale']) || $validcatquiz || ($scale['scale'] == '') )) {
                        // Check if attempts matches.
                        $params = [
                            'userid' => $userid,
                            'scaleid' => $scale['id'],
                        ];
                        $records = $DB->get_records('local_catquiz_attempts', $params);
                        if (count($records) >= $scale['attempts']) {
                            $validcatquiz = true;
                        }
                    }
                    $catquizzes[$complitionnode['id']][$scale['id']] = $validcatquiz;
                }
            }
        }
        return [$this->type => $catquizzes];
    }
}
