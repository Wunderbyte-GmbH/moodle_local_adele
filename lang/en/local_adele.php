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

// Required strings.
$string['modulename'] = '['.__LINE__.']learning path';
$string['modulenameplural'] = '['.__LINE__.']learning paths';
$string['modulename_help'] = '['.__LINE__.']learning path';
$string['pluginadministration'] = '['.__LINE__.']learning path Administration';
$string['pluginname'] = '['.__LINE__.']Learning path';
$string['not_found'] = '['.__LINE__.']Learning path not found! Please contact person in charge!';
$string['required'] = '['.__LINE__.']required';

// Capabilities.
$string['adele:edit'] = '['.__LINE__.']Edit learning path';
$string['adele:teacheredit'] = '['.__LINE__.']Teacher edit';
$string['adele:view'] = '['.__LINE__.']View learning path';
$string['adele:canmanage'] = '['.__LINE__.']Is allowed to manage Learning path Plugins';

// Role.
$string['adeleroledescription'] = '['.__LINE__.']Learning path Manager';

// Vue component route not found.
$string['route_not_found_site_name'] = '['.__LINE__.']Error: Page (route) not found!';
$string['route_not_found'] = '['.__LINE__.']Page was not found. Please consider going back and trying it again.';

// Vue component learning goal edit.
$string['learningpaths_edit_site_name'] = '['.__LINE__.']Learning path goals';
$string['learningpaths_edit_site_description'] = '['.__LINE__.']You may add a new learning path or edit existing paths.';
$string['learningpaths_edit_site_no_learningpaths'] = '['.__LINE__.']No learning paths to show yet.';
$string['learningpaths_edit_no_learningpaths'] = '['.__LINE__.']There are no goals to show.';

// Learning goals overview.
$string['edit'] = '['.__LINE__.']Edit';
$string['view'] = '['.__LINE__.']View';
$string['delete'] = '['.__LINE__.']Delete';
$string['deletepromptpre'] = '['.__LINE__.']Do you really want to delete the learning goal "';
$string['deletepromptpost'] = '['.__LINE__.']"?';
$string['btnconfirmdelete'] = '['.__LINE__.']Confirm delete';
$string['duplicate'] = '['.__LINE__.']Duplicate';
$string['toclipboard'] = '['.__LINE__.']Copy to clipboard';
$string['goalnameplaceholder'] = '['.__LINE__.']Learning path name';
$string['goalsubjectplaceholder'] = '['.__LINE__.']Learning path description';
$string['toclipboarddone'] = '['.__LINE__.']Copied to clipboard';
$string['subject'] = '['.__LINE__.']Subject';

// Learning goal form.
$string['learningpath'] = '['.__LINE__.']Goal';
$string['learningpath_name'] = '['.__LINE__.']Goal name';
$string['learningpath_description'] = '['.__LINE__.']Goal description';
$string['learningpath_form_title_add'] = '['.__LINE__.']Add a new learning path';
$string['learningpath_form_title_edit'] = '['.__LINE__.']Edit a learning path';
$string['save'] = '['.__LINE__.']Save';
$string['cancel'] = '['.__LINE__.']Cancel';

// Button strings.
$string['btnadele'] = '['.__LINE__.']Learning Paths';
$string['btnbacktooverview'] = '['.__LINE__.']Going back to overview';
$string['btncreatecourse'] = '['.__LINE__.']Go to page and create a course';
$string['btnsave'] = '['.__LINE__.']save';
$string['btncancel'] = '['.__LINE__.']cancel';
$string['btnupdate_positions'] = '['.__LINE__.']update positions';
$string['btntoggle'] = '['.__LINE__.']change view';
$string['btndarktoggle'] = '['.__LINE__.']Dark view';
$string['btnlighttoggle'] = '['.__LINE__.']Light view';
$string['btnstudenttoggle'] = '['.__LINE__.']Student view';
$string['btneditortoggle'] = '['.__LINE__.']Editor view';
$string['btnreload'] = '['.__LINE__.']Reload Page';

