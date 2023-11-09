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
 * catquiz catscales view page
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_catquiz\catquiz;
use local_catquiz\output\catscalemanager\managecatscaledashboard;

require_once('../../config.php');

global $USER;
$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/adele:canmanage', $context);

$PAGE->set_url(new moodle_url('/local/adele/manage_adeles.php', []));

$title = get_string('adelemanager', 'local_adele');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$PAGE->requires->js_call_amd('local_adele/app-lazy');

echo $OUTPUT->header();

echo <<<'EOT'
<div id="app"></div>
EOT;

echo $OUTPUT->footer();
