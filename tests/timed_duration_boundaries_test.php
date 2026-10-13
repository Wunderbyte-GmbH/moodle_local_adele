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

use local_adele\course_restriction\conditions\timed_duration;

/**
 * The relative period (timed_duration) at its boundaries, for two persons.
 *
 * Chain T of the E2E plan asks for t0 - 1 s, t0 and t0 + 1 s, and for two
 * persons whose period starts at different moments (E2E issue, section 2,
 * T1-T5). The browser chain can only check either side of the re-evaluation
 * buffer; the exact second is checked here, with a frozen clock - both
 * starting points the editor offers: the subscription to the path, and the
 * first enrolment into the node.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\course_restriction\conditions\timed_duration
 * @covers     \local_adele\helper\time_value
 */
final class timed_duration_boundaries_test extends \advanced_testcase {
    /** @var int Person A's start: 2030-01-15 10:00:00 UTC. */
    private const START_A = 1894701600;

    /** @var int Person B's start: one hour and seven seconds later. */
    private const START_B = 1894705207;

    /** @var int One day. */
    private const DAY = 86400;

    /**
     * A node open for one day from the chosen starting point.
     *
     * @param string $option '0' from the subscription to the path, '1' from the first enrolment into the node.
     * @param int|null $firstenrolled The node's first enrolment, if any.
     * @return array
     */
    private static function node(string $option, ?int $firstenrolled): array {
        $node = ['id' => 'dndnode_1', 'data' => [], 'restriction' => ['nodes' => [[
            'id' => 'condition_1',
            'data' => ['label' => 'timed_duration', 'value' => [
                'selectedOption' => $option,
                'durationValue' => '0',
                'selectedDuration' => '1',
            ]],
        ]]]];
        if ($firstenrolled !== null) {
            $node['data']['first_enrolled'] = $firstenrolled;
        }
        return $node;
    }

    /**
     * The status of the condition for one person at a frozen instant.
     *
     * @param string $option
     * @param int $start The person's starting point.
     * @param int $now
     * @return array [before, inside, after]
     */
    private function state(string $option, int $start, int $now): array {
        $this->mock_clock_with_frozen($now);
        $node = self::node($option, $option === '1' ? $start : null);
        $userpath = (object) ['timecreated' => $option === '0' ? $start : 0, 'user_id' => 0];
        $status = (new timed_duration())->get_restriction_status($node, $userpath)['condition_1'];
        return [$status['isbefore'], $status['completed'], $status['isafter']];
    }

    /**
     * Both starting points the editor offers.
     *
     * @return array
     */
    public static function starting_points(): array {
        return [
            'from the subscription to the path' => ['0'],
            'from the first enrolment into the node' => ['1'],
        ];
    }

    /**
     * Every second around the start and the end, half-open, for both persons
     * - and each person by their own clock, not by the other's.
     *
     * @dataProvider starting_points
     * @param string $option
     * @return void
     */
    public function test_boundaries_per_person(string $option): void {
        $this->resetAfterTest();
        foreach (['A' => self::START_A, 'B' => self::START_B] as $person => $start) {
            $end = $start + self::DAY;
            $cases = [
                't0 - 1 s' => [$start - 1, [true, false, false]],
                't0' => [$start, [false, true, false]],
                't0 + 1 s' => [$start + 1, [false, true, false]],
                'end - 1 s' => [$end - 1, [false, true, false]],
                'end' => [$end, [false, false, true]],
                'end + 1 s' => [$end + 1, [false, false, true]],
            ];
            foreach ($cases as $label => [$now, $expected]) {
                $this->assertSame($expected, $this->state($option, $start, $now), "person {$person}, {$label}");
            }
        }
    }

    /**
     * At one and the same instant the two persons differ: A's day is over,
     * B's is not - the period belongs to the person.
     *
     * @dataProvider starting_points
     * @param string $option
     * @return void
     */
    public function test_one_instant_two_answers(string $option): void {
        $this->resetAfterTest();
        $now = self::START_A + self::DAY;
        $this->assertSame([false, false, true], $this->state($option, self::START_A, $now), 'A: just over');
        $this->assertSame([false, true, false], $this->state($option, self::START_B, $now), 'B: still inside');
        $now = self::START_B - 1;
        $this->assertSame([false, true, false], $this->state($option, self::START_A, $now), 'A: inside');
        $this->assertSame([true, false, false], $this->state($option, self::START_B, $now), 'B: not yet');
    }

    /**
     * Counter-check of the test itself: a period without its starting point
     * opens for nobody - the node says why instead of guessing a time.
     *
     * @return void
     */
    public function test_missing_first_enrolment_opens_nothing(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::START_A);
        $userpath = (object) ['timecreated' => self::START_A];
        $status = (new timed_duration())->get_restriction_status(self::node('1', null), $userpath)['condition_1'];
        $this->assertFalse($status['completed']);
        $this->assertFalse($status['isbefore']);
        $this->assertFalse($status['isafter']);
        $this->assertSame(
            get_string('course_condition_timed_duration_start', 'local_adele'),
            $status['placeholders']['timed_condition']
        );
    }
}
