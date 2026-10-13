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

use local_adele\course_restriction\conditions\timed;
use local_adele\course_restriction\conditions\timed_duration;
use local_adele\helper\adhoc_task_helper;

/**
 * Issue #582: the time-dependent code follows core's clock.
 *
 * Each test freezes the clock and checks a decision that depends on "now".
 * Before #582 these could only be tested by waiting.
 *
 * The exact second at which a `timed` window opens or closes is tested in
 * timed_timestamps_test.php (issue #581); these tests stay hours away from
 * the boundaries.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\course_restriction\conditions\timed
 * @covers     \local_adele\course_restriction\conditions\timed_duration
 * @covers     \local_adele\helper\adhoc_task_helper
 */
final class clock_frozen_test extends \advanced_testcase {
    /** @var string Window start in the stored datetime-local format. */
    private const START = '2030-01-15T10:00';

    /** @var string Window end in the stored datetime-local format. */
    private const END = '2030-01-15T12:00';

    /**
     * A node carrying one fixed window.
     *
     * @return array
     */
    private static function timed_node(): array {
        return ['id' => 'dndnode_1', 'restriction' => ['nodes' => [[
            'id' => 'condition_1',
            'data' => ['label' => 'timed', 'value' => ['start' => self::START, 'end' => self::END]],
        ]]]];
    }

    /**
     * Unix time of a stored datetime-local value, in the same timezone the plugin parses it in.
     *
     * @param string $value
     * @return int
     */
    private static function ts(string $value): int {
        return \DateTime::createFromFormat('!Y-m-d\TH:i', $value)->getTimestamp();
    }

    /**
     * The fixed window follows the frozen clock: before, inside, after.
     *
     * @return void
     */
    public function test_timed_window_follows_the_clock(): void {
        $this->resetAfterTest();
        $cases = [
            'one hour before' => [self::ts(self::START) - HOURSECS, false, true, false],
            'one hour inside' => [self::ts(self::START) + HOURSECS, true, false, false],
            'one hour after' => [self::ts(self::END) + HOURSECS, false, false, true],
        ];
        foreach ($cases as $label => [$now, $valid, $before, $after]) {
            $this->mock_clock_with_frozen($now);
            $result = (new timed())->get_restriction_status(self::timed_node(), (object) ['timecreated' => 0]);
            $status = $result['condition_1'];
            $this->assertSame($valid, $status['completed'], $label . ': valid');
            $this->assertSame($before, $status['isbefore'], $label . ': before');
            $this->assertSame($after, $status['isafter'], $label . ': after');
        }
    }

    /**
     * The relative window counts from first_enrolled and is half-open, exactly
     * like the fixed window of `timed`: open from its first second up to, but
     * not including, its end (#581).
     *
     * @return void
     */
    public function test_timed_duration_window_follows_the_clock(): void {
        $this->resetAfterTest();
        $enrolled = 1893456000; // 2030-01-01 00:00:00 UTC.
        $length = timed_duration::DURATION_SECONDS['0']; // One day.
        $node = [
            'id' => 'dndnode_1',
            'data' => ['first_enrolled' => $enrolled],
            'restriction' => ['nodes' => [[
                'id' => 'condition_1',
                'data' => ['label' => 'timed_duration', 'value' => [
                    'selectedOption' => '1', 'durationValue' => '0', 'selectedDuration' => 1,
                ]],
            ]]],
        ];
        $cases = [
            'first second of the window' => [$enrolled, true, false],
            'last second of the window' => [$enrolled + $length - 1, true, false],
            'end: closed' => [$enrolled + $length, false, true],
            'first second after the end' => [$enrolled + $length + 1, false, true],
        ];
        foreach ($cases as $label => [$now, $inside, $after]) {
            $this->mock_clock_with_frozen($now);
            $status = (new timed_duration())->get_restriction_status($node, (object) ['timecreated' => 0])['condition_1'];
            $this->assertSame($inside, $status['inbetween'], $label . ': inside');
            $this->assertSame($after, $status['isafter'], $label . ': after');
        }
    }

    /**
     * Boundaries in the future get a re-evaluation task, past ones do not.
     *
     * @return void
     */
    public function test_boundary_tasks_follow_the_clock(): void {
        global $DB;
        $this->resetAfterTest();
        $userpath = (object) ['learning_path_id' => 1, 'user_id' => 2, 'course_id' => 1, 'id' => 99, 'timecreated' => 0];
        $classname = '\\' . \local_adele\task\update_user_path::class;

        // Before the window: both boundaries lie ahead.
        $this->mock_clock_with_frozen(self::ts(self::START) - HOURSECS);
        adhoc_task_helper::set_scheduled_adhoc_tasks(self::timed_node(), $userpath);
        $runtimes = $DB->get_fieldset_select('task_adhoc', 'nextruntime', 'classname = ?', [$classname]);
        sort($runtimes);
        $this->assertEquals(
            [self::ts(self::START) + 120, self::ts(self::END) + 120],
            array_map('intval', $runtimes),
            'each future boundary is re-evaluated 120 s after it passes'
        );

        // Inside the window: only the end still lies ahead.
        $DB->delete_records('task_adhoc', ['classname' => $classname]);
        $this->mock_clock_with_frozen(self::ts(self::START) + HOURSECS);
        adhoc_task_helper::set_scheduled_adhoc_tasks(self::timed_node(), $userpath);
        $runtimes = $DB->get_fieldset_select('task_adhoc', 'nextruntime', 'classname = ?', [$classname]);
        $this->assertEquals(
            [self::ts(self::END) + 120],
            array_map('intval', $runtimes),
            'a boundary already passed must not be scheduled again'
        );
    }
}
