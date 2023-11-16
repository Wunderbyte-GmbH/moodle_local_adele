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

namespace local_adele;

use admin_setting_configtextarea;

/**
 * Validate if the string does excist.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_course_tags extends admin_setting_configtextarea {
    /**
     * Validate the contents of the tags
     *
     * @param string $data A list of categories separated by new lines
     * @return mixed bool true for success or string:error on failure
     */
    public function validate($data) {
        $notfoundtags = '';
        if ($data == '') {
            return true;
        }
        // Get all Tags.
        global $DB;
        $tagsdb = $DB->get_records('tag', null, '', 'name');
        $tagsarray = [];
        foreach ($tagsdb as $tagdb) {
            $tagsarray[] = $tagdb->name;
        }
        $usedtags = explode(',', str_replace(' ', '', $data));

        foreach ($usedtags as $usedtag) {
            if (!in_array($usedtag, $tagsarray)) {
                $notfoundtags .= $usedtag . ',';
            }
        }
        if ($notfoundtags != '') {
            return get_string('tag_invalid', 'local_adele', substr_replace($notfoundtags, "", -1));
        }
        return true;
    }
}
