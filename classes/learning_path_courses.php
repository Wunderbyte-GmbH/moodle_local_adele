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
        global $DB;
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
        $select = "SELECT s1.*
        FROM (
            SELECT ti.itemid AS course_node_id, c.fullname, c.shortname, c.category, " . $selectagg . "
            FROM m_tag_instance ti
            LEFT JOIN {tag} tag ON ti.tagid = tag.id
            LEFT JOIN {course} c ON ti.itemid = c.id
            %USERQUERY%
            WHERE ti.itemtype = 'course'
            GROUP BY ti.itemid, c.id
        ) AS s1 %WHEREQUERY%
        ";

        $configadele = get_config('local_adele');

        // Search courses that are tagged with the specified tag.
        $configtags['include'] = explode(',', str_replace(' ', '', $configadele->includetags));
        $configtags['exclude'] = explode(',', str_replace(' ', '', $configadele->excludetags));
        $configtags['category'] = self::get_categories($configadele->catfilter);
        $whereparamsquery = self::build_where_query($configtags);

        // Filter according to select button.
        if ($configadele->selectconfig != null && $configadele->selectconfig == 'only_subscribed') {
            global $USER;
            $userquery = "JOIN (SELECT DISTINCT e.courseid
                FROM {enrol} e
                JOIN {user_enrolments} ue ON
                (ue.enrolid = e.id AND ue.userid = :userid)
                ) en ON (en.courseid = c.id) ";

            $whereparamsquery['params']["userid"] = $USER->id;
        }

        $select = str_replace( ['%USERQUERY%', '%WHEREQUERY%'], [$userquery, $whereparamsquery['wherequery']], $select);
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
        $params = [];
        $wherequery = '';
        foreach ($configfilters as $index => $configfilter) {
            $tagqueries = [
                "(s1.tags OPERATOR :TAG OR ",
                "s1.tags OPERATOR :TAG OR ",
                "s1.tags OPERATOR :TAG OR ",
                "s1.tags OPERATOR :TAG)",
            ];
            if (!empty($configfilter[0])) {
                $indexfilter = 0;
                $filtercount = count($configfilter);
                if ($index == 'category') {
                    $wherequery .= '(';
                    foreach ($configfilter as $filter) {
                        $wherequery .= 's1.category = :' . $index . $indexfilter;
                        $params[$index . $indexfilter] = $filter;
                        if ($indexfilter + 1 < $filtercount) {
                            $wherequery .= ' OR ';
                        }
                        $indexfilter += 1;
                    }
                    $wherequery .= ")";
                } else {
                    $operator = $index == 'include' ? 'LIKE' : 'NOT LIKE';
                    foreach ($configfilter as $filter) {
                        $tagwildcards = [
                            $filter,
                            $filter . ",%",
                            "%," . $filter,
                            "%," . $filter .",%",
                        ];
                        foreach ($tagqueries as $indexquery => $tagquery) {
                            $wherequery .= str_replace(['OPERATOR', 'TAG'], [$operator, $index . $indexfilter], $tagquery);

                            $params[$index . $indexfilter] = $tagwildcards[$indexquery];
                            $indexfilter += 1;
                        }
                    }
                }
            }
        }
        if ($wherequery != '') {
            $wherequery = 'WHERE ' . str_replace(')(', ') AND (', $wherequery);
        }
        return [
            'wherequery' => $wherequery,
            'params' => $params,
        ];

    }
}
