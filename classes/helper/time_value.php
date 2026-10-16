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

namespace local_adele\helper;

/**
 * Time values of the "timed" access condition (issue #581).
 *
 * A window is stored as two Unix timestamps and compared as integers. Earlier
 * versions stored the wall-clock string of an <input type="datetime-local">
 * ("2026-12-01T10:00"), which has no time zone and was parsed with
 * createFromFormat() - filling the missing seconds from the CURRENT second, so
 * the outcome at a boundary depended on when the check happened to run.
 *
 * The window is half-open: open from the first second of its start, closed
 * from the first second of its end.
 *
 *     start <= now < end
 *
 * That is the behaviour the old code showed in practice, so existing paths keep
 * their meaning; it is now a decision written down rather than a side effect.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class time_value {
    /** @var string The format of the values stored before #581. */
    const LEGACY_FORMAT = 'Y-m-d\TH:i';

    /**
     * Turn a stored boundary into a Unix timestamp.
     *
     * Accepts the current form (int, or its string representation as it
     * arrives through JSON) and the legacy wall-clock string. A legacy string
     * carries no time zone; it is read in the site's time zone, which is what
     * the evaluation did before, so its meaning is preserved.
     *
     * @param mixed $value The stored value.
     * @return int|null The timestamp, or null when there is no usable value.
     */
    public static function to_timestamp($value): ?int {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (!is_string($value)) {
            return null;
        }
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }
        // The leading "!" resets every field the format does not name -
        // seconds included - to zero instead of filling it from the current
        // time. That is the whole of the boundary defect in #581.
        $parsed = \DateTimeImmutable::createFromFormat(
            '!' . self::LEGACY_FORMAT,
            $value,
            \core_date::get_server_timezone_object()
        );
        if ($parsed === false || $parsed->format(self::LEGACY_FORMAT) !== $value) {
            return null;
        }
        return $parsed->getTimestamp();
    }

    /**
     * Where "now" lies relative to a window.
     *
     * @param int|null $start Start timestamp, null when not set.
     * @param int|null $end End timestamp, null when not set.
     * @param int $now The current time.
     * @return array{isbefore: bool, inside: bool, isafter: bool}
     */
    public static function window_state(?int $start, ?int $end, int $now): array {
        $startreached = $start === null || $start <= $now;
        $endreached = $end !== null && $end <= $now;
        return [
            'isbefore' => !$startreached,
            'inside' => $startreached && !$endreached && ($start !== null || $end !== null),
            'isafter' => $endreached,
        ];
    }

    /**
     * A boundary for display, in the time zone of the person concerned.
     *
     * @param int $timestamp The timestamp.
     * @param int|null $userid The person whose time zone applies; null for the current user.
     * @return string
     */
    public static function display(int $timestamp, ?int $userid = null): string {
        // The record, not the id: core_date::get_user_timezone() reads a bare
        // number as a forced time zone - and an id is no valid zone, so the
        // server's zone came out for everybody.
        $timezone = 99;
        if ($userid) {
            $user = \core_user::get_user($userid, 'id, timezone');
            if ($user) {
                $timezone = \core_date::get_user_timezone($user);
            }
        }
        return userdate($timestamp, get_string('strftimedatetimeshort', 'langconfig'), $timezone);
    }

    /**
     * Rewrite the boundaries of every timed condition in a learning path tree
     * to timestamps.
     *
     * Used by the upgrade step and safe to run more than once: values that are
     * already timestamps are left alone.
     *
     * @param array $tree The decoded tree of a learning path (by reference).
     * @return int The number of values rewritten.
     */
    public static function migrate_tree(array &$tree): int {
        $changed = 0;
        foreach ($tree['nodes'] ?? [] as $n => $node) {
            foreach ($node['restriction']['nodes'] ?? [] as $c => $condition) {
                if (($condition['data']['label'] ?? '') !== 'timed') {
                    continue;
                }
                foreach (['start', 'end'] as $slot) {
                    $value = $condition['data']['value'][$slot] ?? null;
                    if (!is_string($value) || $value === '' || preg_match('/^-?\d+$/', $value)) {
                        continue;
                    }
                    $timestamp = self::to_timestamp($value);
                    if ($timestamp === null) {
                        continue;
                    }
                    $tree['nodes'][$n]['restriction']['nodes'][$c]['data']['value'][$slot] = $timestamp;
                    $changed++;
                }
            }
        }
        return $changed;
    }
}
