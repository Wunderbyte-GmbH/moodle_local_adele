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
$string['modulename'] = 'learning path';
$string['modulenameplural'] = 'learning paths';
$string['modulename_help'] = 'learning path';
$string['pluginadministration'] = 'learning path Administration';
$string['pluginname'] = 'Learning path';
$string['not_found'] = 'Learning path not found! Please contact person in charge!';

// Capabilities.
$string['adele:edit'] = 'Edit learning path';
$string['adele:view'] = 'View learning path';
$string['adele:canmanage'] = 'Is allowed to manage Learning path Plugins';

// Role.
$string['adeleroledescription'] = 'Learning path Manager';

// Vue component route not found.
$string['route_not_found_site_name'] = 'Error: Page (route) not found!';
$string['route_not_found'] = 'Page was not found. Please consider going back and trying it again.';

// Vue component learning goal edit.
$string['learningpaths_edit_site_name'] = 'Learning path goals';
$string['learningpaths_edit_site_description'] = 'You may add a new learning path or edit existing paths.';
$string['learningpaths_edit_site_no_learningpaths'] = 'No learning paths to show yet.';
$string['learningpaths_edit_no_learningpaths'] = 'There are no goals to show.';

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
$string['learningpath'] = 'Goal';
$string['learningpath_name'] = 'Goal name';
$string['learningpath_description'] = 'Goal description';
$string['learningpath_form_title_add'] = 'Add a new learning path';
$string['learningpath_form_title_edit'] = 'Edit a learning path';
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
$string['btndarktoggle'] = 'Dark view';
$string['btnlighttoggle'] = 'Light view';
$string['btnstudenttoggle'] = 'Student view';
$string['btneditortoggle'] = 'Editor view';
$string['btnreload'] = 'Reload Page';

// From Strings.
$string['fromlearningtitel'] = 'Learning Path Titel';
$string['fromlearningdescription'] = 'Description of Learning Path';
$string['fromavailablecourses'] = 'List of available courses';
$string['tagsearch_description'] = 'For tag-search, start with #';
$string['fromlearningtitelplaceholder'] = 'Please provide a titel';
$string['fromlearningdescriptionplaceholder'] = 'Please provide a short description';
$string['placeholder_search'] = 'Search courses';
$string['placeholder_lp_search'] = 'Search learning path';
$string['edit_course_node'] = 'Edit node';
$string['edit_node_pretest'] = 'Edit completion criteria';
$string['from_default_node_image'] = 'Default node image:';

// Overview String.
$string['overviewlearningpaths'] = 'Overview of all learning paths';
$string['overviewaddingbtn'] = 'Create new learning path';

// Adele Settings.
$string['activefilter'] = 'Activate filter';
$string['activefilter_desc'] = 'The filters will effect the available courses for the creation of learning paths';
$string['courselevel'] = 'Choose course level';
$string['courselevel_desc'] = 'Decide which course level is shown inside the creation of learning paths';
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
$string['image_title_save'] = 'Learning Path image has been saved/updated';
$string['image_description_save'] = 'You have saved/updated the Learning Path image!';


// Node Strings.
$string['node_coursefullname'] = 'Nodename:';
$string['node_courseshortname'] = 'Short node name:';

// Conditions description.
$string['course_description_condition_completion_manual'] = 'Node will be completed manually';
$string['course_description_condition_parent_courses'] = 'Node will be accessible if a certain amount of parent nodes are completed';
$string['course_name_condition_completion_manual'] = 'Node completion checkbox';
$string['course_name_condition_parent_courses'] = 'According to parent nodes';
$string['course_description_condition_restriction_manual'] = 'Access will be granted manually';
$string['course_name_condition_restriction_manual'] = 'Node restriction checkbox';
$string['course_description_condition_restriction_specific_course'] = 'Only if a certain node of this learning path is completed';
$string['course_name_condition_restriction_specific_course'] = 'Certain node completed';
$string['course_description_condition_timed'] = 'Node start date; Node end date';
$string['course_name_condition_timed'] = 'Node start/end date';
$string['course_name_condition_course_completed'] = 'Course(s) completed';
$string['course_description_condition_catquiz'] = 'Accroding to catquiz results/attempts';
$string['course_name_condition_catquiz'] = 'Catquiz Quiz';
$string['course_description_condition_modquiz'] = 'Accroding to mod Quiz result';
$string['course_name_condition_modquiz'] = 'Mod Quiz';
$string['course_description_condition_parent_node_completed'] = 'If one parent node is finished';
$string['course_name_condition_parent_node_completed'] = 'Parent node finished';
$string['course_description_condition_timed_duration'] = 'Duration in which it is possible to edit the course';
$string['course_name_condition_timed_duration'] = 'Course edit duration';
$string['course_select_condition_timed_duration_learning_path'] = 'Since learning path subscription';
$string['course_select_condition_timed_duration_node'] = 'Since node subscription';
$string['course_select_condition_timed_duration_days'] = 'days';
$string['course_select_condition_timed_duration_weeks'] = 'weeks';
$string['course_select_condition_timed_duration_months'] = 'months';