// From Strings.
$string['fromlearningtitel'] = '['.__LINE__.']Learning Path Titel';
$string['fromlearningdescription'] = '['.__LINE__.']Description of Learning Path';
$string['fromavailablecourses'] = '['.__LINE__.']List of available courses';
$string['tagsearch_description'] = '['.__LINE__.']For tag-search, start with #';
$string['fromlearningtitelplaceholder'] = '['.__LINE__.']Please provide a titel';
$string['fromlearningdescriptionplaceholder'] = '['.__LINE__.']Please provide a short description';
$string['placeholder_search'] = '['.__LINE__.']Search courses';
$string['placeholder_lp_search'] = '['.__LINE__.']Search learning path';
$string['edit_course_node'] = '['.__LINE__.']Edit node';
$string['edit_node_pretest'] = '['.__LINE__.']Edit completion criteria';
$string['from_default_node_image'] = '['.__LINE__.']Default node image:';
$string['uploaddefaultimage'] = '['.__LINE__.']Upload your default node image';
$string['selectdefaultimage'] = '['.__LINE__.']Select learning path image';
$string['deselectdefaultimage'] = '['.__LINE__.']Deselect';
$string['uploadowndefaultimage'] = '['.__LINE__.']Or upload a new image:';
$string['uploadanduseimage'] = '['.__LINE__.']Upload and use image';
$string['onlysetaftersaved'] = '['.__LINE__.']Can only be set after learning path was saved';
$string['searchuser'] = '['.__LINE__.']Search users...';
$string['editordeleteconfirmation'] = '['.__LINE__.']Are you sure you want to remove this user as editor?';
$string['selectuser'] = '['.__LINE__.']Select Users';
$string['removeuser'] = '['.__LINE__.']Remove';
$string['nousersfound'] = '['.__LINE__.']No users were found';

// Overview String.
$string['overviewlearningpaths'] = '['.__LINE__.']Overview of all learning paths';
$string['overviewaddingbtn'] = '['.__LINE__.']Create new learning path';

// Adele Settings.
$string['activefilter'] = '['.__LINE__.']Activate filter';
$string['activefilter_desc'] = '['.__LINE__.']The filters will effect the available courses for the creation of learning paths';
$string['courselevel'] = '['.__LINE__.']Choose course level';
$string['courselevel_desc'] = '['.__LINE__.']Decide which course level is shown inside the creation of learning paths';
$string['tagsinclude'] = '['.__LINE__.']Define included tags';
$string['tagsinclude_desc'] = '['.__LINE__.']Define which courses according to their tags will be filtered. Courses with one of those tags will be filtered';
$string['tagsexclude'] = '['.__LINE__.']Define excluded tags';
$string['tagsexclude_desc'] = '['.__LINE__.']Define which courses according to their tags will not filtered. Courses with one of those tags will not be filtered';
$string['categories'] = '['.__LINE__.']Define category level';
$string['categories_desc'] = '['.__LINE__.']Define course-level should be included';
$string['tag_invalid'] = '['.__LINE__.']Following tags were not found: {$a}';
$string['warning_empty_space'] = '['.__LINE__.']Please watch for whitespaces and do not end with a comma';
$string['settings_only_subscribed'] = '['.__LINE__.']Only courses {$a} are subscribed to.';
$string['settings_all_courses'] = '['.__LINE__.']All courses meeting the other criteria.';
$string['single_quiz'] = '['.__LINE__.']One quiz has to fullfill all scales.';
$string['all_quiz'] = '['.__LINE__.']Among all attempts, all scales have to be fullfilled at least once.';
$string['all_quiz_global'] = '['.__LINE__.']Among all attempts with a given global value, all scales have to be fullfilled at least once.';
$string['quiz_settings'] = '['.__LINE__.']Quiz settings';
$string['quiz_settings_desc'] = '['.__LINE__.']The settings define how the quiz attempts will be verified';
$string['enroll_as_setting'] = '['.__LINE__.']Enrollment Settings';
$string['enroll_as_setting_desc'] = '['.__LINE__.']Define with which role a user is enrolled through a learning path.';

// Notifications.
$string['title_duplicate'] = '['.__LINE__.']Learning Path duplicated';
$string['description_duplicate'] = '['.__LINE__.']You have duplicated the Learning Path!';
$string['title_delete'] = '['.__LINE__.']Learning Path deleted';
$string['description_delete'] = '['.__LINE__.']You have deleted the Learning Path!';
$string['title_save'] = '['.__LINE__.']Learning Path saved/updated';
$string['description_save'] = '['.__LINE__.']You have saved/updated the Learning Path!';
$string['image_title_save'] = '['.__LINE__.']Learning Path image has been saved/updated';
$string['image_description_save'] = '['.__LINE__.']You have saved/updated the Learning Path image!';
$string['title_change_visibility'] = '['.__LINE__.']Changed learning path visibility';
$string['description_change_visibility'] = '['.__LINE__.']You have successfully changed the Learning Path visibility!';

// Node Strings.
$string['node_coursefullname'] = '['.__LINE__.']Nodename:';
$string['node_courseshortname'] = '['.__LINE__.']Short node name:';

