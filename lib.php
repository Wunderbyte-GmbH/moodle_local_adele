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

define('COURSES_COND_MASTER', 200);
define('COURSES_COND_NODE_FINISHED', 190);
define('COURSES_COND_PARENT_NODE', 180);
define('COURSES_COND_CATQUIZ', 170);
define('COURSES_COND_MODQUIZ', 160);
define('COURSES_COND_MANUALLY', 150);
define('COURSES_COND_TIMED', 140);

define('COURSES_PRIORITY_BEST', 1);
define('COURSES_PRIORITY_SECOND', 2);
define('COURSES_PRIORITY_THIRD', 3);

define('SESSION_KEY_ADELE', 'LOCAL_ADELE_EDITOR');

/**
 * Renders the popup Link.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_adele_render_navbar_output(\renderer_base $renderer) {
    global $CFG, $DB, $USER, $_SESSION;
    require_login();
    if (!isset($_SESSION[SESSION_KEY_ADELE])) {
        $params = [
            'userid' => (int)$USER->id,
        ];

        $sql = "SELECT lpe.learningpathid
            FROM {local_adele_lp_editors} lpe
            WHERE lpe.userid = :userid";
        $_SESSION[SESSION_KEY_ADELE] = $DB->get_records_sql($sql, $params);
    }
    if (
        !isloggedin() ||
        isguestuser()
      ) {
        return;
    }

    if (
        has_capability('local/adele:canmanage', context_system::instance()) ||
        !empty($_SESSION[SESSION_KEY_ADELE])
    ) {
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
    return;
}

/**
 *  Callback checking permissions and preparing the file for serving plugin files, see File API.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function local_adele_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Check the contextlevel is as expected - if your plugin is a block.
    // We need context course if wee like to acces template files.
    if (!in_array($context->contextlevel, [CONTEXT_SYSTEM])) {
        return false;
    }

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        // Var $args is empty => the path is '/'.
        $filepath = '/';
    } else {
        // Var $args contains elements of the filepath.
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_adele', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // Send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, 0, 0, true, $options);
}
