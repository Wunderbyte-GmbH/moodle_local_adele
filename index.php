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
 * Local Differentiator main view.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('lib.php');
use local_adele\learning_paths;

global $CFG, $DB, $USER;

$learningpathid = optional_param('id', 0, PARAM_INT);

if ($learningpathid > 0) {
    $path = '/local/adele/index.php/learningpaths/edit/' . $learningpathid . '/';
    redirect(new \moodle_url($path));
}

require_login();

$context = context_system::instance();
// Set page context.
$PAGE->set_context($context);

// Set page layout.
$PAGE->set_pagelayout('base');

$PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'local_adele'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_url(new moodle_url('/local/adele/index.php#/learningpaths'));
$PAGE->navbar->ignore_active(true);
$PAGE->navbar->add(get_string('pluginname', 'local_adele'), new moodle_url('/local/adele/index.php#/learningpaths'));

$output = $PAGE->get_renderer('local_adele');
echo $OUTPUT->header();
$view = null;

$hasaccess = learning_paths::check_access();
$learningpaths = learning_paths::return_learningpaths();
if (has_capability('local/adele:canmanage', $context)) {
    $view = 'manager';
} else if (
    $hasaccess
) {
    $view = 'teacheredit';
}
echo $OUTPUT->render_from_template('local_adele/initview', [
  'userid' => $USER->id,
  'contextid' => $context->id,
  'quizsetting' => get_config('local_adele', 'quizsettings'),
  'wwwroot' => $CFG->wwwroot,
  'view' => $view,
  'editablepaths' => json_encode($learningpaths ?? []),
  'version' => $CFG->version,
]);

echo $OUTPUT->footer();
