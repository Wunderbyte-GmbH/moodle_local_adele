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
 * The adele learning goal exporter for web service.
 *
 * @package     local_adele
 * @copyright   2019 Luca Bösch <luca.boesch@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\external\exporter;

/**
 * Class learninggoal
 *
 * @package     local_adele
 * @copyright   2019 Luca Bösch <luca.boesch@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class availablecourse extends \core\external\exporter {
    /**
     * @var
     */
    protected $learninggoal;

    /**
     * The learning goal constructor.
     *
     * @param \learninggoal $learninggoal
     * @param \context $context
     * @throws \coding_exception
     */
    public function __construct($learninggoal, \context $context) {
        $this->learninggoal = $learninggoal;
        parent::__construct([], ['context' => $context]);
    }

    /**
     * Return the id, name, description and elements of the learning goal.
     *
     * @return array
     */
    protected static function define_other_properties() {
        return [
            'id' => [
                'type' => PARAM_INT,
                'description' => 'learning goal id',
            ],
            'name' => [
                'type' => PARAM_TEXT,
                'description' => 'learning goal name',
            ],
            'description' => [
                'type' => PARAM_TEXT,
                'description' => 'learning goal description',
            ],
        ];
    }

    /**
     * Return the list of properties.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'context' => 'context',
        ];
    }

    /**
     * Get id, name and description of the learning goal.
     *
     * @param \renderer_base $output
     * @return array
     */
    protected function get_other_values(\renderer_base $output) {
        return [
            'id' => $this->learninggoal->id,
            'name' => $this->learninggoal->fullname,
            'description' => $this->learninggoal->shortname,
        ];
    }
}