// Feedback Strings.
$string['node_access_completed'] = 'The node is completed';
$string['node_access_accessible'] = 'The node is accessible';
$string['node_access_not_accessible'] = 'The node is not accessible yet';
$string['node_access_closed'] = 'The node is no longer accessible.';
$string['course_description_condition_course_completed'] = 'One course inside this node has to be completed by student';
$string['course_description_before_completion_manual'] = 'Completion will be granted manually';
$string['course_description_inbetween_completion_manual'] = 'Completion was not granted manaully yet';
$string['course_description_after_completion_manual'] = 'Completion was granted manually';
$string['course_description_before_condition_course_completed'] = 'Courses of this node have to be finshed';
$string['course_description_after_condition_course_completed'] = 'You finished enough courses of this node';
$string['course_description_inbetween_condition_course_completed'] = 'Current best completion grade: {course progress}%';
$string['course_description_before_condition_catquiz'] = 'Complete the catquiz results/attempts';
$string['course_description_after_condition_catquiz'] = 'Cat quiz was successfully finished';
$string['course_description_inbetween_condition_catquiz'] = 'Current best cat quiz results: {best catquiz}';
$string['course_description_before_condition_modquiz'] = 'Quiz must be finished with certain score';
$string['course_description_inbetween_condition_modquiz'] = 'Quiz has not been finished good enough. Current best quiz attempt: {best quiz}';
$string['course_description_after_condition_modquiz'] = 'Quiz has been successfully finished';
$string['course_restricition_before_condition_manual'] = 'Access will be granted manually';
$string['course_restricition_before_condition_parent_courses'] = 'Finish following 3/4 courses of parent node';
$string['course_restricition_before_condition_specific_course'] = 'Finish the courses {placeholder}';
$string['course_restricition_before_condition_timed'] = 'Accessible from {START} until {END}';
$string['course_restricition_before_condition_timed_duration'] = 'Accessible for {WEEKS} since {TIMED CONDITION}';
$string['course_restricition_before_condition_parent_node_completed'] = 'Finish the parent node';
$string['course_condition_concatination_or'] = "or";
$string['course_condition_concatination_and'] = " and ";

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
$string['event_node_finished'] = 'Node weas finished';
$string['event_node_finished_description'] = 'The user {$a->user} has finished the node {$a->node}';

// Color strings.
$string['DARK_GREEN'] = '#063449';
$string['DEEP_SKY_BLUE'] = '#0d5575';
$string['LIGHT_SEA_GREEN'] = '#4d8da8';
$string['LIGHT_STEEL_BLUE'] = '#87b8ce';
$string['DARK_RED'] = '#750033';
$string['CRIMSON'] = '#ad0050';
$string['DARK_ORANGE'] = '#df843b';
$string['RUSTY_RED'] = '#c76413';
$string['PUMPKIN'] = '#e7a23b';
$string['LIGHT_GRAY'] = '#d1d1d1';
$string['GRAY'] = '#ababab';
$string['DIM_GRAY'] = '#737373';
$string['VERY_DARK_GRAY'] = '#373737';
$string['BLACK'] = '#0c0c0c';

// Charthelper strings.
$string['charthelper_child_nodes'] = 'Child Nodes:';
$string['charthelper_no_child_nodes'] = 'No child nodes found.';
$string['charthelper_parent_nodes'] = 'Parent Nodes:';
$string['charthelper_no_parent_nodes'] = 'No parent nodes found.';
$string['charthelper_no_name'] = 'No name provided.';
$string['charthelper_no_description'] = 'No description provided.';
$string['charthelper_go_to_learningpath'] = 'Go to learning path editing.';

// Conditions strings.
$string['conditions_no_scales'] = 'No scales available';
$string['conditions_name'] = 'Name';
$string['conditions_scale_value'] = 'Scale value:';
$string['conditions_attempts'] = 'Correct answers in %:';
$string['conditions_set_values'] = 'Set Values';
$string['conditions_catquiz_warning_description'] = 'The Catquiz that is inside the same course as the Learning path';
$string['conditions_catquiz_warning_name'] = 'Catquiz inside course';
$string['conditions_min_grad'] = 'Min. Grade:';
$string['conditions_finish_course'] = 'Finish node manually';