// Conditions description.
$string['course_description_master'] = '['.__LINE__.']This condition overrules everything else';
$string['course_name_master'] = '['.__LINE__.']Master condition';
$string['course_description_condition_completion_manual'] = '['.__LINE__.']Node will be completed manually';
$string['course_description_condition_parent_courses'] = '['.__LINE__.']Node will be accessible if a certain amount of parent nodes are completed';
$string['course_name_condition_completion_manual'] = '['.__LINE__.']Node completion checkbox';
$string['course_name_condition_completion_manual_checkbox_status'] = '['.__LINE__.']not';
$string['course_name_condition_completion_manual_role_teacher'] = '['.__LINE__.']Dozenten';
$string['course_name_condition_parent_courses'] = '['.__LINE__.']According to parent nodes';
$string['course_description_condition_restriction_manual'] = '['.__LINE__.']Access will be granted manually';
$string['course_name_condition_restriction_manual'] = '['.__LINE__.']Node restriction checkbox';
$string['course_description_condition_restriction_specific_course'] = '['.__LINE__.']Only if a certain node of this learning path is completed';
$string['course_name_condition_restriction_specific_course'] = '['.__LINE__.']Certain node completed';
$string['course_description_condition_timed'] = '['.__LINE__.']Node start date; Node end date';
$string['course_name_condition_timed'] = '['.__LINE__.']Node start/end date';
$string['course_name_condition_course_completed'] = '['.__LINE__.']Course(s) completed';
$string['course_description_condition_catquiz'] = '['.__LINE__.']Accroding to catquiz results/attempts';
$string['course_name_condition_catquiz'] = '['.__LINE__.']Catquiz Quiz';
$string['no_catquiz_class'] = '['.__LINE__.']No catquiz found!';
$string['course_description_condition_modquiz'] = '['.__LINE__.']Accroding to mod Quiz result';
$string['course_name_condition_modquiz'] = '['.__LINE__.']Mod Quiz';
$string['course_description_condition_parent_node_completed'] = '['.__LINE__.']If one parent node is finished';
$string['course_name_condition_parent_node_completed'] = '['.__LINE__.']Parent node finished';
$string['course_description_condition_timed_duration'] = '['.__LINE__.']Duration in which it is possible to edit the course';
$string['course_name_condition_timed_duration'] = '['.__LINE__.']Course edit duration';
$string['course_select_condition_timed_duration_learning_path'] = '['.__LINE__.']Since learning path subscription';
$string['course_select_condition_timed_duration_node'] = '['.__LINE__.']Since node subscription';
$string['course_select_condition_timed_duration_days'] = '['.__LINE__.']days';
$string['course_select_condition_timed_duration_weeks'] = '['.__LINE__.']weeks';
$string['course_select_condition_timed_duration_months'] = '['.__LINE__.']months';

// Feedback Strings.
$string['node_access_completed'] = '['.__LINE__.']The node is completed because:';
$string['node_access_accessible'] = '['.__LINE__.']The node is accessible. Current node state:';
$string['node_access_not_accessible'] = '['.__LINE__.']The node is not accessible yet. The restrictions are:';
$string['node_access_closed'] = '['.__LINE__.']The node is no longer accessible. Please contact your admin for help. The restriction were:';
$string['node_access_nothing_defined'] = '['.__LINE__.']No user feedback available';
$string['node_access_completion_before'] = '['.__LINE__.']Um diesen Kurs/diesen Stapel abzuschließen, musst du:';
$string['node_access_completion_inbetween'] = '['.__LINE__.']Um diesen Kurs/diesen Stapel abzuschließen, müssen Sie noch:';
$string['node_access_completion_after'] = '['.__LINE__.']Dieser Kurs/dieser Stapel gilt als abgeschlossen, weil Sie:';
$string['node_access_completion_after_all'] = '['.__LINE__.']Du kannst einen höheren Kursabschluss erreichen, wenn du:';
$string['node_access_restriction_before'] = '['.__LINE__.']Sie haben keinen Zugang zu diesem Kurs/diesem Stapel. Eine Freischaltung erfolgt, wenn:';
$string['node_access_restriction_inbetween'] = '['.__LINE__.']Der Kurs/Der Stapel ist freigeschaltet:';
$string['node_access_restriction_after'] = '['.__LINE__.']Der Kurs/Der Stapel kann nicht (mehr) von Ihnen freigeschaltet werden.';
$string['course_description_condition_course_completed'] = '['.__LINE__.']One course inside this node has to be completed';

$string['course_description_before_completion_manual'] = '['.__LINE__.']durch den {Dozenten} ein manueller Abschluss verbucht werden';
$string['course_description_inbetween_completion_manual'] = '['.__LINE__.']durch den {Dozenten} ein manueller Abschluss verbucht werden';
$string['course_description_after_completion_manual'] = '['.__LINE__.']ihn erfolgreich bearbeitet haben';
$string['course_description_placeholder_checkbox_status'] = '['.__LINE__.']not';

