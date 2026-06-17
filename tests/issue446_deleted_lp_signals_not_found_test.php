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
 * Regression test for GitHub issue #446:
 *   When a learning path is deleted the per-user snapshot in
 *   local_adele_path_user is intentionally KEPT (it holds the user's progress),
 *   but the student loader must signal the deletion (lp_deleted) and empty the
 *   json so the student view shows the same "not found" notice the editor shows
 *   instead of rendering a path that no longer exists.
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
 * @covers \local_adele\learning_paths::get_learning_user_relation
 */
final class issue446_deleted_lp_signals_not_found_test extends advanced_testcase {
    /**
     * Deleting the master LP must keep the user snapshot but make the student
     * loader report lp_deleted with an emptied json (issue #446).
     *
     * @return void
     */
    public function test_deleted_lp_is_signalled_and_snapshot_kept(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $gen = self::getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();

        $tree = json_encode(['tree' => ['nodes' => [], 'edges' => []], 'user_path_relation' => []]);

        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Delete me',
            'json' => $tree,
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
        ]);
        $puid = $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'json' => $tree,
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
        ]);

        $loadparams = [
            'learningpathid' => $lpid,
            'userpathid' => $user->id,
            'courseid' => $course->id,
        ];

        // Control: while the LP exists, no deletion is signalled.
        $before = learning_paths::get_learning_user_relation($loadparams);
        $this->assertTrue(empty($before['lp_deleted']), 'No deletion flag while the LP still exists.');

        // Delete the master learning path.
        learning_paths::delete_learning_path(['learningpathid' => $lpid, 'name' => 'Delete me']);

        // The user's snapshot is preserved (we do NOT cascade-delete it).
        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['id' => $puid]),
            'The user path snapshot must be preserved after the master LP is deleted (#446).'
        );

        // The student loader now flags the deletion and empties the json.
        $after = learning_paths::get_learning_user_relation($loadparams);
        $this->assertTrue(
            !empty($after['lp_deleted']),
            'get_learning_user_relation must flag lp_deleted when the master LP is gone (#446).'
        );
        $this->assertSame(
            '',
            $after['json'],
            'json must be emptied so the frontend does not parse a stale snapshot.'
        );
    }
}
