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

global $CFG, $DB;

$learninggoalid = optional_param('id', 0, PARAM_INT);

if ($learninggoalid > 0) {
    $path = '/local/adele/index.php/learninggoals/edit/' . $learninggoalid . '/';
    redirect(new \moodle_url($path));
}

require_login();

// Set page context.
$PAGE->set_context(context_system::instance());
// Set page layout.
$PAGE->set_pagelayout('standard');

$PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'local_adele'));
$PAGE->set_heading($SITE->fullname);
$PAGE->set_url(new moodle_url('/local/adele/index.php'));
$PAGE->navbar->ignore_active(true);
$PAGE->navbar->add(get_string('pluginname', 'local_adele'), new moodle_url('/local/adele/index.php'));
$PAGE->set_pagelayout('admin');

$output = $PAGE->get_renderer('local_adele');

echo $OUTPUT->header();
$PAGE->requires->js_call_amd('local_adele/app-lazy', 'init');

echo <<<'EOT'
<div id="local-adele-app">
  <router-view></router-view>
</div>
EOT;

echo $OUTPUT->footer();
