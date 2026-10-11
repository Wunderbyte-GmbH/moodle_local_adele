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
                    'timemodified' => \core\di::get(\core\clock::class)->time(),
                    'modifierid' => 2,
                ]);
            }
        }

        // Update savepoint to mark the successful upgrade.
        upgrade_plugin_savepoint(true, 2025081200, 'local', 'adele');
    }
    if ($oldversion < 2026061800) {
        // The local/adele:teacheredit capability gained the editingteacher
        // archetype so editing-teachers can operate master conditions without the
        // over-broad local/adele:canmanage. Archetype defaults only apply to fresh
        // installs, so grant it to existing editing-teacher roles here too. Do not
        // overwrite any explicit admin override (overwrite = false).
        $systemcontext = \context_system::instance();
        foreach (get_archetype_roles('editingteacher') as $role) {
            assign_capability('local/adele:teacheredit', CAP_ALLOW, $role->id, $systemcontext->id, false);
        }

        // Adele savepoint reached.
        upgrade_plugin_savepoint(true, 2026061800, 'local', 'adele');
    }

    if ($oldversion < 2026071500) {
        // The local/adele:teacheredit capability gained the manager
        // archetype so a course Manager operates a learning path like an editing
        // teacher. Archetype defaults only apply to fresh installs, so grant it to
        // existing manager roles here too. Do not overwrite any explicit admin
        // override (overwrite = false).
        $systemcontext = \context_system::instance();
        foreach (get_archetype_roles('manager') as $role) {
            assign_capability('local/adele:teacheredit', CAP_ALLOW, $role->id, $systemcontext->id, false);
        }

        // Adele savepoint reached.
        upgrade_plugin_savepoint(true, 2026071500, 'local', 'adele');
    }
    if ($oldversion < 2026072200) {
        // The course_completed observer now resolves the affected student via
        // relateduserid instead of the acting user (userid). Courses that were
        // already completed before this fix do not re-fire course_completed, so
        // their learning paths can still carry a stale node status. Queue a
        // one-off reconciliation that recomputes every active learning path.
        $reconcile = new \local_adele\task\reconcile_user_paths();
        \core\task\manager::queue_adhoc_task($reconcile, true);

        // Guarantee at most one active user-path relation per
        // (user_id, course_id, learning_path_id). First remove pre-existing
        // duplicates created by the historical check-then-insert race, keeping
        // the most recently created row (highest id). buildsqlqueryuserpath()
        // reads with ORDER BY id DESC, so the highest-id row is the one every
        // read/update path already targets and therefore carries the up-to-date
        // progress; the orphaned lower-id copies were never updated after
        // creation, so nothing is lost.
        //
        // A single DELETE that references {local_adele_path_user} both as the
        // target and inside its own WHERE clause triggers MySQL/MariaDB error
        // 1093 ("You can't specify target table ... for update in FROM
        // clause"); derived-table wrapping is not a reliable workaround. This
        // is therefore split into a read-only SELECT (same-table self-joins
        // are fine there) followed by a plain DELETE by explicit id list,
        // which every supported RDBMS accepts unconditionally.
        //
        // course_id is added by step 2024052300 above, so in a normal
        // sequential upgrade it is guaranteed to exist by the time we get
        // here; the defensive check below covers a prior
        // interrupted/partial upgrade on that installation left its
        // savepoint recorded without the corresponding DDL having actually
        // stuck. Rather than assume that can't happen again, guarantee the
        // column exists immediately before relying on it, exactly like step
        // 2024052300 already does for the same field — cheap, idempotent,
        // and self-healing regardless of how the site got here.
        $table = new xmldb_table('local_adele_path_user');
        $coursefield = new xmldb_field('course_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'user_id');
        if (!$dbman->field_exists($table, $coursefield)) {
            $dbman->add_field($table, $coursefield);
        }

        $duplicateids = $DB->get_fieldset_sql("
            SELECT t1.id
            FROM {local_adele_path_user} t1
            WHERE EXISTS (
                SELECT 1
                FROM {local_adele_path_user} t2
                WHERE t2.user_id = t1.user_id
                  AND t2.course_id = t1.course_id
                  AND t2.learning_path_id = t1.learning_path_id
                  AND t2.id > t1.id
            )
        ");
        if ($duplicateids) {
            $DB->delete_records_list('local_adele_path_user', 'id', $duplicateids);
        }

        // With the duplicates removed, the unique index can be created.
        $table = new xmldb_table('local_adele_path_user');
        $index = new xmldb_index(
            'useridcourseidlpid',
            XMLDB_INDEX_UNIQUE,
            ['user_id', 'course_id', 'learning_path_id']
        );
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026072200, 'local', 'adele');
    }

    if ($oldversion < 2026072301) {
        // A user path's identity is learning path + user; course_id is
        // provenance only. The 2026072200 step above still enforced the old,
        // course-bound identity; without this step a learning path embedded
        // in two different host courses would keep creating a second,
        // competing user path the moment both are active - the duplication
        // (referenced in classes/enrollment.php) originally complained
        // about — the two fixes were solving adjacent but different problems
        // and the schema has to converge on one of them.
        //
        // Collapse remaining course_id-differentiated duplicates per
        // (user_id, learning_path_id), keeping the highest id, mirroring the
        // 2026072200 approach and the same self-join-then-delete-by-id shape
        // (never a same-table subquery inside the DELETE itself).
        $table = new xmldb_table('local_adele_path_user');
        $oldindex = new xmldb_index(
            'useridcourseidlpid',
            XMLDB_INDEX_UNIQUE,
            ['user_id', 'course_id', 'learning_path_id']
        );
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        $duplicateids = $DB->get_fieldset_sql("
            SELECT t1.id
            FROM {local_adele_path_user} t1
            WHERE EXISTS (
                SELECT 1
                FROM {local_adele_path_user} t2
                WHERE t2.user_id = t1.user_id
                  AND t2.learning_path_id = t1.learning_path_id
                  AND t2.id > t1.id
            )
        ");
        if ($duplicateids) {
            $DB->delete_records_list('local_adele_path_user', 'id', $duplicateids);
        }

        $newindex = new xmldb_index(
            'useridlpid',
            XMLDB_INDEX_UNIQUE,
            ['user_id', 'learning_path_id']
        );
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026072301, 'local', 'adele');
    }

    // The local_adele_lp_editors table had no unique index and both columns were
    // nullable, so duplicate (userid, learningpathid) rows were possible —
    // the surrounding code already contained
    // workarounds for exactly this. No other column on this table carries
    // meaningful data (id, userid, learningpathid only), so keeping the
    // lowest id per pair and discarding the rest loses nothing, unlike the
    // similar cleanup above for local_adele_path_user.
    if ($oldversion < 2026072402) {
        $table = new xmldb_table('local_adele_lp_editors');

        $duplicateids = $DB->get_fieldset_sql("
            SELECT t1.id
            FROM {local_adele_lp_editors} t1
            WHERE EXISTS (
                SELECT 1
                FROM {local_adele_lp_editors} t2
                WHERE t2.userid = t1.userid
                  AND t2.learningpathid = t1.learningpathid
                  AND t2.id > t1.id
            )
        ");
        if ($duplicateids) {
            $DB->delete_records_list('local_adele_lp_editors', 'id', $duplicateids);
        }

        // A row with a NULL userid or learningpathid cannot mean anything
        // (can't identify who edits what) and would make change_field_
        // notnull() below fail on any installation that has one — remove
        // them first rather than risk repeating the real production
        // upgrade failure on installations that have such rows.
        $DB->delete_records_select(
            'local_adele_lp_editors',
            'userid IS NULL OR learningpathid IS NULL'
        );

        $userid = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $userid)) {
            $dbman->change_field_notnull($table, $userid);
        }
        $learningpathid = new xmldb_field('learningpathid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $learningpathid)) {
            $dbman->change_field_notnull($table, $learningpathid);
        }

        $newindex = new xmldb_index(
            'useridlearningpathid',
            XMLDB_INDEX_UNIQUE,
            ['userid', 'learningpathid']
        );
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026072402, 'local', 'adele');
    }

    // Create the new host-course index table and backfill it from
    // mod_adele's existing embeddings, so upgrading sites do not start with
    // an empty index that only catches up as activities are next saved.
    if ($oldversion < 2026072403) {
        $table = new xmldb_table('local_adele_host_courses');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('adeleinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('learningpathid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('participantoption1', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('participantoption2', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('participantoption3', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('adeleinstanceid', XMLDB_INDEX_UNIQUE, ['adeleinstanceid']);
            $table->add_index('learningpathid', XMLDB_INDEX_NOTUNIQUE, ['learningpathid']);
            $dbman->create_table($table);
        }

        // Backfill: only possible if mod_adele is already installed (its
        // {adele} table must exist) - on a site installing local_adele
        // before mod_adele for the first time, this is legitimately empty
        // and mod_adele's own install/first save will populate it going
        // forward via the new sync calls in its lifecycle hooks.
        if ($dbman->table_exists('adele')) {
            $embeddings = $DB->get_records('adele', null, '', 'id, learningpathid, course, participantslist');
            foreach ($embeddings as $embedding) {
                if (empty($embedding->learningpathid)) {
                    continue;
                }
                $options = array_map('trim', explode(',', (string) $embedding->participantslist));
                $DB->insert_record('local_adele_host_courses', (object) [
                    'adeleinstanceid' => (int) $embedding->id,
                    'learningpathid' => (int) $embedding->learningpathid,
                    'courseid' => (int) $embedding->course,
                    'participantoption1' => in_array('1', $options, true) ? 1 : 0,
                    'participantoption2' => in_array('2', $options, true) ? 1 : 0,
                    'participantoption3' => in_array('3', $options, true) ? 1 : 0,
                    'timemodified' => \core\di::get(\core\clock::class)->time(),
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2026072403, 'local', 'adele');
    }

    // Drop the host-course index again. It was introduced in 2026072403 so
    // that enrol_adele would not have to read mod_adele's {adele} table, but
    // it could not carry hostenrolmentmode, which the host-course sweep
    // needs. The derivation now lives in mod_adele\local\host_policy and
    // reads {adele} directly, so this table holds nothing that is not
    // already there - and a mirror that nothing reads is a mirror that
    // silently goes stale. No data is lost: every column was a copy.
    if ($oldversion < 2026082800) {
        $table = new xmldb_table('local_adele_host_courses');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082800, 'local', 'adele');
    }

    // Timed access conditions store their window as Unix timestamps instead
    // of wall-clock strings (#581). The strings had no time zone and were
    // compared with the seconds taken from the current time. Each legacy
    // value is converted in the site's time zone - the zone the evaluation
    // used for it until now - so every existing window keeps its meaning.
    // Both the learning paths and the per-user copies carry the conditions.
    // Idempotent: values that already are timestamps are left alone.
    if ($oldversion < 2026100502) {
        foreach (['local_adele_learning_paths', 'local_adele_path_user'] as $tablename) {
            $records = $DB->get_recordset($tablename, null, 'id', 'id, json');
            foreach ($records as $record) {
                $decoded = json_decode((string) $record->json, true);
                if (!is_array($decoded) || !isset($decoded['tree']) || !is_array($decoded['tree'])) {
                    continue;
                }
                if (\local_adele\helper\time_value::migrate_tree($decoded['tree']) > 0) {
                    $DB->set_field($tablename, 'json', json_encode($decoded), ['id' => $record->id]);
                }
            }
            $records->close();
        }
        upgrade_plugin_savepoint(true, 2026100502, 'local', 'adele');
    }

    // Seeing a learner's path and changing it are two rights now (mod_adele
    // #39). local/adele:viewparticipants is new; Moodle assigns it by
    // archetype (teacher, editingteacher, manager) when it registers the
    // capability, after this step. local/adele:teacheredit lost the 'teacher'
    // archetype - the teacher WITHOUT editing rights. Archetype defaults only
    // apply to fresh installs, so the grant is taken from the existing roles
    // of that archetype here; course-level overrides are left to the people
    // who set them.
    if ($oldversion < 2026100707) {
        \local_adele\helper\capability_migration::remove_teacheredit_from_nonediting_teachers();
        upgrade_plugin_savepoint(true, 2026100707, 'local', 'adele');
    }

    return true;
}
