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
 * This class contains a list of webservice functions related to the adele Module by Wunderbyte.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_adele;

use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local adele.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users {
    /**
     * Function to lazyload userlist for autocomplete.
     *
     * @param string $query
     * @return array
     */
    public static function load_users(string $query) {
        global $DB;

        $values = explode(' ', $query);

        $fullsql = $DB->sql_concat(
            '\' \'',
            'u.id',
            '\' \'',
            'u.firstname',
            '\' \'',
            'u.lastname',
            '\' \'',
            'u.email',
            '\' \''
        );

        $sql = "SELECT * FROM (
                    SELECT u.id, u.firstname, u.lastname, u.email, $fullsql AS fulltextstring
                    FROM {user} u
                    WHERE u.deleted = 0
                ) AS fulltexttable";
        // Check for u.deleted = 0 is important, so we do not load any deleted users!
        $params = [];
        if (!empty($query)) {
            // We search for every word extra to get better results.
            $firstrun = true;
            $counter = 1;
            foreach ($values as $value) {
                $sql .= $firstrun ? ' WHERE ' : ' AND ';
                $sql .= " " . $DB->sql_like('fulltextstring', ':param' . $counter, false) . " ";
                // If it's numeric, we search for the full number - so we need to add blanks.
                $params['param' . $counter] = is_numeric($value) ? "% $value %" : "%$value%";
                $firstrun = false;
                $counter++;
            }
        }

        // We don't return more than 100 records, so we don't need to fetch more from db.
        $sql .= " limit 102";

        $rs = $DB->get_recordset_sql($sql, $params);
        $count = 0;
        $list = [];

        foreach ($rs as $record) {
            $user = (object)[
                    'id' => $record->id,
                    'firstname' => $record->firstname,
                    'lastname' => $record->lastname,
                    'email' => $record->email,
            ];

            $count++;
            $list[$record->id] = $user;
        }

        $rs->close();

        return [
                'warnings' => count($list) > 100 ? get_string('toomanyuserstoshow', 'core', '> 100') : '',
                'list' => count($list) > 100 ? [] : $list,
        ];
    }
}
