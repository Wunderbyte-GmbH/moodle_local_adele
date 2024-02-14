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
 * Moodle hooks for local_adele
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('COURSES_COND_CATQUIZ', 170);
define('COURSES_COND_MODQUIZ', 160);
define('COURSES_COND_MANUALLY', 150);
define('COURSES_COND_TIMED', 140);

define('COURSES_PRIORITY_BEST', 1);
define('COURSES_PRIORITY_SECOND', 2);
define('COURSES_PRIORITY_THIRD', 3);
define('COURSES_PRIORITY_FORTH', 4);
define('COURSES_PRIORITY_WARNING', 5);


/**
 * Renders the popup Link.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_adele_render_navbar_output(\renderer_base $renderer) {
    global $CFG;

    // Early bail out conditions.
    if (!isloggedin() || isguestuser()
        || !has_capability('local/adele:canmanage', context_system::instance())) {
        return;
    }

    $output = '<div class="popover-region nav-link icon-no-margin dropdown">
        <a class="btn btn-secondary"
        id="dropdownMenuButton" aria-haspopup="true" aria-expanded="false" href="'
            . $CFG->wwwroot . '/local/adele/index.php#/learningpaths"
        role="button">
        '. get_string('btnadele', 'local_adele') .'
        </a>
    </div>';

    return $output;
}
