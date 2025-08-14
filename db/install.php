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
 * @package     local_adele
 * @category    upgrade
 * @copyright   2023 Georg Maißer Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom code to be run on installing the plugin.
 */
function xmldb_local_adele_install() {
    global $DB;

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
}
