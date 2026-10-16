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
 * The owner always stands on the list of editors of their path
 * (decision 2026-10-08, "Regel B").
 *
 * Editing is decided by that list alone; an owner taken off it lost their
 * own path (found with chain R5). Now removing the owner is refused - by the
 * owner, by another editor, by the Adele Manager - and an upgrade step puts
 * back owners taken off before.
 *
 * @package    local_adele
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use context_system;
use externallib_advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Owner stays editor.
 *
 * @package    local_adele
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\external\remove_lp_edit_users
 * @covers \local_adele\ownership::ensure_owners_are_editors
 */
final class owner_stays_editor_test extends externallib_advanced_testcase {
    /**
     * A path owned by $owner with the given editors (the owner among them).
     *
     * @param \stdClass $owner The owner.
     * @param \stdClass[] $editors Further editors.
     * @param bool $ownerlisted Whether the owner is on the list.
     * @return int The path id.
     */
    private function path(\stdClass $owner, array $editors, bool $ownerlisted = true): int {
        global $DB;
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Owner ' . $owner->id,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $owner->id,
        ]);
        foreach (array_merge($ownerlisted ? [$owner] : [], $editors) as $editor) {
            $DB->insert_record('local_adele_lp_editors', (object) [
                'learningpathid' => $lpid,
                'userid' => $editor->id,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        return $lpid;
    }

    /**
     * Whether somebody is on the list of editors.
     *
     * @param int $lpid The path.
     * @param \stdClass $user The person.
     * @return bool
     */
    private function listed(int $lpid, \stdClass $user): bool {
        global $DB;
        return $DB->record_exists('local_adele_lp_editors', ['learningpathid' => $lpid, 'userid' => $user->id]);
    }

    /**
     * Who tries it makes no difference: the owner, another editor, the manager.
     *
     * @return array
     */
    public static function who_tries(): array {
        // Another editor is stopped earlier already: removing OTHER people is
        // reserved for the owner and the manager (#571).
        return [
            'the owner' => ['owner', 'editorremoveowner'],
            'another editor' => ['editor', 'nopermissions'],
            'the administrator (Adele Manager)' => ['admin', 'editorremoveowner'],
        ];
    }

    /**
     * Removing the owner is refused, and the owner stays on the list.
     *
     * @dataProvider who_tries
     * @param string $who Who asks.
     * @param string $reason The error code of the refusal.
     */
    public function test_removing_the_owner_is_refused(string $who, string $reason): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $editor = $this->getDataGenerator()->create_user();
        $lpid = $this->path($owner, [$editor]);
        if ($who === 'admin') {
            $this->setAdminUser();
        } else {
            $this->setUser($who === 'owner' ? $owner : $editor);
        }
        try {
            external\remove_lp_edit_users::execute(context_system::instance()->id, $lpid, (int) $owner->id);
            $this->fail('removing the owner must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame($reason, $e->errorcode);
        }
        $this->assertTrue($this->listed($lpid, $owner), 'the owner must still be on the list');
    }

    /**
     * Everybody else can still be taken off - by the owner, and by themselves.
     */
    public function test_other_editors_can_still_be_removed(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $lpid = $this->path($owner, [$first, $second]);

        $this->setUser($owner);
        external\remove_lp_edit_users::execute(context_system::instance()->id, $lpid, (int) $first->id);
        $this->assertFalse($this->listed($lpid, $first));

        $this->setUser($second);
        external\remove_lp_edit_users::execute(context_system::instance()->id, $lpid, (int) $second->id);
        $this->assertFalse($this->listed($lpid, $second));
        $this->assertTrue($this->listed($lpid, $owner));
    }

    /**
     * The upgrade step puts back owners taken off before - and only those
     * whose account still exists.
     */
    public function test_owners_taken_off_before_are_put_back(): void {
        global $DB;
        $this->resetAfterTest(true);
        $gen = $this->getDataGenerator();
        $off = $gen->create_user();
        $on = $gen->create_user();
        $gone = $gen->create_user();
        $editor = $gen->create_user();
        $pathoff = $this->path($off, [$editor], false);
        $pathon = $this->path($on, [$editor]);
        $pathgone = $this->path($gone, [$editor], false);
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);

        $this->assertSame(1, ownership::ensure_owners_are_editors());

        $this->assertTrue($this->listed($pathoff, $off), 'the owner taken off is back');
        $this->assertSame(2, $DB->count_records('local_adele_lp_editors', ['learningpathid' => $pathon]), 'nothing doubled');
        $this->assertFalse($this->listed($pathgone, $gone), 'a deleted owner is left to the successor rule');
        $this->assertSame(0, ownership::ensure_owners_are_editors(), 'a second run finds nothing');
    }
}
