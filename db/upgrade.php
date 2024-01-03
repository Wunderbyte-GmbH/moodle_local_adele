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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/upgradelib.php');

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
    if ($oldversion < 2023010303) {

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
        upgrade_plugin_savepoint(true, 2023010303, 'local', 'adele');
    }

    return true;
}
