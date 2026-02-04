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
require_once($CFG->dirroot . '/course/externallib.php');

/**
 * Class local_adele_generator for generation of dummy data
 *
 * @package local_adele
 * @category test
 * @copyright 2023 Andrii Semenets
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_adele_generator extends testing_module_generator {
    /**
     * Create catscale structure by importing from CSV file.
     *
     * @param array $data
     * @return int
     */
    public function create_adele_learningpaths(array $data) {
        global $DB, $CFG;

        // Load learning path data from file if it is provided (specifically for Behat tests).
        if (isset($data['filepath'])) {
            // Validate path and load JSON.
            $filepath = "{$CFG->dirroot}/{$data['filepath']}";
            if (!file_exists($filepath)) {
                throw new coding_exception("File '{$filepath}' does not exist");
            }
            if (!isset($data['courses']) || !is_string($data['courses'])) {
                throw new coding_exception("Courses must be provided as string");
            }
            // Prepare list of course IDs.
            $shortnamearr = explode(',', $data['courses']);
            $courses = [];
            foreach ($shortnamearr as $shortname) {
                $courses[] = $DB->get_field(
                    'course',
                    'id',
                    ['shortname' => trim($shortname)],
                    MUST_EXIST
                );
            }

            // Prepare image file path if correct.
            $imagefilepath = $data['image'] ? (file_exists("{$CFG->dirroot}/{$data['image']}") ? $data['image'] : '') : '';
            // Load LP from file.
            $content = file_get_contents($filepath);
            $data = json_decode($content, true);
            $data['image'] = $imagefilepath;
            $nodedata = json_decode($data['json'], true);
            // Associate courses with LP nodes.
            if (count($courses) < count($nodedata['tree']['nodes'])) {
                throw new coding_exception("No enough courses to fill LP nodes");
            }
            $i = 0;
            foreach ($nodedata['tree']['nodes'] as &$node) {
                if (isset($node['data']['course_node_id'])) {
                    $node['data']['course_node_id'] = [
                        $courses[$i],
                    ];
                    $i++;
                }
            }
            $data['json'] = json_encode($nodedata);
        }

        $id = $DB->insert_record('local_adele_learning_paths', $data);
        return $id;
    }
}
