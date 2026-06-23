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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_adele;

use advanced_testcase;
use local_adele\task\check_timed_restrictions;

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Exhaustive unit tests for the #438 sweep gate: which paths are "due" for a
 * timed re-evaluation. Covers BOTH timed restrictions ('timed' = fixed date
 * window, 'timed_duration' = relative window). Pure logic, fixed clock, no DB.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\task\check_timed_restrictions::has_due_timed_boundary
 */
final class issue438_timed_sweep_gate_test extends advanced_testcase {

    /** @var int Fixed, minute-aligned reference "now" so datetime-local rounding is exact. */
    private const NOW = 1782000000;

    /** @var int A timecreated that is irrelevant to a given case. */
    private const TC_IRRELEVANT = 1782000000 - 1000000;

    /** Build a path with one fixed-date 'timed' restriction. */
    private static function timed(?int $start, ?int $end): array {
        $value = [];
        if ($start !== null) {
            $value['start'] = date('Y-m-d\TH:i', $start);
        }
        if ($end !== null) {
            $value['end'] = date('Y-m-d\TH:i', $end);
        }
        return ['tree' => ['nodes' => [
            ['restriction' => ['nodes' => [['data' => ['label' => 'timed', 'value' => $value]]]]],
        ]]];
    }

    /** Build a path with one 'timed_duration' restriction. */
    private static function td(string $option, string $durationvalue, $selectedduration,
            ?int $firstenrolled = null, bool $withoption = true): array {
        $value = ['durationValue' => $durationvalue, 'selectedDuration' => $selectedduration];
        if ($withoption) {
            $value['selectedOption'] = $option;
        }
        $nodedata = [];
        if ($firstenrolled !== null) {
            $nodedata['first_enrolled'] = $firstenrolled;
        }
        return ['tree' => ['nodes' => [
            ['data' => $nodedata, 'restriction' => ['nodes' => [
                ['data' => ['label' => 'timed_duration', 'value' => $value]],
            ]]],
        ]]];
    }

    /**
     * @return array<string, array{0: array, 1: int, 2: int, 3: bool}>
     *         [json, timemodified, timecreated, expected]
     */
    public static function boundary_provider(): array {
        $now = self::NOW;
        $tc = self::TC_IRRELEVANT;
        return [
            // ---- 'timed' (fixed date window) ----
            'timed start crossed'        => [self::timed($now - 3600, $now + 3600), $now - 7200, $tc, true],
            'timed end crossed'          => [self::timed($now - 7200, $now - 1800), $now - 3600, $tc, true],
            'timed start in future'      => [self::timed($now + 3600, $now + 7200), $now - 60, $tc, false],
            'timed start before tm'      => [self::timed($now - 10800, $now + 3600), $now - 7200, $tc, false],
            'timed start equals tm'      => [self::timed($now - 7200, $now + 3600), $now - 7200, $tc, false],
            'timed start equals now'     => [self::timed($now, $now + 3600), $now - 7200, $tc, true],
            'timed empty dates'          => [self::timed(null, null), $now - 7200, $tc, false],
            'manual label ignored'       => [
                ['tree' => ['nodes' => [['restriction' => ['nodes' => [
                    ['data' => ['label' => 'manual', 'value' => []]],
                ]]]]]], $now - 7200, $tc, false,
            ],
            'empty nodes'                => [['tree' => ['nodes' => []]], $now - 7200, $tc, false],
            'missing tree'               => [[], $now - 7200, $tc, false],

            // ---- 'timed_duration' (relative window: close = start_ref + duration) ----
            // option 0 -> start ref = timecreated. 1-day window.
            'td opt0 window closed'      => [self::td('0', '0', 1), $now - 7200, $now - 90000, true],
            'td opt0 window open'        => [self::td('0', '0', 1), $now, $now - 3600, false],
            'td opt0 already accounted'  => [self::td('0', '0', 1), $now - 1800, $now - 90000, false],
            // weeks (1 week) -> close = timecreated + 604800.
            'td opt0 weeks closed'       => [self::td('0', '1', 1), $now - 7200, $now - 608400, true],
            // invalid duration unit -> not computable.
            'td invalid duration unit'   => [self::td('0', '9', 1), $now - 7200, $now - 90000, false],
            // non-numeric multiplier -> not computable.
            'td empty multiplier'        => [self::td('0', '0', ''), $now - 7200, $now - 90000, false],
            // missing selectedOption -> condition would not evaluate -> not due.
            'td missing option'          => [self::td('0', '0', 1, null, false), $now - 7200, $now - 90000, false],
            // option 1 -> start ref = first_enrolled.
            'td opt1 no first_enrolled'  => [self::td('1', '0', 1), $now - 7200, $tc, false],
            'td opt1 closed'             => [self::td('1', '0', 1, $now - 90000), $now - 7200, $tc, true],
            'td opt1 open'               => [self::td('1', '0', 1, $now - 3600), $now, $tc, false],
        ];
    }

    /**
     * @dataProvider boundary_provider
     * @param array $json
     * @param int $timemodified
     * @param int $timecreated
     * @param bool $expected
     */
    public function test_has_due_timed_boundary(array $json, int $timemodified, int $timecreated, bool $expected): void {
        $this->assertSame(
            $expected,
            check_timed_restrictions::has_due_timed_boundary($json, $timemodified, $timecreated, self::NOW)
        );
    }

    /**
     * An unparseable date string must be ignored, never throw.
     */
    public function test_invalid_date_is_ignored(): void {
        $json = ['tree' => ['nodes' => [
            ['restriction' => ['nodes' => [
                ['data' => ['label' => 'timed', 'value' => ['start' => 'not-a-date', 'end' => '']]],
            ]]],
        ]]];
        $this->assertFalse(
            check_timed_restrictions::has_due_timed_boundary($json, self::NOW - 7200, self::TC_IRRELEVANT, self::NOW)
        );
    }

    /**
     * A node with no restriction key must be skipped without warnings.
     */
    public function test_node_without_restriction_is_skipped(): void {
        $json = ['tree' => ['nodes' => [
            ['id' => 'dndnode_1', 'data' => []],
            ['restriction' => ['nodes' => [
                ['data' => ['label' => 'timed', 'value' => ['start' => date('Y-m-d\TH:i', self::NOW - 1800)]]],
            ]]],
        ]]];
        $this->assertTrue(
            check_timed_restrictions::has_due_timed_boundary($json, self::NOW - 7200, self::TC_IRRELEVANT, self::NOW)
        );
    }

    /**
     * Among several restriction nodes (mixed types), a single due one is enough.
     */
    public function test_multiple_conditions_one_due(): void {
        $json = ['tree' => ['nodes' => [
            ['data' => [], 'restriction' => ['nodes' => [
                ['data' => ['label' => 'manual', 'value' => []]],
                ['data' => ['label' => 'timed', 'value' => ['start' => date('Y-m-d\TH:i', self::NOW + 99999)]]],
                ['data' => ['label' => 'timed_duration', 'value' => [
                    'selectedOption' => '0', 'durationValue' => '0', 'selectedDuration' => 1]]],
            ]]],
        ]]];
        // timed_duration close = timecreated(+1 day) lands in the window -> due.
        $this->assertTrue(
            check_timed_restrictions::has_due_timed_boundary($json, self::NOW - 7200, self::NOW - 90000, self::NOW)
        );
    }
}