$string['course_description_before_condition_course_completed_kurse'] = '['.__LINE__.']Kurse';
$string['course_description_before_condition_course_completed_kursen'] = '['.__LINE__.']Kursen';
$string['course_description_before_condition_course_completed_item'] = '['.__LINE__.']Den Kurs';
$string['course_description_before_condition_course_completed_aus'] = '['.__LINE__.']aus';
$string['course_description_before_condition_course_completed'] = '['.__LINE__.']{item} erfolgreich bearbeiten ';
$string['course_description_inbetween_condition_course_completed'] = '['.__LINE__.']{item} erfolgreich bearbeiten';
$string['course_description_after_condition_course_completed'] = '['.__LINE__.']{item} erfolgreich bearbeitet haben';

$string['course_description_before_condition_catquiz'] = '['.__LINE__.']beende das Quiz {quiz_name}';
$string['course_description_inbetween_condition_catquiz'] = '['.__LINE__.']deiner Ergebnisse im Test „{quiz_name}”. Denn hierüber hast Du die im Kurs zu erwerbenden Kompetenzen zu:{quiz_attempts_list} in ausreichender Form nachgewiesen.';
$string['course_description_inbetween_condition_catquiz_best'] = '['.__LINE__.']<li>„{$a->scale}” (<a href="{$a->link}" target="_blank">bestes Testergebnis am {$a->time}</a>)</li>';
$string['course_description_after_condition_catquiz'] = '['.__LINE__.']deiner Ergebnisse im Test „{quiz_name}”. Denn hierüber hast Du die im Kurs zu erwerbenden Kompetenzen zu:{quiz_attempts_list} in ausreichender Form nachgewiesen.';

$string['course_description_after_condition_modquiz_list'] = '['.__LINE__.']<li>„{$a->scale}” (<a href="{$a->link}" target="_blank">bestes Testergebnis am {$a->time}  Logit {$a->currentlogit} / {$a->targetlogit} Percentage {$a->currentperc} / {$a->targetperc}</a>)</li>';

$string['course_description_after_condition_modquiz_best'] = '['.__LINE__.']Beste Note:';

$string['course_description_before_condition_modquiz'] = '['.__LINE__.']das Quiz mit {minnumb} von {maxnumb} bestehen';
$string['course_description_inbetween_condition_modquiz'] = '['.__LINE__.']das Quiz mit {minnumb} von {maxnumb} {currentbest} bestehen';
$string['course_description_after_condition_modquiz'] = '['.__LINE__.']das Quiz mit {minnumb} von {maxnumb} bestanden haben';

$string['course_restricition_before_condition_manual'] = '['.__LINE__.']eine manuelle Freigabe durch den Lehrenden stattgefunden hat';
$string['course_restricition_before_condition_parent_courses'] = '['.__LINE__.']Sie {node_name} abgeschlossen haben';
$string['course_restricition_before_condition_specific_course'] = '['.__LINE__.']Sie {node_name} abgeschlossen haben';
$string['course_condition_timed_duration_start'] = '['.__LINE__.']from the moment of subscription to this node';
$string['course_condition_timed_duration_since'] = '['.__LINE__.']since ';
$string['course_restricition_before_condition_timed'] = '['.__LINE__.']der {start_date} erreicht wird';
$string['course_restricition_before_condition_from'] = '['.__LINE__.']from ';
$string['course_restricition_before_condition_to'] = '['.__LINE__.']to ';
$string['course_restricition_before_condition_timed_duration'] = '['.__LINE__.']zugänglich für {duration_period} {timed_condition}';
$string['course_restricition_before_condition_parent_node_completed'] = '['.__LINE__.']Sie {node_name} abgeschlossen haben';

$string['node_restriction_inbetween_timed'] = '['.__LINE__.']Sie haben bis zum {$a} Zugang zu diesem Kurs/ diesem Stapel.';
$string['node_restriction_before_timed'] = '['.__LINE__.']Nach der Freischaltung haben Sie maximal bis zum {$a} Zugang.';

$string['course_master_conditions'] = '['.__LINE__.']Master Conditions';
$string['course_master_condition_restriction'] = '['.__LINE__.']Master restriction checkbox';
$string['course_master_condition_completion'] = '['.__LINE__.']Master completion checkbox';

$string['course_condition_concatination_or'] = "or";
$string['course_condition_concatination_and'] = " and ";

