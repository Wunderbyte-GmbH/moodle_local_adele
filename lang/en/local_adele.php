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
 * Plugin strings are defined here.
 *
 * @package     local_adele
 * @category    string
 * @copyright   2019 Luca Bösch <luca.boesch@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Required strings.
$string['modulename'] = 'adele';
$string['modulenameplural'] = 'adeles';
$string['modulename_help'] = 'adele';
$string['pluginadministration'] = 'adele Administration';
$string['pluginname'] = 'adele';

// Capabilities.
$string['adele:edit'] = 'Edit adele';
$string['adele:view'] = 'View adele';

// Vue component route not found.
$string['route_not_found_site_name'] = 'Error: Page (route) not found!';
$string['route_not_found'] = 'Page was not found. Please consider going back and and trying it again.';

// Vue component learning goal edit.
$string['learninggoals_edit_site_name'] = 'adele learning goals';
$string['learninggoals_edit_site_description'] = 'You may add a new goal or edit existing goals.';
$string['learninggoals_edit_no_learninggoals'] = 'There are no goals to show.';

// Learning goals overview.
$string['edit'] = 'Edit';
$string['delete'] = 'Delete';
$string['deletepromptpre'] = 'Do you really want to delete the learning goal "';
$string['deletepromptpost'] = '"?';
$string['btnconfirmdelete'] = 'Confirm delete';
$string['duplicate'] = 'Duplicate';
$string['toclipboard'] = 'Copy to clipboard';
$string['goalnameplaceholder'] = 'Learning goal name';
$string['toclipboarddone'] = 'Copied to clipboard';
$string['subject'] = 'Subject';


// Learning goal form.
$string['learninggoal'] = 'Goal';
$string['learninggoal_name'] = 'Goal name';
$string['learninggoal_description'] = 'Goal description';
$string['learninggoal_form_title_add'] = 'Add a new learning path';
$string['learninggoal_form_title_edit'] = 'Edit a learning path';
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';

// Tabs.
$string['thinkingskill'] = 'Thinking Skill';
$string['content'] = 'Content';
$string['resources'] = 'Resources';
$string['products'] = 'Products';
$string['groups'] = 'Groups';

// Words.
$string['prethinkingskill'] = 'Students will';
$string['clicktoedit'] = '[click to edit]';
$string['preresource'] = 'using';
$string['preproduct'] = 'and create';
$string['pregroup'] = 'in groups of';

// Button strings.
$string['btnadelebtn'] = 'Learning Paths';
$string['btnbacktooverview'] = 'Going back to overview';

// From strings.
$string['fromlearningtitel'] = 'Learning Path Tiel';
$string['fromlearningdescription'] = 'Description of Learning Path';
$string['fromlearningtitelplaceholder'] = 'Please provide a titel';
$string['fromlearningdescriptionplaceholder'] = 'Please provide a short description';
