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

defined('MOODLE_INTERNAL') || die();

/**
 * Class local_catquiz_generator for generation of dummy data
 *
 * @package local_catquiz
 * @category test
 * @copyright 2023 Andrii Semenets
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_adele_generator extends testing_module_generator {

    /**
     * Create catscale structure by importing from CSV file.
     *
     * @param array $data
     * @return void
     */
    public function create_adele_learningpaths(array $data) {
        global $DB;
        $content = file_get_contents(__DIR__ . '/../fixtures/' . $data['filename']);
        $object = json_decode($content);
        $DB->insert_record('local_adele_learning_paths', $object);
    }
}