// Event Strings.
$string['event_learnpath_deleted'] = '['.__LINE__.']Learning path deleted';
$string['event_learnpath_deleted_description'] = '['.__LINE__.']The learning path {$a} was deleted';
$string['event_learnpath_updated'] = '['.__LINE__.']Learning path updated';
$string['event_learnpath_updated_description'] = '['.__LINE__.']The learning path {$a} was updated';
$string['event_learnpath_created'] = '['.__LINE__.']Learning path created';
$string['event_learnpath_created_description'] = '['.__LINE__.']The learning path {$a} was created';
$string['event_completion_criteria_updated'] = '['.__LINE__.']Completion criteria updated';
$string['event_completion_criteria_updated_description'] = '['.__LINE__.']The completion criteria {$a} was updated';
$string['event_user_path_updated'] = '['.__LINE__.']User path relation was updated';
$string['event_user_path_updated_description'] = '['.__LINE__.']The user path path relation for user {$a->user} and learning path {$a->path} was updated';
$string['event_node_finished'] = '['.__LINE__.']Node weas finished';
$string['event_node_finished_description'] = '['.__LINE__.']The user {$a->user} has finished the node {$a->node}';
$string['event_attempt_submitted'] = '['.__LINE__.']Quiz attempt was finished';
$string['event_attempt_submitted_description'] = '['.__LINE__.']The user {$a->user} has finished the quiz {$a->node}';

// Color strings.
$string['DARK_GREEN'] = '#063449';
$string['DEEP_SKY_BLUE'] = '#0d5575';
$string['LIGHT_SEA_GREEN'] = '#4d8da8';
$string['LIGHT_STEEL_BLUE'] = '#87b8ce';
$string['DARK_RED'] = '#750033';
$string['CRIMSON'] = '#ad0050';
$string['DARK_ORANGE'] = '#df843b';
$string['RUSTY_RED'] = ']#c76413';
$string['PUMPKIN'] = '#e7a23b';
$string['LIGHT_GRAY'] = '#d1d1d1';
$string['GRAY'] = '#ababab';
$string['DIM_GRAY'] = '#737373';
$string['VERY_DARK_GRAY'] = ']#373737';
$string['BLACK'] = '#0c0c0c';

// Charthelper strings.
$string['charthelper_child_nodes'] = '['.__LINE__.']Child Nodes:';
$string['charthelper_no_child_nodes'] = '['.__LINE__.']No child nodes found.';
$string['charthelper_parent_nodes'] = '['.__LINE__.']Parent Nodes:';
$string['charthelper_no_parent_nodes'] = '['.__LINE__.']No parent nodes found.';
$string['charthelper_no_name'] = '['.__LINE__.']No name provided.';
$string['charthelper_no_description'] = '['.__LINE__.']No description provided.';
$string['charthelper_go_to_learningpath'] = '['.__LINE__.']Go to learning path editing.';

// Conditions strings.
$string['conditions_no_scales'] = '['.__LINE__.']No scales available';
$string['conditions_name'] = '['.__LINE__.']Subscale';
$string['conditions_parent_scale_name'] = '['.__LINE__.']Parent scale';
$string['conditions_scale_value'] = '['.__LINE__.']Scale value:';
$string['conditions_attempts'] = '['.__LINE__.']Correct answers in %:';
$string['conditions_set_values'] = '['.__LINE__.']Set Values';
$string['conditions_catquiz_warning_description'] = '['.__LINE__.']The Catquiz that is inside the same course as the Learning path';
$string['conditions_catquiz_warning_name'] = '['.__LINE__.']Catquiz inside course';
$string['conditions_min_grad'] = '['.__LINE__.']Min. Grade:';
$string['conditions_finish_course'] = '['.__LINE__.']Finish node manually';

