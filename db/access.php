<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin capabilities are defined here.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
        'local/adele:canmanage' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => [
                'manager' => CAP_ALLOW,
                'teacher' => CAP_ALLOW,
            ],
        ],
        'local/adele:assist' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => [
                'manager' => CAP_ALLOW,
                'teacher' => CAP_ALLOW,
            ],
        ],
        // TAKE PART in the learning paths a course embeds (#587). Whoever is
        // enrolled in such a course with a role that holds this becomes a
        // learner of the path; whoever teaches there does not.
        'local/adele:participate' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_COURSE,
            'archetypes' => [
                'student' => CAP_ALLOW,
            ],
        ],
        // SEE the learners of an embedded learning path and their paths
        // (mod_adele #39). Seeing is not changing: a teacher without editing
        // rights holds this one, not local/adele:teacheredit.
        'local/adele:viewparticipants' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_COURSE,
            'archetypes' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW,
            ],
        ],
        // CHANGE a learner's path: manual completion, manual release, master
        // checkboxes. Not for the 'teacher' archetype - that is the teacher
        // WITHOUT editing rights (mod_adele #39).
        'local/adele:teacheredit' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_COURSE,
            'archetypes' => [
                'editingteacher' => CAP_ALLOW,
                // A course Manager must operate a learning path like an editing teacher
                // (#482): grant them the same course-level editor capability.
                'manager' => CAP_ALLOW,
            ],
        ],
        'local/adele:view' => [
            'captype' => 'view',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => [
                'user' => CAP_ALLOW,
            ],
        ],
        'local/adele:edit' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => [
                'user' => CAP_ALLOW,
            ],
        ],
    ];
