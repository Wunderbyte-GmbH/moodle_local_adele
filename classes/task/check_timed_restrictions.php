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

/**
 * Scheduled task that re-evaluates timed learning-path restrictions.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\task;

use local_adele\learning_path_update;

/**
 * Re-evaluate timed ("Node start/end date") restrictions so a node unlocks (or
 * closes) at its scheduled time even when the learner never opens the course.
 *
 * Timed status is a pure function of the clock, but the stored per-user snapshot
 * is only recomputed on events (subscribe, completion, viewing the course...).
 * Without this sweep a node whose window opened would keep showing "locked" in
 * the teacher overview until the learner happened to trigger a recompute
 * (GitHub #438).
 *
 * Design notes (kept deliberately stateless and idempotent):
 *  - A path is recomputed ONLY when a timed start/end date falls in the window
 *    (timemodified, now] — i.e. a boundary was crossed since the path was last
 *    computed.  Future boundaries wait; boundaries already accounted for (the
 *    snapshot's timemodified is newer) are skipped.  So there is no needless
 *    recompute and, because every recompute bumps timemodified, no path can be
 *    recomputed twice for the same boundary.
 *  - The actual status is decided by the normal recompute path
 *    (trigger_user_path_update -> updated_single -> timed condition), so this
 *    task never re-implements the timed logic and can never diverge from it.
 *  - Every path is processed in its own try/catch: one corrupt record can never
 *    abort the sweep.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_timed_restrictions extends \core\task\scheduled_task {

    /**
     * Name shown on the Site administration > Scheduled tasks page.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_check_timed_restrictions', 'local_adele');
    }

    /**
     * Sweep active user paths and recompute those whose timed window just opened
     * or closed.
     */
    public function execute() {
        global $DB;

        $now = time();

        // Pre-filter in SQL to active paths whose JSON mentions a timed restriction.
        // Both 'timed' and 'timed_duration' contain the substring "timed", so this
        // can never miss one; at worst it returns a few extra rows that
        // has_due_timed_boundary() then discards.
        $select = "status = :active AND " . $DB->sql_like('json', ':pattern');
        $params = ['active' => 'active', 'pattern' => '%timed%'];

        $checked = 0;
        $recomputed = 0;

        $rs = $DB->get_recordset_select(
            'local_adele_path_user',
            $select,
            $params,
            '',
            'id, timemodified, timecreated, json'
        );
        try {
            foreach ($rs as $rec) {
                $checked++;
                try {
                    $json = json_decode($rec->json, true);
                    if (!is_array($json)
                        || !self::has_due_timed_boundary(
                            $json, (int)$rec->timemodified, (int)$rec->timecreated, $now)) {
                        continue;
                    }

                    // Re-fetch the live row so we recompute the freshest data
                    // (and confirm it is still active).
                    $userpath = $DB->get_record(
                        'local_adele_path_user',
                        ['id' => $rec->id, 'status' => 'active']
                    );
                    if (!$userpath) {
                        continue;
                    }
                    $userpath->json = json_decode($userpath->json, true);
                    if (!is_array($userpath->json)) {
                        continue;
                    }

                    // Standard recompute path: fires user_path_updated -> updated_single,
                    // which re-evaluates every node (incl. the timed one) and persists.
                    learning_path_update::trigger_user_path_update($userpath);
                    $recomputed++;
                } catch (\Throwable $e) {
                    // Never let a single bad path abort the whole sweep.
                    mtrace('local_adele check_timed_restrictions: skipped path '
                        . $rec->id . ': ' . $e->getMessage());
                }
            }
        } finally {
            $rs->close();
        }

        mtrace('local_adele check_timed_restrictions: checked ' . $checked
            . ' path(s), recomputed ' . $recomputed . '.');
    }

    /**
     * True if any timed restriction in the path has a boundary that fell in the
     * half-open window (timemodified, now] — i.e. it crossed since the path was
     * last recomputed.
     *
     * Covers BOTH time-based restrictions:
     *  - 'timed'           — a fixed start/end date window.
     *  - 'timed_duration'  — "X days/weeks/months after enrolment / path creation";
     *                        the boundary that needs a proactive recompute is the
     *                        moment the window closes (start_ref + duration).
     *
     * Pure and side-effect free so it can be unit-tested exhaustively.
     *
     * @param array $json The decoded path_user JSON.
     * @param int $timemodified Unix time the snapshot was last recomputed.
     * @param int $timecreated Unix time the path was created (start ref for some
     *                         timed_duration options).
     * @param int $now Current Unix time.
     * @return bool
     */
    public static function has_due_timed_boundary(array $json, int $timemodified, int $timecreated, int $now): bool {
        $nodes = $json['tree']['nodes'] ?? [];
        if (!is_array($nodes)) {
            return false;
        }
        foreach ($nodes as $node) {
            $restrictionnodes = $node['restriction']['nodes'] ?? [];
            if (!is_array($restrictionnodes)) {
                continue;
            }
            foreach ($restrictionnodes as $restrictionnode) {
                $label = $restrictionnode['data']['label'] ?? '';
                if ($label === 'timed') {
                    // Fixed start/end dates stored as datetime-local strings.
                    foreach (['start', 'end'] as $slot) {
                        $date = $restrictionnode['data']['value'][$slot] ?? '';
                        if ($date === '' || $date === null) {
                            continue;
                        }
                        $timestamp = strtotime((string)$date);
                        if ($timestamp !== false && self::in_window($timestamp, $timemodified, $now)) {
                            return true;
                        }
                    }
                } else if ($label === 'timed_duration') {
                    $close = self::timed_duration_close_time($restrictionnode, $node, $timecreated);
                    if ($close !== null && self::in_window($close, $timemodified, $now)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Whether a boundary timestamp falls in the half-open window (timemodified, now].
     *
     * @param int $timestamp
     * @param int $timemodified
     * @param int $now
     * @return bool
     */
    private static function in_window(int $timestamp, int $timemodified, int $now): bool {
        return $timestamp > $timemodified && $timestamp <= $now;
    }

    /**
     * The Unix time a 'timed_duration' window closes (start reference + duration),
     * or null when it is not yet computable (e.g. option 1 before enrolment, or an
     * invalid/missing duration).
     *
     * Mirrors timed_duration::get_restriction_status and reuses its DURATION_SECONDS
     * table as the single source of truth, so the two cannot diverge. Only the close
     * boundary is relevant to the sweep: the window opens at enrolment / path
     * creation, which is itself a recompute event, so the open never needs a sweep.
     *
     * @param array $restrictionnode The timed_duration restriction node.
     * @param array $node The owning tree node (for first_enrolled).
     * @param int $timecreated The path creation time (start ref for options != '1').
     * @return int|null
     */
    private static function timed_duration_close_time(array $restrictionnode, array $node, int $timecreated): ?int {
        $value = $restrictionnode['data']['value'] ?? [];
        if (!isset($value['selectedOption'])) {
            return null;
        }
        $durationvalue = $value['durationValue'] ?? null;
        $selectedduration = $value['selectedDuration'] ?? null;
        $map = \local_adele\course_restriction\conditions\timed_duration::DURATION_SECONDS;
        if (!isset($map[$durationvalue]) || !is_numeric($selectedduration)) {
            return null;
        }
        if ((string)$value['selectedOption'] === '1') {
            // Window starts when the learner is first enrolled into the node.
            $startref = $node['data']['first_enrolled'] ?? null;
            if (!is_numeric($startref)) {
                return null;
            }
            $startref = (int)$startref;
        } else {
            // Window starts at path creation.
            $startref = $timecreated;
        }
        return $startref + (int)($map[$durationvalue] * $selectedduration);
    }
}
