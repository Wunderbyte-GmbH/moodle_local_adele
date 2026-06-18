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
 * Regression test for GitHub issue #466:
 *   Duplicate rows in local_adele_lp_editors (non-idempotent create_editors)
 *   collided the get_records_sql key in return_learningpaths /
 *   get_editable_learning_paths, raising the "first column must be unique"
 *   debugging warning and crashing the navbar / module form under debug.
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
 * @covers \local_adele\learning_path_editors::create_editors
 * @covers \local_adele\learning_paths::return_learningpaths
 */
final class issue466_duplicate_editors_test extends advanced_testcase {
    /**
     * Adding the same editor twice must not create a second row (#466).
     *
     * @return void
     */
    public function test_create_editors_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Issue 466 LP',
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);

        learning_path_editors::create_editors($lpid, $user->id);
        learning_path_editors::create_editors($lpid, $user->id);

        $this->assertSame(
            1,
            $DB->count_records('local_adele_lp_editors', ['learningpathid' => $lpid, 'userid' => $user->id]),
            'create_editors must be idempotent - the same (path, user) must yield a single row.'
        );
    }

    /**
     * Pre-existing duplicate editor rows must not collide the get_records_sql key
     * (no "first column must be unique" debugging) and must collapse to one entry.
     *
     * @return void
     */
    public function test_duplicate_rows_do_not_break_return_learningpaths(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Issue 466 LP',
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);

        // Simulate legacy duplicate data (two editor rows for the same path/user).
        $DB->insert_record('local_adele_lp_editors', (object) ['learningpathid' => $lpid, 'userid' => $user->id]);
        $DB->insert_record('local_adele_lp_editors', (object) ['learningpathid' => $lpid, 'userid' => $user->id]);

        $records = learning_paths::return_learningpaths();

        // DISTINCT collapses the duplicates to a single, uniquely-keyed entry; the
        // "first column must be unique" debugging is never raised (would fail the test).
        $this->assertCount(1, $records);
        $this->assertArrayHasKey($lpid, $records);
    }
}