// Completion strings.
$string['completion_invalid_path_title'] = '['.__LINE__.']Invalid Path';
$string['completion_invalid_path_text'] = '['.__LINE__.']Found standalone nodes. Every node must be connected to the path.';
$string['completion_invalid_condition_title'] = '['.__LINE__.']Invalid Conditions';
$string['completion_invalid_condition_text'] = '['.__LINE__.']Not all conditions have valid values. Please complete or delete these conditions.';
$string['completion_empty_global_value'] = '['.__LINE__.']Missing global scale';
$string['completion_empty_global_value_text'] = '['.__LINE__.']The global scale of some conditions were not set.';
$string['completion_go_back_learningpath'] = '['.__LINE__.']Go Back to Learningpath';
$string['completion_edit_completion'] = '['.__LINE__.']Edit Completion criteria of course node';
$string['completion_completion_for'] = '['.__LINE__.']Completion Criteria for:';
$string['completion_course_title'] = '['.__LINE__.']Node Title:';
$string['completion_course_tags'] = '['.__LINE__.']Tags:';
$string['completion_node_refused_title'] = '['.__LINE__.']Node drop refused';
$string['completion_node_refused_text'] = '['.__LINE__.']Please drop the node in the dropzones, which will be shown if you drag a node to an exsisting node.';
$string['completion_feedback_node'] = '['.__LINE__.']Feedback node';
$string['completion_description_feedback'] = '['.__LINE__.']Description';
$string['completion_dates_duration_feedback'] = '['.__LINE__.']Dates and Duration';
$string['completion_estimated_duration_feedback'] = '['.__LINE__.']Estimated Duration:';
$string['completion_start_date_feedback'] = '['.__LINE__.']Start Date:';
$string['completion_end_date_feedback'] = '['.__LINE__.']End Date:';
$string['completion_first_subscription_feedback'] = '['.__LINE__.']First subscribbed to node:';
$string['completion_restriction_feedback'] = '['.__LINE__.']Restriction';
$string['completion_nothing_defined_feedback'] = '['.__LINE__.']Nothing is defined';
$string['completion_completion_inbetween_feedback'] = '['.__LINE__.']Completion Inbetween';
$string['completion_completion_feedback'] = '['.__LINE__.']Completion';
$string['completion_loading_completion'] = '['.__LINE__.']Loading completion...';
$string['completion_drop_here'] = '['.__LINE__.']Drop to connect here';
$string['completion_drop_zone'] = '['.__LINE__.']Drop zone';
$string['completion_list_of_criteria'] = '['.__LINE__.']List of available ';
$string['completion_criteria'] = '['.__LINE__.'] criteria';
$string['completion_edge_or'] = '['.__LINE__.'] OR';
$string['completion_edge_and'] = '['.__LINE__.']AND';
$string['course_completion_choose_number'] = '['.__LINE__.']Choose a number of courses';
$string['course_completion_minimum_amount'] = '['.__LINE__.']Select the minimum amount of finished courses';

// Flowchart strings.
$string['flowchart_add_learning'] = '['.__LINE__.']Add a learning module';
$string['flowchart_title'] = '['.__LINE__.']Title:';
$string['flowchart_please_provide'] = '['.__LINE__.']Please provide a name!';
$string['flowchart_color'] = '['.__LINE__.']Color:';
$string['flowchart_cancel'] = '['.__LINE__.']Cancel:';
$string['flowchart_cancel_button'] = '['.__LINE__.']Discard changes';
$string['flowchart_add'] = '['.__LINE__.']Add';
$string['flowchart_existing_learning_modules'] = '['.__LINE__.']Existing learning modules';
$string['flowchart_provide_name'] = '['.__LINE__.']Please provide a name!';
$string['flowchart_save_button'] = '['.__LINE__.']Save';
$string['flowchart_delete_button'] = '['.__LINE__.']Delete';
$string['flowchart_save_notification_title'] = '['.__LINE__.']Saved failed';
$string['flowchart_save_notification_text_missing_strings'] = '['.__LINE__.']Provide a title and a short description for the learning path';
$string['flowchart_invalid_path_notification_title'] = '['.__LINE__.']Invalid Path';
$string['flowchart_save_notification_text'] = '['.__LINE__.']Found standalone nodes. Every node must be connected to the path. Submit to continue anyway.';
$string['flowchart_cancel_confirmation'] = '['.__LINE__.']All unsaved changes will be lost';
$string['flowchart_back_button'] = '['.__LINE__.']Continue edit';
$string['flowchart_course_already_inside_title'] = '['.__LINE__.']Course already inside';
$string['flowchart_course_already_inside_text'] = '['.__LINE__.']The course is already inside the node included';
$string['flowchart_drop_refused_title'] = '['.__LINE__.']Node drop refused';
$string['flowchart_drop_refused_text'] = '['.__LINE__.']Please drop the node in the dropzones, which will be shown if you drag a node to an exsisting node.';
$string['flowchart_delete_confirmation'] = '['.__LINE__.']Do you want to remove node ';
$string['flowchart_learning_package'] = '['.__LINE__.']Learning package';
$string['flowchart_courses'] = '['.__LINE__.']Courses';
$string['flowchart_hover_darg_drop'] = '['.__LINE__.']Drag and drop the course inside the drop zones to include it in the learning path.';
$string['flowchart_hover_click_here'] = '['.__LINE__.']Click here to go to course';

// Modals strings.
$string['modals_edit_feedback'] = '['.__LINE__.']Edit Feedback';
$string['modals_close'] = '['.__LINE__.']Close';
$string['modals_save_changes'] = '['.__LINE__.']Save Changes';
$string['modals_how_to_learningpath'] = '['.__LINE__.']How To Learning Path';
$string['modals_previous'] = '['.__LINE__.']Previous';
$string['modals_next'] = '['.__LINE__.']Next';
$string['modals_edit'] = '['.__LINE__.']Edit';
$string['modals_longname'] = '['.__LINE__.']Longname:';
$string['modals_description'] = '['.__LINE__.']Node description:';
$string['estimate_duration'] = '['.__LINE__.']Estimated duration ';
$string['modals_no_description'] = '['.__LINE__.']No node description given...';
$string['modals_shortname'] = '['.__LINE__.']Shortname:';

