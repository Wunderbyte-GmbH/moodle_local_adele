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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_adele
 * @category    upgrade
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_adele upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_adele_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.
    if ($oldversion < 2024010304) {

        // Define table local_adele_path_user to be created.
        $table = new xmldb_table('local_adele_path_user');

        // Adding fields to table local_adele_path_user.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('learning_path_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('json', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table local_adele_path_user.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user_id', XMLDB_KEY_FOREIGN, ['user_id'], 'user', ['id']);
        $table->add_key('learning_path_id', XMLDB_KEY_FOREIGN, ['learning_path_id'], 'local_adele_learning_paths', ['id']);
        $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);

        // Conditionally launch create table for local_adele_path_user.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adele savepoint reached.
        upgrade_plugin_savepoint(true, 2024010304, 'local', 'adele');
    }

    if ($oldversion < 2024052300) {

        // Define field course_id to be added to local_adele_path_user.
        $table = new xmldb_table('local_adele_path_user');
        $field = new xmldb_field('course_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'user_id');

        // Conditionally launch add field course_id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Adele savepoint reached.
        upgrade_plugin_savepoint(true, 2024052300, 'local', 'adele');
    }

    if ($oldversion < 2024060304) {

        // Define field course_id to be added to local_adele_path_user.
        $table = new xmldb_table('local_adele_learning_paths');
        $field = new xmldb_field('image', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'json');

        // Conditionally launch add field image.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Adele savepoint reached.
        upgrade_plugin_savepoint(true, 2024060304, 'local', 'adele');
    }
    if ($oldversion < 2024080901) {

        // Define field course_id to be added to local_adele_path_user.
        $table = new xmldb_table('local_adele_path_user');
        $field = new xmldb_field('last_seen_by_owner', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Conditionally launch add field image.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Adele savepoint reached.
        upgrade_plugin_savepoint(true, 2024080901, 'local', 'adele');
    }
    if ($oldversion < 2024081201) {
        // Define table local_adele_lp_editors to be created.
        $table = new xmldb_table('local_adele_lp_editors');

        // Adding fields to table local_adele_lp_editors.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('learningpathid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table local_adele_lp_editors.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_adele_lp_editors.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adele savepoint reached.
        upgrade_plugin_savepoint(true, 2024081201, 'local', 'adele');
    }
    if ($oldversion < 2024082905) {
        // Define table local_adele_lp_editors to be created.
        $table = new xmldb_table('local_adele_learning_paths');
        $field = new xmldb_field('visibility', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Conditionally launch add field image.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Adele savepoint reached.
        upgrade_plugin_savepoint(true, 2024082905, 'local', 'adele');
    }
       // Example upgrade step.
    if ($oldversion < 2023101200) { // Adjust this version number appropriately.
        // Perform database schema changes, add new capabilities, etc.

        // Update the plugin's savepoint version.
        upgrade_plugin_savepoint(true, 2023101200, 'local', 'adele');
    }
    if ($oldversion < 2025081200) {
        // Define the new "Adele Assistant" role properties.
        $name = 'Adele Assistant';
        $shortname = 'adeleassistant';
        $descriptionstr = 'adeleassistantdescription';
        $capabilities = ['local/adele:assist'];

        // Check if the role exists by its shortname.
        $role = $DB->get_record('role', ['shortname' => $shortname]);
        if (empty($role->id)) {
            // Get the new sort order.
            $sql = "SELECT MAX(sortorder)+1 AS id FROM {role}";
            $max = $DB->get_record_sql($sql, []);

            // Create the new role record.
            $role = (object) [
                'name' => $name,
                'shortname' => $shortname,
                'description' => 'Adele assistant',
                'sortorder' => $max->id,
                'archetype' => '',
            ];
            // Insert the new role into the database.
            $role->id = $DB->insert_record('role', $role);
        }

        // Ensure this role is assigned at the required context level.
        $chk = $DB->get_record('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_SYSTEM]);
        if (empty($chk->id)) {
            $DB->insert_record('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_SYSTEM]);
        }

        // Ensure this role has the required capabilities.
        $ctx = \context_system::instance();
        foreach ($capabilities as $cap) {
            $chk = $DB->get_record('role_capabilities', [
                    'contextid' => $ctx->id,
                    'roleid' => $role->id,
                    'capability' => $cap,
                    'permission' => 1,
                ]);
            if (empty($chk->id)) {
                $DB->insert_record('role_capabilities', [
                    'contextid' => $ctx->id,
                    'roleid' => $role->id,
                    'capability' => $cap,
                    'permission' => 1,
                    'timemodified' => time(),
                    'modifierid' => 2,
                ]);
            }
        }

        // Update savepoint to mark the successful upgrade.
        upgrade_plugin_savepoint(true, 2025081200, 'local', 'adele');
    }
    


    return true;
}
