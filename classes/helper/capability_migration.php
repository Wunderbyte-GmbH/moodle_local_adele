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

namespace local_adele\helper;

/**
 * Changes to role definitions that an upgrade has to make on existing sites,
 * because archetype defaults in db/access.php only reach fresh installs.
 *
 * Kept out of db/upgrade.php so that they can be tested.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capability_migration {
    /**
     * local/adele:teacheredit no longer belongs to the 'teacher' archetype -
     * the teacher WITHOUT editing rights (mod_adele #39): take the site-wide
     * grant from the existing roles of that archetype.
     *
     * Only what the old default put there is touched: the permission in the
     * system context. Overrides in a course or a category are somebody's
     * decision and stay.
     *
     * @return int[] The ids of the roles that lost the grant.
     */
    public static function remove_teacheredit_from_nonediting_teachers(): array {
        global $DB;
        $systemcontext = \context_system::instance();
        $changed = [];
        foreach (get_archetype_roles('teacher') as $role) {
            $granted = $DB->record_exists('role_capabilities', [
                'roleid' => $role->id,
                'contextid' => $systemcontext->id,
                'capability' => 'local/adele:teacheredit',
                'permission' => CAP_ALLOW,
            ]);
            if ($granted) {
                unassign_capability('local/adele:teacheredit', $role->id, $systemcontext->id);
                $changed[] = (int) $role->id;
            }
        }
        return $changed;
    }
}