// Nodes strings.
$string['nodes_collection'] = '['.__LINE__.']Collection';
$string['nodes_edit'] = '['.__LINE__.']Edit';
$string['nodes_learning_module'] = '['.__LINE__.']Learning Module';
$string['nodes_select_module'] = '['.__LINE__.']Select a module';
$string['nodes_deselect_module'] = '['.__LINE__.']Deselect module';
$string['nodes_included_courses'] = '['.__LINE__.']Included Courses';
$string['nodes_edit_restriction'] = '['.__LINE__.']Edit restriction';
$string['nodes_edit_completion'] = '['.__LINE__.']Edit completion criteria';
$string['nodes_completion'] = '['.__LINE__.']Completion';
$string['nodes_restriction'] = '['.__LINE__.']Restriction';
$string['nodes_potential_start'] = '['.__LINE__.']Potential starting node';
$string['nodes_progress'] = '['.__LINE__.']Progress:';
$string['nodes_courses'] = '['.__LINE__.']Courses:';
$string['nodes_table_key'] = '['.__LINE__.']Key';
$string['nodes_table_checkmark'] = '['.__LINE__.']Checkmark';
$string['nodes_no_restriction_defined'] = '['.__LINE__.']No Restrictions are defined';
$string['nodes_no_completion_defined'] = '['.__LINE__.']No Completions are defined';
$string['nodes_hide_completion'] = '['.__LINE__.']Hide Completion';
$string['nodes_show_completion'] = '['.__LINE__.']Show Completion';
$string['nodes_feedback'] = '['.__LINE__.']Feedback';
$string['nodes_no_feedback'] = '['.__LINE__.']No feedback set...';
$string['nodes_warning_time_restriction'] = '['.__LINE__.']This restriction does not overwrite the general course accessebility times. Make sure your dates do not conflict with the course dates.';
$string['nodes_warning_time_heading'] = '['.__LINE__.']Warning!';
$string['nodes_no_description'] = '['.__LINE__.']No course description was provided';
$string['nodes_course_node'] = '['.__LINE__.']Course Node';
$string['nodes_feedback_restriction_before'] = '['.__LINE__.']To unlock node you have to:';
$string['nodes_feedback_completion_after'] = '['.__LINE__.']Node completed because:';
$string['nodes_feedback_completion_before'] = '['.__LINE__.']To complete node you have to:';
$string['nodes_feedback_completion_inbetween'] = '['.__LINE__.']Current node state:';
$string['nodes_feedback_completion_higher'] = '['.__LINE__.']With these, you can achieve a higher completion status:';
$string['nodes_feedback_before'] = '['.__LINE__.']Before';
$string['nodes_feedback_inbetween'] = '['.__LINE__.']Inbetween';
$string['nodes_feedback_after'] = '['.__LINE__.']After';
$string['nodes_feedback_use_default'] = '['.__LINE__.']Use default feedback';

// Nodes Items strings.
$string['nodes_items_start'] = '['.__LINE__.']Start:';
$string['nodes_items_end'] = '['.__LINE__.']End:';
$string['nodes_items_testname'] = '['.__LINE__.']Testname:';
$string['nodes_items_none'] = '['.__LINE__.']None';
$string['nodes_items_coursename'] = '['.__LINE__.']Coursename:';
$string['nodes_items_restrictions'] = '['.__LINE__.']Restrictions';
$string['nodes_items_no_conditions'] = '['.__LINE__.']No conditions are defined';
$string['nodes_items_restriction'] = '['.__LINE__.']Restriction';
$string['nodes_items_no_restrictions'] = '['.__LINE__.']No restrictions are defined';
$string['nodes_items_completion'] = '['.__LINE__.']Completion';
$string['nodes_items_no_progress'] = '['.__LINE__.']No Progress';

// Conditions strings.
$string['composables_new_node'] = '['.__LINE__.']New Starting node';
$string['composables_drop_zone_parent'] = '['.__LINE__.']Drop zone Parent';
$string['composables_drop_zone_child'] = '['.__LINE__.']Drop zone Child';
$string['composables_drop_zone_add'] = '['.__LINE__.']And drop zone';
$string['composables_drop_zone_or'] = '['.__LINE__.']Or drop zone';
$string['composables_feedback_node'] = '['.__LINE__.']Feedback node';

