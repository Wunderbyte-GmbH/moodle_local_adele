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
 * Regression test for GitHub issue #450:
 *   A "specific node completed" (specific_course) access criterion could be
 *   saved with no node selected, producing a broken path that renders the
 *   literal {node_name} placeholder. Saving such a path must be refused.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use context_system;
use local_adele\external\save_learningpath;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_paths::assert_conditions_complete
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 */
#[RunTestsInSeparateProcesses]
final class issue450_incomplete_condition_test extends advanced_testcase {
    /**
     * Build a learning-path tree whose node carries a single specific_course
     * restriction condition with the given courseid (null = no node selected).
     *
     * @param string|null $courseid
     * @return string json
     */
    private function tree_with_specific_course($courseid): string {
        return json_encode([
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1',
                        'data' => ['fullname' => 'Node A'],
                        'restriction' => [
                            'nodes' => [
                                [
                                    'id' => 'condition_1',
                                    'data' => [
                                        'label' => 'specific_course',
                                        'value' => ['courseid' => $courseid],
                                    ],
                                ],
                            ],
                            'edges' => [],
                        ],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                ],
                'edges' => [],
            ],
            'modules' => null,
        ]);
    }

    /**
     * Saving a path whose specific_course condition has no node selected must be
     * refused (#450).
     *
     * @return void
     */
    public function test_save_rejects_incomplete_specific_course(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $ctxid = context_system::instance()->id;

        $this->expectException(\moodle_exception::class);
        save_learningpath::execute(2, 0, 'Broken LP', '', $this->tree_with_specific_course(null), $ctxid, '');
    }

    /**
     * A specific_course condition with a node selected saves normally.
     *
     * @return void
     */
    public function test_save_accepts_complete_specific_course(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $ctxid = context_system::instance()->id;

        $result = save_learningpath::execute(2, 0, 'Valid LP', '', $this->tree_with_specific_course('dndnode_2'), $ctxid, '');

        $this->assertTrue($DB->record_exists('local_adele_learning_paths', ['id' => $result['learningpath']->id]));
    }

    /**
     * Build a tree whose node carries a single completion condition of the given
     * label and value.
     *
     * @param string $label
     * @param array $value
     * @return string json
     */
    private function tree_with_completion(string $label, array $value): string {
        return json_encode([
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1',
                        'data' => ['fullname' => 'Node A'],
                        'restriction' => ['nodes' => [], 'edges' => []],
                        'completion' => [
                            'nodes' => [
                                ['id' => 'condition_1', 'data' => ['label' => $label, 'value' => $value]],
                            ],
                            'edges' => [],
                        ],
                    ],
                ],
                'edges' => [],
            ],
            'modules' => null,
        ]);
    }

    /**
     * A modquiz completion condition with no quiz selected must be refused (#450).
     *
     * @return void
     */
    public function test_save_rejects_incomplete_modquiz(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $ctxid = context_system::instance()->id;

        $this->expectException(\moodle_exception::class);
        save_learningpath::execute(2, 0, 'Broken modquiz', '', $this->tree_with_completion('modquiz', ['quizid' => null]), $ctxid, '');
    }

    /**
     * A catquiz completion condition with no test selected must be refused (#450).
     *
     * @return void
     */
    public function test_save_rejects_incomplete_catquiz(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $ctxid = context_system::instance()->id;

        $this->expectException(\moodle_exception::class);
        save_learningpath::execute(2, 0, 'Broken catquiz', '', $this->tree_with_completion('catquiz', []), $ctxid, '');
    }

    /**
     * catquiz testid = 0 is a legitimate selection (parent-scale mode) and must NOT
     * be treated as incomplete.
     *
     * @return void
     */
    public function test_save_accepts_catquiz_test_zero(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $ctxid = context_system::instance()->id;

        $result = save_learningpath::execute(2, 0, 'Catquiz parent scale', '', $this->tree_with_completion('catquiz', ['testid' => 0]), $ctxid, '');

        $this->assertTrue($DB->record_exists('local_adele_learning_paths', ['id' => $result['learningpath']->id]));
    }
}
