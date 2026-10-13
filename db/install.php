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
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * Uses the Moodle role APIs — create_role(), set_role_contextlevels() and
 * assign_capability() — rather than writing directly into the {role},
 * {role_context_levels} and {role_capabilities} core tables, so sortorder,
 * modifierid and cache invalidation are handled correctly. Both roles
 * previously got the same literal "Adele assistant" description regardless
 * of which one was being created.
 *
 * Regression fix (confirmed against a real Moodle 4.5.12 instance, same
 * session): xmldb_local_adele_install() runs BEFORE Moodle applies this
 * plugin's own db/access.php into {capabilities} — assign_capability()
 * validates the capability exists there first and throws a coding_exception
 * if it does not, which a fresh install always hits for this plugin's own
 * capabilities. The original raw {role_capabilities} insert this code used
 * to have deliberately worked around exactly this ordering gotcha by
 * skipping that validation. create_role()/set_role_contextlevels() have no
 * such dependency and stay on the proper API; capability assignment now
 * checks whether the capability is registered yet and only uses
 * assign_capability() when it is (its validation, cache invalidation and
 * event trigger are still preferable in that case, e.g. a later upgrade
 * where the capability already exists) — otherwise it falls back to the
 * same direct insert as before, which does not require the capability
 * definition to exist first.
 *
 * @package     local_adele
 * @category    upgrade
 * @copyright   2023 Georg Maißer Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom code to be run on installing the plugin.
 */
function xmldb_local_adele_install() {
    // Create or update the Adele Manager role.
    create_role_for_adele('Adele Manager', 'adelemanager', 'adeleroledescription', ['local/adele:canmanage']);

    // Create or update the new Adele Assistant role.
    create_role_for_adele('Adele Assistant', 'adeleassistant', 'adeleassistantdescription', ['local/adele:assist']);

    return true;
}

/**
 * Helper function to create a role if it does not exist.
 *
 * @param string $name Role name.
 * @param string $shortname Role shortname.
 * @param string $descriptionstr Identifier for the description string.
 * @param array $capabilities List of capabilities for the role.
 */
function create_role_for_adele($name, $shortname, $descriptionstr, $capabilities) {
    global $DB;

    $description = get_string($descriptionstr, 'local_adele');

    $role = $DB->get_record('role', ['shortname' => $shortname]);
    if (empty($role->id)) {
        $roleid = create_role($name, $shortname, $description, '');
    } else {
        $roleid = $role->id;
        // The description may have been wrong on a previous install of this
        // plugin (both roles used to share the literal string "Adele
        // assistant") — correct it going forward without touching a name
        // an administrator may have customised since.
        if ($role->description !== $description) {
            $DB->set_field('role', 'description', $description, ['id' => $roleid]);
        }
    }

    // Ensure this role is assigned at the required context level. Additive
    // with respect to any OTHER context level an administrator may already
    // have configured for this role, since set_role_contextlevels() replaces
    // the full set — read the current set first and merge.
    $current = get_role_contextlevels($roleid);
    if (!in_array(CONTEXT_SYSTEM, $current, true)) {
        $current[] = CONTEXT_SYSTEM;
        set_role_contextlevels($roleid, $current);
    }

    // Ensure this role has the required capabilities. On a fresh install,
    // {capabilities} does not contain THIS plugin's own capabilities yet
    // (Moodle applies db/access.php after xmldb_local_adele_install() runs)
    // — assign_capability() would throw. Fall back to the direct insert in
    // that case; once Moodle registers the capability right after this
    // function returns, the row already correctly reflects the permission.
    $ctx = \context_system::instance();
    foreach ($capabilities as $cap) {
        if ($DB->record_exists('capabilities', ['name' => $cap])) {
            assign_capability($cap, CAP_ALLOW, $roleid, $ctx->id);
            continue;
        }
        $exists = $DB->record_exists('role_capabilities', [
            'contextid' => $ctx->id,
            'roleid' => $roleid,
            'capability' => $cap,
            'permission' => CAP_ALLOW,
        ]);
        if (!$exists) {
            $DB->insert_record('role_capabilities', [
                'contextid' => $ctx->id,
                'roleid' => $roleid,
                'capability' => $cap,
                'permission' => CAP_ALLOW,
                'timemodified' => \core\di::get(\core\clock::class)->time(),
                'modifierid' => 0,
            ]);
        }
    }
}
