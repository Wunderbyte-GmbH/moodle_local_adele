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
 * Helper functions for user path relation.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\helper;

/**
 * The learnpath created event class.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_names {
    /**
     * Get learning paths that contain course.
     *
     * @return string
     *
     */
    public function get_role_names() {
        global $DB;
        $roleshortnames = ['editingteacher', 'teacher'];
        list($insql, $params) = $DB->get_in_or_equal($roleshortnames, SQL_PARAMS_QM, 'param', true);
        $sql = "SELECT shortname, name
                FROM {role}
                WHERE shortname {$insql}";

        $roles = $DB->get_records_sql($sql, $params);

        return self::get_role_string($roles);
    }

    /**
     * Get learning paths that contain course.
     * @param object $roles
     * @return string
     *
     */
    private function get_role_string($roles) {
        $rolenames = [];
        foreach ($roles as $role) {
            $rolenames[] = !empty($role->name) ? $role->name : $role->shortname;
        }
        return implode(' and ', $rolenames);
    }

}