// Completion strings.
$string['completion_invalid_path_title'] = 'Invalid Path';
$string['completion_invalid_path_text'] = 'Found standalone nodes. Every node must be connected to the path.';
$string['completion_go_back_learningpath'] = 'Go Back to Learningpath';
$string['completion_edit_completion'] = 'Edit Completion criteria of course node';
$string['completion_completion_for'] = 'Completion Criteria for:';
$string['completion_course_title'] = 'Node Title:';
$string['completion_course_tags'] = 'Tags:';
$string['completion_node_refused_title'] = 'Node drop refused';
$string['completion_node_refused_text'] = 'Please drop the node in the dropzones, which will be shown if you drag a node to an exsisting node.';
$string['completion_feedback_node'] = 'Feedback node';
$string['completion_loading_completion'] = 'Loading completion...';
$string['completion_drop_here'] = 'Drop to connect here';
$string['completion_drop_zone'] = 'Drop zone';
$string['completion_list_of_criteria'] = 'List of available ';
$string['completion_criteria'] = ' criteria';

// Flowchart strings.
$string['flowchart_add_learning'] = 'Add a learning module';
$string['flowchart_title'] = 'Title:';
$string['flowchart_please_provide'] = 'Please provide a name!';
$string['flowchart_color'] = 'Color:';
$string['flowchart_cancel'] = 'Cancel:';
$string['flowchart_cancel_button'] = 'Discard changes';
$string['flowchart_add'] = 'Add';
$string['flowchart_existing_learning_modules'] = 'Existing learning modules';
$string['flowchart_provide_name'] = 'Please provide a name!';
$string['flowchart_save_button'] = 'Save';
$string['flowchart_delete_button'] = 'Delete';
$string['flowchart_save_notification_title'] = 'Saved failed';
$string['flowchart_save_notification_text_missing_strings'] = 'Provide a title and a short description for the learning path';
$string['flowchart_invalid_path_notification_title'] = 'Invalid Path';
$string['flowchart_save_notification_text'] = 'Found standalone nodes. Every node must be connected to the path';
$string['flowchart_cancel_confirmation'] = 'All unsaved changes will be lost';
$string['flowchart_back_button'] = 'Continue edit';
$string['flowchart_course_already_inside_title'] = 'Course already inside';
$string['flowchart_course_already_inside_text'] = 'The course is already inside the node included';
$string['flowchart_drop_refused_title'] = 'Node drop refused';
$string['flowchart_drop_refused_text'] = 'Please drop the node in the dropzones, which will be shown if you drag a node to an exsisting node.';
$string['flowchart_learning_package'] = 'Learning package';
$string['flowchart_courses'] = 'Courses';
$string['flowchart_hover_darg_drop'] = 'Drag and drop the course inside the drop zones to include it in the learning path.';
$string['flowchart_hover_click_here'] = 'Click here to go to course';

// Modals strings.
$string['modals_edit_feedback'] = 'Edit Feedback';
$string['modals_close'] = 'Close';
$string['modals_save_changes'] = 'Save Changes';
$string['modals_how_to_learningpath'] = 'How To Learning Path';
$string['modals_previous'] = 'Previous';
$string['modals_next'] = 'Next';
$string['modals_edit'] = 'Edit';
$string['modals_longname'] = 'Longname:';
$string['modals_description'] = 'Node description:';
$string['estimate_duration'] = 'Estimated duration ';
$string['modals_no_description'] = 'No node description given...';
$string['modals_shortname'] = 'Shortname:';

