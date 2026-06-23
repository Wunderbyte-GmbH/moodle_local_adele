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

namespace local_adele;

use advanced_testcase;

/**
 * Reproduce-first test for GitHub #456: an incomplete restriction criterion (no node
 * selected) or a deleted-node reference must never leak a literal {placeholder} token
 * into the feedback shown to a student.
 *
 * @package     local_adele
 * @copyright   2026 cbadusch
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\relation_update::render_placeholders_single_restriction
 * @covers \local_adele\relation_update::render_placeholders
 */
final class issue456_placeholder_leak_test extends advanced_testcase {
    /**
     * An unresolved {node_name} (placeholder never set because no node was selected)
     * must be stripped, not shown literally to the student.
     */
    public function test_single_restriction_strips_unresolved_node_name(): void {
        // A specific_course / parent_courses style feedback string.
        $string = 'Sie {node_name} abgeschlossen haben';
        // Condition WITHOUT a node_name placeholder (mirrors specific_course when no
        // course/node was selected — the foreach that would set it never matches).
        $condition = ['placeholders' => ['numb_courses' => 1]];

        $result = relation_update::render_placeholders_single_restriction($string, 'n1', [], $condition);

        $this->assertStringNotContainsString('{node_name}', $result,
            'Unresolved {node_name} leaked into the student-facing feedback (#456).');
    }

    /**
     * The render_placeholders variant (used for feedback nodes) must also strip leftovers.
     */
    public function test_render_placeholders_strips_unresolved_node_name(): void {
        $string = '„{node_name}" abschliessen';
        // A set of conditions, none of which provides node_name.
        $placeholders = [
            ['placeholders' => ['numb_courses' => '2']],
        ];

        $result = relation_update::render_placeholders($string, $placeholders, 'n1', []);

        $this->assertStringNotContainsString('{node_name}', $result,
            'Unresolved {node_name} leaked through render_placeholders (#456).');
    }

    /**
     * Regression guard: a placeholder that IS provided must still be substituted correctly
     * (the strip must not eat resolved values).
     */
    public function test_resolved_placeholder_is_substituted(): void {
        $string = 'Sie {node_name} abgeschlossen haben';
        $condition = ['placeholders' => ['node_name' => ['Course 1']]];

        $result = relation_update::render_placeholders_single_restriction($string, 'n1', [], $condition);

        $this->assertStringContainsString('Course 1', $result);
        $this->assertStringNotContainsString('{node_name}', $result);
    }
}
