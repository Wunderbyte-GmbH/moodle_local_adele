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
 * This class contains a list of webservice functions related to the adele Module by Wunderbyte.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_adele;

use local_adele\event\user_path_updated;
use context_system;

/**
 * External Service for local adele.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment {
    /**
     * Webservice for the local adele plugin to get next question.
     *
     * @param object $event
     */
    public static function enrolled($event) {
        // An enrolment ADELE created itself - into a node course or a host
        // course of some learning path - is not taking part in a course.
        // Counting it would carry the person into every other path embedded
        // there, and from there on through shared courses (mod_adele #37).
        if ((($event->other ?? [])['enrol'] ?? '') === 'adele') {
            return;
        }
        $params = $event;
        $learningpaths = self::buildsqlquerypath($params->courseid);
        if ($learningpaths) {
            foreach ($learningpaths as $learningpath) {
                self::subscribe_user_to_learning_path($learningpath, $params, $params->courseid);
            }
        }
    }

    /**
     * Subscribe a user to a learning path (idempotent, race-safe).
     *
     * The identity of a user path is learning path + user (specification 2.1
     * of the enrol_adele project); the host course is NOT part of it. $courseid
     * is kept as an optional provenance hint only: it records which course
     * triggered the first subscription and carries no further meaning. Callers
     * should omit it.
     *
     * @param object $learningpath
     * @param object $params
     * @param int|null $courseid Optional provenance only, no identity.
     */
    public static function subscribe_user_to_learning_path($learningpath, $params, $courseid = null) {
        global $DB;
        if ($learningpath) {
            if (is_string($learningpath->json)) {
                $learningpath->json = json_decode($learningpath->json, true);
            }
            $userpath = self::buildsqlqueryuserpath($learningpath->id, $params->relateduserid);
            if (!$userpath) {
                $newrecord = [
                    'user_id' => $params->relateduserid,
                    'course_id' => (int) ($courseid ?? 0),
                    'learning_path_id' => $learningpath->id,
                    'status' => 'active',
                    'timecreated' => \core\di::get(\core\clock::class)->time(),
                    'timemodified' => \core\di::get(\core\clock::class)->time(),
                    'createdby' => $params->userid,
                    'json' => json_encode([
                        // An empty learning path has no 'tree' yet; default to an empty
                        // tree so adding it to a course does not raise "Undefined array
                        // key tree" (GitHub #448). Downstream recompute already guards
                        // against empty nodes.
                        'tree' => $learningpath->json['tree'] ?? ['nodes' => [], 'edges' => []],
                        'modules' => $learningpath->json['modules'] ?? null,
                    ]),
                ];
                try {
                    $id = $DB->insert_record('local_adele_path_user', $newrecord);
                    $userpath = $DB->get_record('local_adele_path_user', ['id' => $id]);
                } catch (\dml_exception $e) {
                    // A concurrent request won the race and inserted
                    // the row first; the unique index (user_id, learning_path_id)
                    // rejected this insert. Reuse the row that now exists instead
                    // of failing or creating a duplicate. If no such row is found
                    // the exception was not a duplicate conflict and must propagate.
                    $userpath = self::buildsqlqueryuserpath($learningpath->id, $params->relateduserid);
                    if (!$userpath) {
                        throw $e;
                    }
                }
            }
            $userpath->json = json_decode($userpath->json, true);
            $eventsingle = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
                    'course_id' => $courseid,
                ],
            ]);
            $eventsingle->trigger();
        }
    }

    /**
     * Build sql query with config filters.
     *
     * @param string $courseid
     * @return array
     */
    public static function buildsqlquerypath($courseid) {
        global $DB;
        // Only subscribe a user to a learning path when they are enrolled into the
        // course that HOSTS the learning path as a mod_adele activity (the "home
        // course"). This prevents spurious user-path records — and the resulting
        // cascade of auto-enrolments into unrelated starting-node courses — that
        // happened when the old code matched any course referenced anywhere in the
        // LP JSON (GitHub issue #444; aligns with the origin/dev_chris fix for the
        // related #433 "duplicate user learningpathes"). local_adele declares a
        // dependency on mod_adele, so joining {adele} is safe.
        // DISTINCT is required: a learning path embedded via more than one
        // mod_adele activity in the SAME host course makes this JOIN return
        // the same lp.id more than once. get_records_sql() uses the first
        // selected column as the array key, so a duplicate lp.id triggers
        // "Duplicate value found in column 'id'" - and the caller
        // (enrolled(), below) would have called subscribe_user_to_learning_path()
        // twice for the same path, redundant even though harmless
        // (idempotent per that method's own docblock). Pre-existing bug,
        // not introduced this session — surfaced by that test.
        $sql = "SELECT DISTINCT lp.id, lp.json
        FROM {local_adele_learning_paths} lp
        JOIN {adele} a ON a.learningpathid = lp.id
        WHERE a.course = :courseid";

        $params = ['courseid' => (int) $courseid];

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Find the active user path for a learning path + user pair.
     *
     * course_id is deliberately NOT part of the lookup any more: a learning
     * path is a system-wide entity, and binding the user path to a host course
     * produced duplicate paths when the same learning path was embedded in
     * several courses (enrol_adele project specification 2.1). The third
     * parameter is accepted and ignored for callers that still pass it.
     *
     * @param int $learningpathid
     * @param int $userid
     * @param int|null $courseid Ignored, kept for backwards compatibility.
     * @return object|false
     */
    public static function buildsqlqueryuserpath($learningpathid, $userid, $courseid = null) {
        global $DB;
        $sql = "SELECT *
        FROM {local_adele_path_user} lpu
        WHERE lpu.learning_path_id = :learningpathid
        AND lpu.status = 'active'
        AND lpu.user_id = :userid
        ORDER BY lpu.id DESC";

        // Providing the named parameter in the $params array.
        $params = [
            'learningpathid' => (int)$learningpathid,
            'userid' => (int)$userid,
        ];

        // Using get_records_sql function to execute the query with parameters.
        $record = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);

        return $record;
    }

    /**
     * Grant the site-wide assistant role when a user is assigned the
     * configured trigger role.
     *
     * The enrollassistant setting names a role whose assignment (anywhere)
     * should also grant the user the system-level adeleassistant role. Does
     * nothing when the setting is empty or set to "no role" ('0'), or when the
     * assigned role is not the trigger role.
     *
     * @param object $event The role_assigned event.
     */
    public static function assign_assistant_to_role($event) {
        global $DB;
        $configrole = get_config('local_adele', 'enrollassistant');
        if ($configrole === false || $configrole === '' || $configrole === '0') {
            return;
        }
        if ((string) $event->objectid !== (string) $configrole) {
            return;
        }
        $systemrole = $DB->get_record('role', ['shortname' => 'adeleassistant']);
        if (!$systemrole) {
            return;
        }
        $systemcontext = context_system::instance();
        role_assign($systemrole->id, $event->relateduserid, $systemcontext->id);
    }
}
