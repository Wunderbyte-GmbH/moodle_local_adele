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
use local_adele\helper\time_value;

/**
 * Timed access conditions compare timestamps (issue #581).
 *
 * The acceptance criteria of #581, one test each:
 * - boundaries are exact to the second and do not depend on the second in
 *   which the check runs (half-open window: start <= now < end),
 * - the author's and the site's time zone cannot shift a stored window,
 * - a daylight-saving change is unambiguous,
 * - existing paths keep working: legacy strings are still read, and the
 *   upgrade rewrites them once and only once.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\helper\time_value
 * @covers     \local_adele\course_restriction\conditions\timed
 * @covers     \local_adele\relation_update
 */
final class timed_timestamps_test extends \advanced_testcase {
    /** @var int 2030-01-15 10:00:00 UTC. */
    private const START = 1894701600;

    /** @var int 2030-01-15 12:00:00 UTC. */
    private const END = 1894708800;

    /**
     * A node with one window.
     *
     * @param mixed $start
     * @param mixed $end
     * @return array
     */
    private static function node($start, $end): array {
        return ['id' => 'dndnode_1', 'restriction' => ['nodes' => [[
            'id' => 'condition_1',
            'data' => ['label' => 'timed', 'value' => ['start' => $start, 'end' => $end]],
        ]]]];
    }

    /**
     * Evaluate a window at a frozen instant.
     *
     * @param mixed $start
     * @param mixed $end
     * @param int $now
     * @return array The status of condition_1.
     */
    private function status_at($start, $end, int $now): array {
        $this->mock_clock_with_frozen($now);
        $result = (new timed())->get_restriction_status(self::node($start, $end), (object) ['timecreated' => 0]);
        return $result['condition_1'];
    }

    /**
     * Every second around both boundaries, with the half-open semantics.
     *
     * @return void
     */
    public function test_boundaries_are_exact_and_half_open(): void {
        $this->resetAfterTest();
        $cases = [
            'start - 1 s' => [self::START - 1, false, true, false],
            'start' => [self::START, true, false, false],
            'start + 1 s' => [self::START + 1, true, false, false],
            'end - 1 s' => [self::END - 1, true, false, false],
            'end' => [self::END, false, false, true],
            'end + 1 s' => [self::END + 1, false, false, true],
        ];
        foreach ($cases as $label => [$now, $inside, $before, $after]) {
            $status = $this->status_at(self::START, self::END, $now);
            $this->assertSame($inside, $status['completed'], $label . ': inside');
            $this->assertSame($before, $status['isbefore'], $label . ': before');
            $this->assertSame($after, $status['isafter'], $label . ': after');
        }
    }

    /**
     * A legacy value is read with its seconds set to zero, whatever the second
     * of the clock - the defect #581 started from.
     *
     * @return void
     */
    public function test_legacy_value_does_not_depend_on_the_current_second(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC');
        foreach ([0, 1, 37, 59] as $second) {
            $this->mock_clock_with_frozen(self::END + $second - 60);
            $this->assertSame(self::START, time_value::to_timestamp('2030-01-15T10:00'), 'second ' . $second);
        }
        // And the window it describes behaves exactly like the timestamp one.
        foreach ([self::START - 1, self::START, self::END - 1, self::END] as $now) {
            $this->assertSame(
                $this->status_at(self::START, self::END, $now)['completed'],
                $this->status_at('2030-01-15T10:00', '2030-01-15T12:00', $now)['completed'],
                'legacy and timestamp windows must agree at ' . $now
            );
        }
    }

    /**
     * A stored timestamp means the same instant whatever the site's time zone.
     *
     * @return void
     */
    public function test_site_time_zone_cannot_shift_a_stored_window(): void {
        $this->resetAfterTest();
        foreach (['UTC', 'Europe/Berlin', 'America/New_York', 'Asia/Tokyo'] as $zone) {
            $this->setTimezone($zone);
            $this->assertTrue($this->status_at(self::START, self::END, self::START)['completed'], $zone . ': start');
            $this->assertFalse($this->status_at(self::START, self::END, self::END)['completed'], $zone . ': end');
        }
    }

    /**
     * Across the spring change in Europe/Berlin (2026-03-29, 02:00 -> 03:00),
     * a window of timestamps is unambiguous: one real hour stays one hour.
     *
     * @return void
     */
    public function test_daylight_saving_change_is_unambiguous(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Berlin');
        $start = 1774746000; // 2026-03-29 01:00 UTC = 03:00 CEST, just after the change.
        $end = $start + HOURSECS;
        $this->assertTrue($this->status_at($start, $end, $end - 1)['completed']);
        $this->assertFalse($this->status_at($start, $end, $end)['completed']);
    }

    /**
     * The helper reads every stored form and refuses what is not a time.
     *
     * @return void
     */
    public function test_to_timestamp_accepts_stored_forms_only(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC');
        $this->assertSame(self::START, time_value::to_timestamp(self::START));
        $this->assertSame(self::START, time_value::to_timestamp((string) self::START));
        $this->assertSame(self::START, time_value::to_timestamp('2030-01-15T10:00'));
        foreach ([null, '', false, 'not a date', '2030-02-30T10:00', '15.01.2030 10:00', []] as $invalid) {
            $this->assertNull(time_value::to_timestamp($invalid), var_export($invalid, true));
        }
    }

