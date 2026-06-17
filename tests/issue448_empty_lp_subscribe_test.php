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
 * Regression test for GitHub issue #448:
 *   Adding an empty learning path to a course throws "Undefined array key
 *   tree" because the subscription builds the per-user snapshot from
 *   $learningpath->json['tree'] without guarding the (absent) key.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\enrollment::subscribe_user_to_learning_path
 */
final class issue448_empty_lp_subscribe_test extends advanced_testcase {
    /**
     * Subscribing a user to an empty learning path (json with no 'tree' key)
     * must not throw and must create the user snapshot (issue #448).
     *
     * @return void
     */
    public function test_subscribing_to_empty_lp_does_not_crash(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $creator = $gen->create_user();
        $this->setUser($creator);

        // A freshly created, empty learning path: json carries no 'tree' key.
        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Empty LP',
            'json' => json_encode(['name' => 'Empty LP']),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creator->id,
        ]);
        $learningpath = $DB->get_record('local_adele_learning_paths', ['id' => $lpid]);

        $params = (object) [
            'relateduserid' => $user->id,
            'userid' => $creator->id,
        ];

        // On the old code this raises "Undefined array key tree"; it must not.
        enrollment::subscribe_user_to_learning_path($learningpath, $params, (int) $course->id);

        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', [
                'learning_path_id' => $lpid,
                'user_id' => $user->id,
            ]),
            'Subscribing to an empty learning path must still create the user snapshot without crashing (#448).'
        );
    }
}
