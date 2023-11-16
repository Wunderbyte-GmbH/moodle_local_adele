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
 * This page is the entry page into the mod.
 *
 * @package    local_adele
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_login();

$coursemoduleid = optional_param('id', 0, PARAM_INT);
if ($coursemoduleid > 0) {
    $path = '/local/adele/view.php/' . $coursemoduleid . '/';
    redirect(new \moodle_url($path));
}

$title = get_string('modulename', 'local_adele');

$PAGE->set_context($coursemodule->context ?? null);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');

$url = new moodle_url('/local/adele/view.php', ['id' => $coursemoduleid]);
$PAGE->set_url($url);

$PAGE->requires->js_call_amd('local_adele/app-lazy', 'init');

echo $OUTPUT->header();

echo <<<'EOT'
<div id="app"></div>
EOT;

echo $OUTPUT->footer();
