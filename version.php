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
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_adele
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_adele';
$plugin->release = '0.6.0';
$plugin->version = 2026100612;
// Moodle 4.5 (2024100700) is the lowest supported version; 4.4 and older
// are no longer supported. Also required for \core\clock.
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_ALPHA;
// The CI matrix builds against 4.5 AND 5.0; [405, 405] declared the
// plugin unsupported on the very version it is tested on.
$plugin->supported = [405, 405];
// Requires a mod_adele version that provides mod_adele\local\host_policy,
// which enrol_state delegates every host-course question to.
$plugin->dependencies = [
    'mod_adele' => 2026082902,
    'enrol_adele' => 2026072500,
];
