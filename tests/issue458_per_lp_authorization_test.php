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
 * Regression test for GitHub issue #458 (per-LP authorization):
 *   The mutating learning-path web services gated only on check_access() (a
 *   boolean "editor of anything / assistant / manager"), not on ownership of
 *   the specific learning path. So any editor/assistant could overwrite ANY
 *   learning path by id via the web service (IDOR), even though the editor UI
 *   only lists their own. Editing must be restricted to the caller's own LPs
 *   (lp_editors membership) - managers/admins keep full access.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use context_system;
use local_adele\external\save_learningpath;
use local_adele\external\update_lp_visiblity;
use local_adele\external\create_lp_edit_users;
use local_adele\external\get_lp_edit_users;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_paths::require_lp_editor_access
 * @covers \local_adele\external\save_learningpath
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 */
#[RunTestsInSeparateProcesses]
final class issue458_per_lp_authorization_test extends advanced_testcase {
    /** Empty-tree json used for all fixtures. */
    private function emptytree(): string {
        return json_encode(['tree' => ['nodes' => [], 'edges' => []], 'modules' => null]);
    }

    /**
     * Create a learning path owned (lp_editors) by $userid.
     *
     * @param string $name
     * @param int $userid
     * @return int learningpathid
     */
    private function make_lp(string $name, int $userid): int {
        global $DB;
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => $name,
            'description' => '',
            'json' => $this->emptytree(),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $userid,
            'visibility' => 0,
        ]);
        $DB->insert_record('local_adele_lp_editors', (object) ['learningpathid' => $lpid, 'userid' => $userid]);
        return $lpid;
    }

    /**
     * An editor of LP-B must NOT be able to overwrite LP-A (which they do not
     * own) via save_learningpath - the core IDOR (#458).
     *
     * @return void
     */
    public function test_non_owner_editor_cannot_overwrite_others_lp(): void {
        $this->resetAfterTest(true);
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $lpa = $this->make_lp('Owned by A', $a->id);
        $this->make_lp('Owned by B', $b->id); // B is an editor of something -> passes check_access.
        $ctxid = context_system::instance()->id;

        $this->setUser($b);
        $this->expectException(\core\exception\required_capability_exception::class);
        save_learningpath::execute($b->id, $lpa, 'HACKED BY B', 'x', $this->emptytree(), $ctxid, '');
    }

    /**
     * The owner (lp_editors member) can update their own LP.
     *
     * @return void
     */
    public function test_owner_can_update_own_lp(): void {
        global $DB;
        $this->resetAfterTest(true);
        $a = $this->getDataGenerator()->create_user();
        $lpa = $this->make_lp('Owned by A', $a->id);
        $ctxid = context_system::instance()->id;

        $this->setUser($a);
        save_learningpath::execute($a->id, $lpa, 'Renamed by A', 'x', $this->emptytree(), $ctxid, '');

        $this->assertSame('Renamed by A', $DB->get_record('local_adele_learning_paths', ['id' => $lpa])->name);
    }

    /**
     * Creating a new LP (learningpathid = 0) stays open to any editor/assistant.
     *
     * @return void
     */
    public function test_any_editor_can_create_new_lp(): void {
        global $DB;
        $this->resetAfterTest(true);
        $b = $this->getDataGenerator()->create_user();
        $this->make_lp('Owned by B', $b->id); // B passes check_access.
        $ctxid = context_system::instance()->id;

        $this->setUser($b);
        $result = save_learningpath::execute($b->id, 0, 'Brand new', 'x', $this->emptytree(), $ctxid, '');

        $newid = $result['learningpath']->id;
        $this->assertTrue($DB->record_exists('local_adele_learning_paths', ['id' => $newid]));
        $this->assertTrue(
            $DB->record_exists('local_adele_lp_editors', ['learningpathid' => $newid, 'userid' => $b->id]),
            'The creator must be auto-added as an editor of the new LP.'
        );
    }

    /**
     * A manager/admin may update any LP (full access bypass).
     *
     * @return void
     */
    public function test_manager_can_update_any_lp(): void {
        global $DB;
        $this->resetAfterTest(true);
        $a = $this->getDataGenerator()->create_user();
        $lpa = $this->make_lp('Owned by A', $a->id);
        $ctxid = context_system::instance()->id;

        $this->setAdminUser();
        save_learningpath::execute(2, $lpa, 'Renamed by admin', 'x', $this->emptytree(), $ctxid, '');

        $this->assertSame('Renamed by admin', $DB->get_record('local_adele_learning_paths', ['id' => $lpa])->name);
    }

    /**
     * The same per-LP rule must apply to the other mutators - a non-owner cannot
     * toggle another path's visibility (#458).
     *
     * @return void
     */
    public function test_non_owner_cannot_toggle_others_visibility(): void {
        $this->resetAfterTest(true);
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $lpa = $this->make_lp('Owned by A', $a->id);
        $this->make_lp('Owned by B', $b->id);
        $ctxid = context_system::instance()->id;

        $this->setUser($b);
        $this->expectException(\core\exception\required_capability_exception::class);
        update_lp_visiblity::execute($ctxid, $lpa, 1);
    }

    /**
     * A non-owner cannot add a named person (editor) to another path (#458).
     *
     * @return void
     */
    public function test_non_owner_cannot_add_editor_to_others_lp(): void {
        $this->resetAfterTest(true);
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $victim = $this->getDataGenerator()->create_user();
        $lpa = $this->make_lp('Owned by A', $a->id);
        $this->make_lp('Owned by B', $b->id);
        $ctxid = context_system::instance()->id;

        $this->setUser($b);
        $this->expectException(\core\exception\required_capability_exception::class);
        create_lp_edit_users::execute($ctxid, $lpa, $victim->id);
    }

    /**
     * A brand-new, unsaved learning path (learningpathid = 0) has no owner yet,
     * so any editor/assistant must still be able to work with it (e.g. the editor
     * loading the empty editors panel while creating a new LP). Per-LP ownership
     * must NOT be required for id 0 (#458 regression).
     *
     * @return void
     */
    public function test_new_unsaved_lp_does_not_require_ownership(): void {
        $this->resetAfterTest(true);
        $b = $this->getDataGenerator()->create_user();
        $this->make_lp('Owned by B', $b->id); // B passes check_access (editor of something).
        $ctxid = context_system::instance()->id;

        $this->setUser($b);
        $editors = get_lp_edit_users::execute($ctxid, 0);
        $this->assertIsArray($editors);
    }
}
