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
    'local_adele_get_restrictions' => [
        'classname' => 'local_adele\external\get_restrictions',
        'classpath' => '',
        'description' => 'Get all restrictions.',
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
    'local_adele_get_user_path_relation' => [
        'classname' => 'local_adele\external\get_lp_user_path_relation',
        'classpath' => '',
        'description' => 'Get single users enrolled in a learningpath',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_save_user_path_relation' => [
        'classname' => 'local_adele\external\save_lp_user_path_relation',
        'classpath' => '',
        'description' => 'Save single users path relation',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_get_catquiz_tests' => [
        'classname' => 'local_adele\external\get_catquiz_tests',
        'classpath' => '',
        'description' => 'Get catquiz tests',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_get_catquiz_scales' => [
        'classname' => 'local_adele\external\get_catquiz_scales',
        'classpath' => '',
        'description' => 'Get catquiz scales',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_get_mod_quizzes' => [
        'classname' => 'local_adele\external\get_mod_quizzes',
        'classpath' => '',
        'description' => 'Get mod quizzes',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_get_catquiz_parent_scales' => [
        'classname' => 'local_adele\external\get_catquiz_parent_scales',
        'classpath' => '',
        'description' => 'Get catquiz parent scales',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/adele:edit',
    ],
    'local_adele_get_catquiz_parent_scale' => [
      'classname' => 'local_adele\external\get_catquiz_parent_scale',
      'classpath' => '',
      'description' => 'Get catquiz parent subscale',
      'type' => 'read',
      'ajax' => true,
      'capabilities' => 'local/adele:edit',
    ],
    'local_adele_get_image_paths' => [
      'classname' => 'local_adele\external\get_image_paths',
      'classpath' => '',
      'description' => 'Get image paths',
      'type' => 'read',
      'ajax' => true,
      'capabilities' => 'local/adele:edit',
    ],
    'local_adele_upload_lp_image' => [
      'classname' => 'local_adele\external\set_new_image',
      'classpath' => '',
      'description' => 'Set new image',
      'type' => 'read',
      'ajax' => true,
      'capabilities' => 'local/adele:edit',
    ],
];