// Nodes strings.
$string['nodes_collection'] = 'Collection';
$string['nodes_edit'] = 'Edit';
$string['nodes_learning_module'] = 'Learning Module';
$string['nodes_select_module'] = 'Select a module';
$string['nodes_included_courses'] = 'Included Courses';
$string['nodes_edit_restriction'] = 'Edit Restriction';
$string['nodes_edit_completion'] = 'Edit Completion';
$string['nodes_completion'] = 'Completion';
$string['nodes_restriction'] = 'Restriction';
$string['nodes_potential_start'] = 'Potential starting node';
$string['nodes_progress'] = 'Progress:';
$string['nodes_courses'] = 'Courses:';
$string['nodes_table_key'] = 'Key';
$string['nodes_table_checkmark'] = 'Checkmark';
$string['nodes_no_restriction_defined'] = 'No Restrictions are defined';
$string['nodes_no_completion_defined'] = 'No Completions are defined';
$string['nodes_hide_completion'] = 'Hide Completion';
$string['nodes_show_completion'] = 'Show Completion';
$string['nodes_feedback'] = 'Feedback';
$string['nodes_no_feedback'] = 'No feedback set...';
$string['nodes_warning_time_restriction'] = 'This restriction does not overwrite the general course accessebility times. Make sure your dates do not conflict with the course dates.';
$string['nodes_warning_time_heading'] = 'Warning!';
$string['nodes_no_description'] = 'No course description was provided';
$string['nodes_course_node'] = 'Course Node';
$string['nodes_feedback_restriction_before'] = 'To unlock node you have to:';
$string['nodes_feedback_completion_after'] = 'Node completed because:';
$string['nodes_feedback_completion_before'] = 'To complete node you have to:';
$string['nodes_feedback_completion_inbetween'] = 'Current node state:';
$string['nodes_feedback_completion_higher'] = 'Node is completed! With these, you can achieve a higher completion status:';
$string['nodes_feedback_before'] = 'Before';
$string['nodes_feedback_inbetween'] = 'Inbetween';
$string['nodes_feedback_after'] = 'After';
$string['nodes_feedback_use_default'] = 'Use default feedback';

// Nodes Items strings.
$string['nodes_items_start'] = 'Start:';
$string['nodes_items_end'] = 'End:';
$string['nodes_items_testname'] = 'Testname:';
$string['nodes_items_none'] = 'None';
$string['nodes_items_coursename'] = 'Coursename:';
$string['nodes_items_restrictions'] = 'Restrictions';
$string['nodes_items_no_conditions'] = 'No conditions are defined';
$string['nodes_items_restriction'] = 'Restriction';
$string['nodes_items_no_restrictions'] = 'No restrictions are defined';
$string['nodes_items_completion'] = 'Completion';
$string['nodes_items_no_progress'] = 'No Progress';

// Conditions strings.
$string['composables_new_node'] = 'New Starting node';
$string['composables_drop_zone_parent'] = 'Drop zone Parent';
$string['composables_drop_zone_child'] = 'Drop zone Child';
$string['composables_drop_zone_add'] = 'And drop zone';
$string['composables_drop_zone_or'] = 'Or drop zone';

$string['composables_feedback_node'] = 'Feedback node';

// Restriction strings.
$string['restriction_select_number'] = 'Select a Number:';
$string['restriction_no_select_number'] = 'No parent courses where found';
$string['restriction_select_course'] = 'Select a Course:';
$string['restriction_choose_number'] = 'Choose a number';
$string['restriction_parents_found'] = 'Found following parent nodes:';
$string['restriction_access_manually'] = 'Grant access to node manually';
$string['restriction_invalid_path_title'] = 'Invalid Path';
$string['restriction_invalid_path_text'] = 'Found standalone nodes. Every node must be connected to the path';
$string['restriction_go_back_learningpath'] = 'Go Back to Learningpath';
$string['restriction_edit_restrictions'] = 'Edit Restrictions to enter course node';
$string['restriction_restrictions_for'] = 'Restrictions for:';
$string['restriction_course_title'] = 'Node Title:';
$string['restriction_tags'] = 'Tags:';
$string['restriction_loading_restrictions'] = 'Loading restrictions...';
$string['restriction_node_drop_refused_title'] = 'Node drop refused';
$string['restriction_node_drop_refused_text'] = 'Please drop the node in the dropzones, which will be shown if you drag a node to an exsisting node.';

// User view strings.
$string['user_view_id'] = 'ID';
$string['user_view_username'] = 'Username';
$string['user_view_firstname'] = 'Firstname';
$string['user_view_lastname'] = 'Lastname';
$string['user_view_email'] = 'Email';
$string['user_view_progress'] = 'Progress';
$string['user_view_nodes'] = 'Nodes';
$string['user_view_go_back_overview'] = 'Go Back to Overview';
$string['user_view_user_path_for'] = 'User path for:';

// Main strings.
$string['main_intro_slider'] = 'Introduction slider';
$string['main_description'] = 'Description:';
$string['main_duplicate'] = 'Duplicate';
$string['main_delete'] = 'Delete';

// Mobile strings.
$string['mobile_view_buttons_path'] = 'Learning path';
$string['mobile_view_buttons_list'] = 'Node list';
$string['mobile_view_list_header'] = 'Nodes List View';
$string['mobile_view_detail_id'] = 'ID:';
$string['mobile_view_detail_back'] = 'Back';
$string['mobile_view_detail_description'] = 'Description:';
$string['mobile_view_detail_estimate'] = 'Estimated duration:';
$string['mobile_view_detail_course_link'] = 'Open course';