// Restriction strings.
$string['restriction_select_number'] = '['.__LINE__.']Select a Number:';
$string['restriction_no_select_number'] = '['.__LINE__.']No parent courses where found';
$string['restriction_select_course'] = '['.__LINE__.']Select a Node:';
$string['restriction_choose_number'] = '['.__LINE__.']Choose a number';
$string['restriction_parents_found'] = '['.__LINE__.']Found following parent nodes:';
$string['restriction_access_manually'] = '['.__LINE__.']Grant access to node manually';
$string['restriction_invalid_path_title'] = '['.__LINE__.']Invalid Path';
$string['restriction_invalid_path_text'] = '['.__LINE__.']Found standalone nodes. Every node must be connected to the path';
$string['restriction_go_back_learningpath'] = '['.__LINE__.']Go Back to Learningpath';
$string['restriction_edit_restrictions'] = '['.__LINE__.']Edit Restrictions to enter course node';
$string['restriction_restrictions_for'] = '['.__LINE__.']Restrictions for:';
$string['restriction_course_title'] = '['.__LINE__.']Node Title:';
$string['restriction_tags'] = '['.__LINE__.']Tags:';
$string['restriction_loading_restrictions'] = '['.__LINE__.']Loading restrictions...';
$string['restriction_node_drop_refused_title'] = '['.__LINE__.']Node drop refused';
$string['restriction_node_drop_refused_text'] = '['.__LINE__.']Please drop the node in the dropzones, which will be shown if you drag a node to an exsisting node.';

// User view strings.
$string['user_view_id'] = '['.__LINE__.']ID';
$string['user_view_username'] = '['.__LINE__.']Username';
$string['user_view_firstname'] = '['.__LINE__.']Firstname';
$string['user_view_lastname'] = '['.__LINE__.']Lastname';
$string['user_view_email'] = '['.__LINE__.']Email';
$string['user_view_progress'] = '['.__LINE__.']Progress';
$string['user_view_nodes'] = '['.__LINE__.']Finished Nodes';
$string['userlistranking'] = '['.__LINE__.']Ranking';
$string['user_view_go_back_overview'] = '['.__LINE__.']Go Back to Overview';
$string['user_view_user_path_for'] = '['.__LINE__.']User path for:';
$string['user_view_user_list'] = '['.__LINE__.']User List';
$string['user_view_user_list_show'] = '['.__LINE__.']Show ';
$string['user_view_user_list_hide'] = '['.__LINE__.']Hide ';

// Main strings.
$string['main_intro_slider'] = '['.__LINE__.']Introduction slider';
$string['main_description'] = '['.__LINE__.']Description:';
$string['main_duplicate'] = '['.__LINE__.']Duplicate';
$string['main_delete'] = '['.__LINE__.']Delete';

// Mobile strings.
$string['mobile_view_buttons_path'] = '['.__LINE__.']Learning path';
$string['mobile_view_buttons_list'] = '['.__LINE__.']Node list';
$string['mobile_view_list_header'] = '['.__LINE__.']Nodes List View';
$string['mobile_view_detail_id'] = '['.__LINE__.']ID:';
$string['mobile_view_detail_back'] = '['.__LINE__.']Back';
$string['mobile_view_detail_description'] = '['.__LINE__.']Description:';
$string['mobile_view_detail_estimate'] = '['.__LINE__.']Estimated duration:';
$string['mobile_view_detail_course_link'] = '['.__LINE__.']Open course';

// Privacy API.
$string['privacy:metadata:local_adele_learning_paths'] = '['.__LINE__.']The table stores information about learning paths created by users.';
$string['privacy:metadata:local_adele_learning_paths:createdby'] = '['.__LINE__.']The ID of the user who created the learning path.';
$string['privacy:metadata:local_adele_learning_paths:json'] = '['.__LINE__.']Additional information about the learning path stored in JSON format.';

$string['privacy:metadata:local_adele_path_user'] = '['.__LINE__.']The table stores the relationship between users and their assigned learning paths.';
$string['privacy:metadata:local_adele_path_user:user_id'] = '['.__LINE__.']The ID of the user assigned to the learning path.';
$string['privacy:metadata:local_adele_path_user:json'] = '['.__LINE__.']Additional information about the assignment stored in JSON format.';

$string['privacy:metadata:local_adele_lp_editors'] = '['.__LINE__.']The table stores information about users who are allowed to edit certain learning paths.';
$string['privacy:metadata:local_adele_lp_editors:userid'] = '['.__LINE__.']The ID of the user who is allowed to edit the learning path.';

$string['cachedef_navisteacher'] = '['.__LINE__.']Is teacher cache';
