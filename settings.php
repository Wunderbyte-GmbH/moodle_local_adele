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
 * Plugin administration pages are defined here.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_adele\admin_setting_course_tags;
use local_adele\helper\role_names;
use local_adele\course_restriction\course_restriction_info;
defined('MOODLE_INTERNAL') || die();

$componentname = 'local_adele';

// Default for users that have site config.
if ($hassiteconfig) {
    // Add the category to the local plugin branch.
    $settings = new admin_settingpage('local_adele_settings', '');
    $ADMIN->add('localplugins', new admin_category($componentname, get_string('pluginname', $componentname)));
    $ADMIN->add($componentname, $settings);

    $rolenamesclass = new role_names();
    $rolenames = $rolenamesclass->get_role_names();

    // Select options.
    $settings->add(
        new admin_setting_configselect($componentname . '/selectconfig',
                get_string('activefilter', $componentname),
                get_string('activefilter_desc', $componentname),
                'only_subscribed',
                [
                    'only_subscribed' => get_string('settings_only_subscribed', $componentname, $rolenames),
                    'all_courses' => get_string('settings_all_courses', $componentname),
                ]));

    // Included tags.
    $settings->add(
            new admin_setting_course_tags(
                    $componentname . '/includetags',
                    get_string('tagsinclude', $componentname),
                    get_string('tagsinclude_desc', $componentname),
                    '',
                    PARAM_TEXT
            )
    );

    // Excluded tags.
    $settings->add(
        new admin_setting_course_tags(
                $componentname . '/excludetags',
                get_string('tagsexclude', $componentname),
                get_string('tagsexclude_desc', $componentname),
                '',
                PARAM_TEXT
        )
    );

    // Category level.
    $categories = core_course_category::make_categories_list();
    $settings->add(new admin_setting_configmultiselect(
                $componentname . '/catfilter',
                get_string('categories', $componentname),
                get_string('categories_desc', $componentname),
                [],
                $categories)
    );

    // Restrict restrictions.
    $restrictions = course_restriction_info::get_restrictions();
    $matchedrestrictions = [];
    foreach ($restrictions as $key => $value) {
        $matchedrestrictions[$value['label']] = $value['name'];
    }
    $restnames = array_map(function($item) {
        return $item['name'];
    }, $restrictions);
    $settings->add(new admin_setting_configmultiselect(
                $componentname . '/restrictionfilter',
                get_string('nodes_restriction', $componentname),
                get_string('nodes_edit_restriction', $componentname),
                [],
                $matchedrestrictions)
    );

    // Alise quiz settings.
    $settings->add(
        new admin_setting_configselect(
                $componentname . '/quizsettings',
                get_string('quiz_settings', $componentname),
                get_string('quiz_settings_desc', $componentname),
                'all_quiz_global',
                [
                    'single_quiz' => get_string('single_quiz', $componentname),
                    'all_quiz_global' => get_string('all_quiz_global', $componentname),
                    'all_quiz' => get_string('all_quiz', $componentname),
                ]));
}
