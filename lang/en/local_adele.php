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
$string['route_not_found'] = 'Page was not found. Please consider going back and trying it again.';

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

// Button strings.
$string['btnadele'] = 'Learning Paths';
$string['btnbacktooverview'] = 'Going back to overview';
$string['btncreatecourse'] = 'Go to page and create a course';
$string['btnsave'] = 'save';
$string['btncancel'] = 'cancel';
$string['btnupdate_positions'] = 'update positions';
$string['btntoggle'] = 'change view';

// From Strings.
$string['fromlearningtitel'] = 'Learning Path Titel';
$string['fromlearningdescription'] = 'Description of Learning Path';
$string['fromavailablecourses'] = 'List of available courses';
$string['tagsearch_description'] = 'To search for courses, start the tag-name with an #';
$string['fromlearningtitelplaceholder'] = 'Please provide a titel';
$string['fromlearningdescriptionplaceholder'] = 'Please provide a short description';
$string['placeholder_search'] = 'Search courses';
$string['edit_course_node'] = 'Edit course node';
$string['edit_node_pretest'] = 'Edit completion criteria';


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
$string['warning_empty_space'] = 'Please watch for whitespaces and do not end with a comma';
$string['settings_only_subscribed'] = 'Only courses the teacher is subscribed to.';
$string['settings_all_courses'] = 'All courses meeting the other criteria.';

// Notifications.
$string['title_duplicate'] = 'Learning Path duplicated';
$string['description_duplicate'] = 'You have duplicated the Learning Path!';
$string['title_delete'] = 'Learning Path deleted';
$string['description_delete'] = 'You have deleted the Learning Path!';
$string['title_save'] = 'Learning Path saved/updated';
$string['description_save'] = 'You have saved/updated the Learning Path!';

// Node Strings.
$string['node_coursefullname'] = 'Full Coursename:';
$string['node_courseshortname'] = 'Short Coursename:';

// Conditions description.
$string['course_description_condition_manually'] = 'Course will be completed manually';
$string['course_name_condition_manually'] = 'Course completion checkbox';
$string['course_label_condition_manually'] = 'manually';
$string['course_description_condition_timed'] = 'Course has to be completed due to a given date ';
$string['course_name_condition_timed'] = 'Course determination date';
$string['course_label_condition_timed'] = 'timed';
$string['course_description_condition_course_completed'] = 'Course has been completed by student';
$string['course_name_condition_course_completed'] = 'Course completed';
$string['course_label_condition_course_completed'] = 'info_text';

// Event Strings.
$string['event_learnpath_deleted'] = 'Learning path deleted';
$string['event_learnpath_deleted_description'] = 'The learning path {$a} was deleted';
$string['event_learnpath_updated'] = 'Learning path updated';
$string['event_learnpath_updated_description'] = 'The learning path {$a} was updated';
$string['event_learnpath_created'] = 'Learning path created';
$string['event_learnpath_created_description'] = 'The learning path {$a} was created';
$string['event_completion_criteria_updated'] = 'Completion criteria updated';
$string['event_completion_criteria_updated_description'] = 'The completion criteria {$a} was updated';
$string['event_user_path_updated'] = 'User path relation was updated';
$string['event_user_path_updated_description'] = 'The user path path relation for user {$a->user} and learning path {$a->path} was updated';
