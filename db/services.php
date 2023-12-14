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
 * adele plugin external functions and service definitions.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_adele_get_availablecourses' => [
        'classname' => 'local_adele\external\get_availablecourses',
        'classpath' => '',
        'description' => 'Get all available courses.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_save_learningpath' => [
        'classname' => 'local_adele\external\save_learningpath',
        'classpath' => '',
        'description' => 'Save a specific learning path.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_adele_get_learningpaths' => [
        'classname' => 'local_adele\external\get_learningpaths',
        'classpath' => '',
        'description' => 'Get all learning goals.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_get_learningpath' => [
        'classname' => 'local_adele\external\get_learningpath',
        'classpath' => '',
        'description' => 'Get a specific learning goal.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_delete_learningpath' => [
        'classname' => 'local_adele\external\delete_learningpath',
        'classpath' => '',
        'description' => 'Delete a specific learning path.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_adele_duplicate_learningpath' => [
        'classname' => 'local_adele\external\duplicate_learningpath',
        'classpath' => '',
        'description' => 'Duplicate a specific learning goal.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_adele_get_completions' => [
        'classname' => 'local_adele\external\get_completions',
        'classpath' => '',
        'description' => 'Get all completions.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_get_user_path_relations' => [
        'classname' => 'local_adele\external\get_lp_user_path_relations',
        'classpath' => '',
        'description' => 'Get all users enrolled in a learningpath',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
];