    /**
     * The upgrade rewrites legacy values once, and only once.
     *
     * @return void
     */
    public function test_migration_rewrites_legacy_values_idempotently(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC');
        $tree = ['nodes' => [
            self::node('2030-01-15T10:00', '2030-01-15T12:00'),
            self::node(self::START, null),
            ['id' => 'other', 'restriction' => ['nodes' => [[
                'id' => 'condition_1', 'data' => ['label' => 'parent_courses', 'value' => ['start' => 'x']],
            ]]]],
        ]];
        $this->assertSame(2, time_value::migrate_tree($tree));
        $this->assertSame(self::START, $tree['nodes'][0]['restriction']['nodes'][0]['data']['value']['start']);
        $this->assertSame(self::END, $tree['nodes'][0]['restriction']['nodes'][0]['data']['value']['end']);
        $this->assertSame(
            'x',
            $tree['nodes'][2]['restriction']['nodes'][0]['data']['value']['start'],
            'conditions of other kinds must be left alone'
        );
        $this->assertSame(0, time_value::migrate_tree($tree), 'a second run must change nothing');
    }

    /**
     * The feedback names the end of the window for BOTH kinds of time
     * condition.
     *
     * Regression guard: after #581 the end time was read as a timestamp, but
     * timed_duration still delivered "d.m.Y H:i" text - which is no time to
     * the reader, so the sentence "You have access ... until" disappeared for
     * relative windows. Both conditions now deliver timestamps.
     *
     * @covers \local_adele\relation_update::inbetweenfeedback
     * @covers \local_adele\course_restriction\conditions\timed_duration
     * @return void
     */
    public function test_feedback_names_the_end_for_both_kinds_of_window(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC');
        $this->mock_clock_with_frozen(self::START + 60);

        $duration = (new \local_adele\course_restriction\conditions\timed_duration())->get_restriction_status([
            'id' => 'dndnode_1',
            'data' => ['first_enrolled' => self::START],
            'restriction' => ['nodes' => [[
                'id' => 'condition_1',
                'data' => ['label' => 'timed_duration', 'value' => [
                    'selectedOption' => '1', 'durationValue' => '0', 'selectedDuration' => 1,
                ]],
            ]]],
        ], (object) ['timecreated' => 0]);
        $fixed = $this->status_at(self::START, self::END, self::START + 60);

        foreach (
            [
            'timed_duration' => [$duration['condition_1'], self::START + DAYSECS],
            'timed' => [$fixed, self::END],
            ] as $label => [$criterion, $expectedend]
        ) {
            $this->assertSame($expectedend, $criterion['inbetween_info']['endtime'], $label . ': end as timestamp');
            $feedback = [];
            relation_update::inbetweenfeedback(
                $feedback,
                [[$label . '_condition_1']],
                [$label => ['condition_1' => $criterion]],
                [],
                'inbetween'
            );
            $this->assertSame(
                get_string('node_restriction_inbetween_timed', 'local_adele', time_value::display($expectedend)),
                $feedback['restriction']['inbetween_timed'] ?? null,
                $label . ': the feedback must name the end of the window'
            );
        }
    }

    /**
     * Dates are shown in the time zone of the learner the path belongs to -
     * whoever happens to run the evaluation (cron, a teacher, an admin).
     *
     * Regression: display() handed the user id to core_date, which reads a
     * bare number as a forced time zone; the server's zone came out for
     * everybody, and the end of the window in the zone of the current user.
     */
    public function test_dates_are_shown_in_the_learners_time_zone(): void {
        $this->resetAfterTest();
        // The site, and the person running the evaluation: UTC.
        set_config('timezone', 'UTC');
        $this->setAdminUser();
        global $USER;
        $USER->timezone = 'UTC';
        $learner = $this->getDataGenerator()->create_user(['timezone' => 'Asia/Tokyo']);
        $format = get_string('strftimedatetimeshort', 'langconfig');
        $startintokyo = userdate(self::START, $format, 'Asia/Tokyo');
        $endintokyo = userdate(self::END, $format, 'Asia/Tokyo');
        $this->assertNotSame(userdate(self::START, $format, 'UTC'), $startintokyo, 'the two zones must differ for this test');

        $this->assertSame($startintokyo, time_value::display(self::START, (int) $learner->id));

        $this->mock_clock_with_frozen(self::START + 60);
        $status = (new timed())->get_restriction_status(
            self::node(self::START, self::END),
            (object) ['user_id' => $learner->id]
        );
        $this->assertSame($startintokyo, $status['condition_1']['placeholders']['start_date']);
        $this->assertSame($endintokyo, $status['condition_1']['placeholders']['end_date']);

        $feedback = [];
        relation_update::inbetweenfeedback(
            $feedback,
            [['timed_condition_1']],
            ['timed' => ['condition_1' => $status['condition_1']]],
            [],
            'inbetween',
            (int) $learner->id
        );
        $this->assertSame(
            get_string('node_restriction_inbetween_timed', 'local_adele', $endintokyo),
            $feedback['restriction']['inbetween_timed'] ?? null,
            'the end of the window, in the learner\'s zone'
        );
    }
}
