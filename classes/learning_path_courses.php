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
 * Entities Class to display list of entity records.
 *
 * @package local_catquiz
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

/**
 * Class catquiz
 *
 * @author Georg Maißer
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_path_courses {

    /**
     * Start a new attempt for a user.
     *
     * @param int $userid
     * @param int $categoryid
     * @return array
     */
    public static function get_availablecourses() {
        global $DB;
        $list = self::buildsqlquery();
        return array_map(fn($a) => (array)$a, $list);
    }

    /**
     * Start a new attempt for a user.
     *
     * @param int $userid
     * @param int $categoryid
     * @return array
     */
    public static function buildsqlquery() {
        global $DB;
        $where = "c.id IN (SELECT t.itemid FROM {tag_instance} t WHERE (";
        $configadele = get_config('local_adele');

        // Search courses that are tagged with the specified tag.
        $configtags['OR'] = explode(',', str_replace(' ', '', $configadele->includetags));
        $configtags['AND'] = explode(',', str_replace(' ', '', $configadele->excludetags));

        // Filter according to the tags.
        if ($configtags['OR'][0] != null || $configtags['AND'][0] != null) {
            $concat = false;
            if ($configtags['OR'][0] != null && $configtags['AND'][0] != null) {
                $concat = true;
            }
            $params = [];
            $indexparam = 0;
            foreach ($configtags as $operator => $tags) {
                if (!empty($tags[0])) {
                    $tagscount = count($tags);
                    foreach ($tags as $index => $tag) {
                        $tag = (array) $DB->get_record('tag', ['name' => $tag], 'id, name');
                        $params['tag'. $indexparam] = $tag['id'];
                        $where .= "t.tagid";
                        $where .= $operator == 'OR' ? ' = ' : ' != ';
                        $where .= ":tag" . $indexparam;
                        if ($index + 1 < $tagscount) {
                            $where .= ' ' . $operator .' ';
                        } else {
                            $where .= ")";
                        };
                        $indexparam += 1;
                    }
                    if ($concat) {
                        $where .= " AND (";
                        $concat = false;
                    }
                }
            }
        }

        // Filter according to the category level.
        if ($configadele->catfilter[0] != null ) {
            $configcategories = explode(',', str_replace(' ', '', $configadele->catfilter));
            $sqlcategories = "SELECT id FROM {course_categories} WHERE ";
            foreach ($configcategories as $index => $configcategory) {
                $sqlcategories .= "path LIKE '%/" . $configcategory . "%'";
                if ($index + 1 < count($configcategories)) {
                    $sqlcategories .= ' OR ';
                }
            }
            $categorylist = $DB->get_records_sql($sqlcategories);
            if (!empty($categorylist) ) {
                $categorylist = array_values($categorylist);
                $where .= ' AND (';
                foreach ($categorylist as $catindex => $catid) {
                    $where .= 'category = :catid' . $catindex;
                    $params['catid' . $catindex] = $catid->id;
                    if ($catindex + 1 < count($categorylist)) {
                        $where .= ' OR ';
                    }
                }
                $where .= ')';
            }
        }
        $where .= ")";
        return self::get_course_records($where, $params);

    }

    protected static function get_course_records($whereclause, $params) {
        global $DB;
        $fields = array('c.id', 'c.fullname', 'c.shortname');
        $sql = "SELECT ". join(',', $fields).
                " FROM {course} c JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = :contextcourse WHERE ".
                $whereclause." ORDER BY c.sortorder";
        $list = $DB->get_records_sql($sql,
            array('contextcourse' => CONTEXT_COURSE) + $params);
        return $list;
    }
}
