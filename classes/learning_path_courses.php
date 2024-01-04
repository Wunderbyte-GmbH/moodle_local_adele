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
 * @package     local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

/**
 * Class learning_path_courses
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_path_courses {

    /**
     * Start a new attempt for a user.
     *
     * @return array
     */
    public static function get_availablecourses() {
        $list = self::buildsqlquery();
        return array_map(fn($a) => (array)$a, $list);
    }

    /**
     * Build sql query with config filters.
     *
     * @return array
     */
    public static function buildsqlquery() {
        global $DB, $USER;
        $selectagg = $DB->sql_group_concat('tag.name') . ' as tags';
        $userquery = '';

        $configadele = get_config('local_adele');

        // Search courses that are tagged with the specified tag.
        $configtags['include'] = explode(',', str_replace(' ', '', $configadele->includetags));
        $configtags['exclude'] = explode(',', str_replace(' ', '', $configadele->excludetags));
        $configtags['category'] = self::get_categories($configadele->catfilter);
        $whereparamsquery = self::build_where_query($configtags);

        // Filter according to select button.
        if ($configadele->selectconfig != null && $configadele->selectconfig == 'only_subscribed') {
            global $USER;
            $userquery = "LEFT JOIN (SELECT DISTINCT e.courseid
                FROM {enrol} e
                LEFT JOIN {user_enrolments} ue ON
                (ue.enrolid = e.id AND ue.userid = :userid)
                ) en ON (en.courseid = c.id) ";

            $whereparamsquery['params']["userid"] = $USER->id;
        }
        $select = "SELECT s1.*
        FROM (
            SELECT DISTINCT c.id AS course_node_id, c.fullname, c.shortname, c.category, " . $selectagg . "
            FROM {course} c
            LEFT JOIN {tag_instance} ti ON ti.itemid = c.id AND ti.itemtype = 'course'
            LEFT JOIN {tag} tag ON ti.tagid = tag.id " .
            $userquery .
            " WHERE c.visible=1
            GROUP BY ti.itemid, c.id
        ) AS s1 " . $whereparamsquery['wherequery'];
        return $DB->get_records_sql($select, $whereparamsquery['params']);
    }

    /**
     * Build sql query with config filters.
     * @param string $categories
     * @return array
     */
    protected static function get_categories($categories) {
        global $DB;
        // Filter according to the category level.
        $configcategories = explode(',', str_replace(' ', '', $categories));
        $sqlcategories = "SELECT id FROM {course_categories} WHERE ";
        foreach ($configcategories as $index => $configcategory) {
            $sqlcategories .= "path LIKE '%/" . $configcategory . "/%'";
            if ($index + 1 < count($configcategories)) {
                $sqlcategories .= ' OR ';
            }
        }
        $categorylist = $DB->get_records_sql($sqlcategories);
        foreach ($categorylist as $category) {
            $configcategories[] = $category->id;
        }
        return $configcategories;
    }
    /**
     * Build sql query with config filters.
     * @param array $configfilters
     * @return array
     */
    protected static function build_where_query($configfilters) {

        global $DB;

        $params = [];
        $categoryparams = [];
        $tagqueries = [
            "(s1.tags OPERATOR :TAG OPERATION ",
            "s1.tags OPERATOR :TAG OPERATION ",
            "s1.tags OPERATOR :TAG OPERATION ",
            "s1.tags OPERATOR :TAG)",
        ];

        $where = [];
        foreach ($configfilters as $index => $configfilter) {
            if (!empty($configfilter[0])) {
                $indexfilter = 0;
                $filtercount = count($configfilter);
                if ($index == 'category') {
                    if ($filtercount > 0) {
                        list($inorequal, $categoryparams) = $DB->get_in_or_equal($configfilter, SQL_PARAMS_NAMED);
                        $where[] = " s1.category $inorequal  ";
                    }
                    // Because we always get an array with key 0 and empty string from settings.php.
                } else {
                    $wheretags = [];
                    $operator = $index == 'include' ? 'LIKE' : 'NOT LIKE';
                    $operation = $index == 'include' ? 'OR' : 'AND';
                    foreach ($configfilter as $filter) {
                        $wherequery = '';
                        $tagwildcards = [
                            $filter,
                            $filter . ",%",
                            "%, " . $filter,
                            "%, " . $filter .",%",
                        ];
                        foreach ($tagqueries as $indexquery => $tagquery) {
                            $wherequery .= str_replace(
                                ['OPERATOR', 'TAG', 'OPERATION'],
                                [$operator, $index . $indexfilter, $operation],
                                $tagquery);

                            $params[$index . $indexfilter] = $tagwildcards[$indexquery];
                            $indexfilter += 1;
                        }
                        if (!empty($wherequery)) {
                            $wheretags[] = $wherequery;
                        }
                    }
                    if (!empty($wheretags)) {
                        $where[] = "(" . implode(' OR ', $wheretags) . ")";
                    }
                }
            }
        }
        if (!empty($where)) {
            $wherequery = " WHERE " . implode(' AND ', $where);
        }

        foreach ($categoryparams as $key => $value) {
            $params[$key] = $value;
        }

        return [
            'wherequery' => $wherequery,
            'params' => $params,
        ];
    }
}
