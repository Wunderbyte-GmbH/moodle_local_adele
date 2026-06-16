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
 * Regression test for GitHub issue #449:
 *   removing all access criteria (Zugangskriterien) from a node did not open it.
 *
 * When the access criteria are removed, the node keeps a present-but-empty
 * restriction (`restriction => ['nodes' => []]`) rather than no restriction key
 * at all. relation_update::getnodestatus() only treated "no restriction key" /
 * null as unrestricted, so an empty restriction fell through to 'closed' and the
 * node stayed locked. An empty restriction must be treated like no restriction.
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
 * @covers \local_adele\relation_update::getnodestatus
 */
final class issue449_empty_restriction_test extends advanced_testcase {
    /**
     * Minimal feedback object as built by getfeedback() for a node with no
     * completion progress and no restriction feedback.
     *
     * @return array
     */
    private function feedback(): array {
        return [
            'completion' => ['after' => null],
            'restriction' => ['before' => null],
        ];
    }

    /**
     * A node whose access criteria were removed (empty restriction) must be
     * accessible — exactly like a node that never had a restriction.
     *
     * @return void
     */
    public function test_empty_restriction_is_accessible(): void {
        $node = ['restriction' => ['nodes' => []]];
        $this->assertSame(
            'accessible',
            relation_update::getnodestatus($this->feedback(), [], $node),
            'A node with an empty restriction must be accessible (issue #449).'
        );
    }

    /**
     * Control: a node that never had a restriction is accessible (unchanged).
     *
     * @return void
     */
    public function test_absent_restriction_is_accessible(): void {
        $node = [];
        $this->assertSame('accessible', relation_update::getnodestatus($this->feedback(), [], $node));
    }

    /**
     * Guard: a node with a real, unsatisfied access condition stays locked, so
     * the fix does not open genuinely restricted nodes.
     *
     * @return void
     */
    public function test_active_restriction_stays_locked(): void {
        $node = ['restriction' => ['nodes' => [
            ['id' => 'condition_1', 'data' => ['label' => 'manual'], 'childCondition' => []],
        ]]];
        $this->assertNotSame(
            'accessible',
            relation_update::getnodestatus($this->feedback(), [], $node),
            'A node with an unsatisfied access condition must not become accessible.'
        );
    }
}
