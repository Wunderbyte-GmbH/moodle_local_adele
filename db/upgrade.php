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

    if ($oldversion < 2024010304) {
        $table = new xmldb_table('local_adele_path_user');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('learning_path_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('json', XMLDB_TYPE_TEXT, null, null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user_id', XMLDB_KEY_FOREIGN, ['user_id'], 'user', ['id']);
        $table->add_key('learning_path_id', XMLDB_KEY_FOREIGN, ['learning_path_id'], 'local_adele_learning_paths', ['id']);
        $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2024010304, 'local', 'adele');
    }

    if ($oldversion < 2024052300) {
        $table = new xmldb_table('local_adele_path_user');
        $field = new xmldb_field('course_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'user_id');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024052300, 'local', 'adele');
    }

    if ($oldversion < 2024060304) {
        $table = new xmldb_table('local_adele_learning_paths');
        $field = new xmldb_field('image', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'json');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024060304, 'local', 'adele');
    }

    if ($oldversion < 2024080901) {
        $table = new xmldb_table('local_adele_path_user');
        $field = new xmldb_field('last_seen_by_owner', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024080901, 'local', 'adele');
    }

    if ($oldversion < 2024081201) {
        $table = new xmldb_table('local_adele_lp_editors');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('learningpathid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2024081201, 'local', 'adele');
    }

    if ($oldversion < 2024082905) {
        $table = new xmldb_table('local_adele_learning_paths');
        $field = new xmldb_field('visibility', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024082905, 'local', 'adele');
    }

    if ($oldversion < 2025081200) {
        $name = 'Adele Assistant';
        $shortname = 'adeleassistant';
        $descriptionstr = 'adeleassistantdescription';
        $capabilities = ['local/adele:assist'];

        $role = $DB->get_record('role', ['shortname' => $shortname]);
        if (empty($role->id)) {
            $sql = "SELECT MAX(sortorder)+1 AS id FROM {role}";
            $max = $DB->get_record_sql($sql, []);

            $role = (object) [
                'name' => $name,
                'shortname' => $shortname,
                'description' => 'Adele assistant',
                'sortorder' => $max->id,
                'archetype' => '',
            ];
            $role->id = $DB->insert_record('role', $role);
        }

        $chk = $DB->get_record('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_SYSTEM]);
        if (empty($chk->id)) {
            $DB->insert_record('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_SYSTEM]);
        }

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

        upgrade_plugin_savepoint(true, 2025081200, 'local', 'adele');
    }

    if ($oldversion < 2026031600) {
        $table = new xmldb_table('local_adele_path_user');

        // =====================================================================
        // Step 1: Robustly merge all duplicate active records per
        //         (learning_path_id, user_id). We use a raw SQL subquery to
        //         find the ID to keep (the one with the highest timemodified,
        //         tie-broken by highest id), then archive everything else.
        // =====================================================================

        // First, find all (learning_path_id, user_id) combinations that have
        // more than one active record. We use COUNT and select the combo as a
        // concatenated string to avoid get_records_sql key-collision issues.
        $sql = "SELECT CONCAT(learning_path_id, '-', user_id) AS combo,
                       learning_path_id, user_id, COUNT(*) AS cnt
                FROM {local_adele_path_user}
                WHERE status = 'active'
                GROUP BY learning_path_id, user_id
                HAVING COUNT(*) > 1";
        $duplicates = $DB->get_records_sql($sql);

        foreach ($duplicates as $dup) {
            // For each duplicate combo, find ALL active records ordered by
            // timemodified DESC, id DESC. The first one is the keeper.
            $records = $DB->get_records_sql(
                "SELECT id
                 FROM {local_adele_path_user}
                 WHERE learning_path_id = :learningpathid
                   AND user_id = :userid
                   AND status = 'active'
                 ORDER BY timemodified DESC, id DESC",
                [
                    'learningpathid' => $dup->learning_path_id,
                    'userid' => $dup->user_id,
                ]
            );

            $first = true;
            foreach ($records as $record) {
                if ($first) {
                    // Keep this one (most recent).
                    $first = false;
                    continue;
                }
                // Archive all others.
                $DB->set_field('local_adele_path_user', 'status',
                    'archived_migration', ['id' => $record->id]);
            }
        }

        // =====================================================================
        // Step 2: Safety check – verify no duplicates remain before creating
        //         the unique index. This handles edge cases the above might
        //         have missed (e.g. records with status other than 'active'
        //         that still collide on the new unique key).
        // =====================================================================
        $sql = "SELECT CONCAT(learning_path_id, '-', user_id, '-', status) AS combo,
                       learning_path_id, user_id, status, COUNT(*) AS cnt
                FROM {local_adele_path_user}
                GROUP BY learning_path_id, user_id, status
                HAVING COUNT(*) > 1";
        $remainingdupes = $DB->get_records_sql($sql);

        foreach ($remainingdupes as $dup) {
            $records = $DB->get_records_sql(
                "SELECT id
                 FROM {local_adele_path_user}
                 WHERE learning_path_id = :learningpathid
                   AND user_id = :userid
                   AND status = :status
                 ORDER BY timemodified DESC, id DESC",
                [
                    'learningpathid' => $dup->learning_path_id,
                    'userid' => $dup->user_id,
                    'status' => $dup->status,
                ]
            );

            $first = true;
            foreach ($records as $record) {
                if ($first) {
                    $first = false;
                    continue;
                }
                // For active records, archive. For non-active, delete.
                if ($dup->status === 'active') {
                    $DB->set_field('local_adele_path_user', 'status',
                        'archived_migration', ['id' => $record->id]);
                } else {
                    $DB->delete_records('local_adele_path_user', ['id' => $record->id]);
                }
            }
        }

        // =====================================================================
        // Step 3: Drop any existing index that includes course_id.
        // =====================================================================
        // Try various possible index names that might exist.
        $possibleindexes = [
            new xmldb_index('leacouuse_uix', XMLDB_INDEX_UNIQUE,
                ['learning_path_id', 'course_id', 'user_id', 'status']),
            new xmldb_index('mdl_locaadelpathuser_leacouu_uix', XMLDB_INDEX_UNIQUE,
                ['learning_path_id', 'course_id', 'user_id', 'status']),
        ];
        foreach ($possibleindexes as $oldindex) {
            if ($dbman->index_exists($table, $oldindex)) {
                $dbman->drop_index($table, $oldindex);
            }
        }

        // =====================================================================
        // Step 4: Drop the course_id column.
        // =====================================================================
        $field = new xmldb_field('course_id');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // =====================================================================
        // Step 5: Add new unique index on (learning_path_id, user_id, status).
        // =====================================================================
        $newindex = new xmldb_index('leause_uix', XMLDB_INDEX_UNIQUE,
            ['learning_path_id', 'user_id', 'status']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026031600, 'local', 'adele');
    }

    return true;
}
