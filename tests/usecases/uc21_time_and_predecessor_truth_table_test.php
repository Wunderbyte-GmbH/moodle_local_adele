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

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once(__DIR__ . '/../adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
require_once(__DIR__ . '/time_predecessor_truth_table.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// The attribute stands above the class comment: between comment and class
// it would hide the class-level @covers tags from the coding-style check.
#[RunTestsInSeparateProcesses]
/**
 * Time window AND predecessor, the whole truth table.
 *
 * AND: one column, predecessor then window - open only when both hold.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\relation_update
 * @covers     \local_adele\course_restriction\conditions\timed
 * @covers     \local_adele\course_restriction\conditions\parent_courses
 */
final class uc21_time_and_predecessor_truth_table_test extends adele_learningpath_testcase {
    use time_predecessor_truth_table;

    /**
     * The fixture.
     *
     * @return string
     */
    protected function fixturefile(): string {
        return 'simpleconcatlp.json';
    }

    /**
     * Real course ids; node 1 completes on its course alone.
     *
     * @param array $nodes
     * @return void
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_1') {
                $node['data']['course_node_id'] = [$this->courseids[0]];
                if (!empty($node['completion']['nodes'])) {
                    foreach ($node['completion']['nodes'] as &$condition) {
                        if ($condition['id'] === 'condition_1') {
                            $condition['childCondition'] = ['condition_1_feedback'];
                        }
                    }
                    unset($condition);
                    $node['completion']['nodes'] = array_values(array_filter(
                        $node['completion']['nodes'],
                        fn($condition) => $condition['id'] !== 'condition_2'
                    ));
                }
            } else {
                $node['data']['course_node_id'] = [$this->courseids[2]];
            }
        }
        unset($node);
    }

    /**
     * The expected value of a row.
     *
     * @param string $window
     * @param bool $done
     * @return bool Whether node 2 is accessible.
     */
    private function expected(string $window, bool $done): bool {
        return $window === 'inside' && $done;
    }

    /**
     * The six rows.
     *
     * @return array
     */
    public static function rows(): array {
        return self::table();
    }

    /**
     * One row: every user gets the expected status of node 2.
     *
     * @dataProvider rows
     * @param string $window
     * @param bool $done
     * @return void
     */
    public function test_row(string $window, bool $done): void {
        $expected = $this->expected($window, $done) ? 'accessible' : 'not_accessible';
        $statuses = $this->evaluate($window, $done);
        $this->assertNotEmpty($statuses);
        foreach ($statuses as $userid => $status) {
            $this->assertSame($expected, $status, "user {$userid}");
        }
    }
}
