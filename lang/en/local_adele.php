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
 * Strings for local:adele, language en
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Required strings.
$string['modulename'] = 'adele';
$string['modulenameplural'] = 'adeles';
$string['modulename_help'] = 'adele';
$string['pluginadministration'] = 'adele Administration';
$string['pluginname'] = 'Adele';

// Capabilities.
$string['adele:edit'] = 'Edit adele';
$string['adele:view'] = 'View adele';

// Vue component route not found.
$string['route_not_found_site_name'] = 'Error: Page (route) not found!';
$string['route_not_found'] = 'Page was not found. Please consider going back and and trying it again.';

// Vue component learning goal edit.
$string['learninggoals_edit_site_name'] = 'adele learning goals';
$string['learninggoals_edit_site_description'] = 'You may add a new learning path or edit existing paths.';
$string['learninggoals_edit_site_no_learningpaths'] = 'No learning paths to show yet.';
$string['learninggoals_edit_no_learninggoals'] = 'There are no goals to show.';

// Learning goals overview.
$string['edit'] = 'Edit';
$string['delete'] = 'Delete';
$string['deletepromptpre'] = 'Do you really want to delete the learning goal "';
$string['deletepromptpost'] = '"?';
$string['btnconfirmdelete'] = 'Confirm delete';
$string['duplicate'] = 'Duplicate';
$string['toclipboard'] = 'Copy to clipboard';
$string['goalnameplaceholder'] = 'Learning path name';
$string['goalsubjectplaceholder'] = 'Learning path description';
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
$string['btnadele'] = 'Learning Paths';
$string['btnbacktooverview'] = 'Going back to overview';

// From Strings.
$string['fromlearningtitel'] = 'Learning Path Tiel';
$string['fromlearningdescription'] = 'Description of Learning Path';
$string['fromavailablecourses'] = 'List of available courses';
$string['fromlearningtitelplaceholder'] = 'Please provide a titel';
$string['fromlearningdescriptionplaceholder'] = 'Please provide a short description';

// Overview String.
$string['overviewlearningpaths'] = 'Overview of all Learningpaths';
$string['overviewaddingbtn'] = 'Create new learning path';

// Adele Settings.
$string['activefilter'] = 'Activate filter';
$string['activefilter_desc'] = 'The filters will effect the available courses for the creation of learningpaths';

$string['courselevel'] = 'Choose course level';
$string['courselevel_desc'] = 'Decide which course level is shown inside the creation of learningpaths';

$string['tagsinclude'] = 'Define included tags';
$string['tagsinclude_desc'] = 'Define which courses according to their tags will be filtered. Courses with one of those tags will be filtered';

$string['tagsexclude'] = 'Define excluded tags';
$string['tagsexclude_desc'] = 'Define which courses according to their tags will not filtered. Courses with one of those tags will not be filtered';

$string['categories'] = 'Define category level';
$string['categories_desc'] = 'Define course-level should be included';

$string['tag_invalid'] = 'Following tags were not found: {$a}';
