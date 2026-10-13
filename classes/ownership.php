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
 * Learning-path ownership: manual transfer (#488) and automatic succession (#571).
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

/**
 * Owner (= learning_paths.createdby) transfer and succession.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ownership {
    /**
     * Whether a user counts as "vanished" for ownership purposes.
     *
     * Extension point (#571): succession currently triggers for DELETED (or
     * missing) accounts only. Further conditions - e.g. suspended accounts or
     * disabled auth - are a product decision; add them as additional checks
     * here and every consumer (event observer, daily sweep, tile flag) follows
     * automatically.
     *
     * @param \stdClass|false|null $user User record, or false/null when it does not exist.
     * @return bool
     */
    public static function is_vanished($user): bool {
        if (!$user) {
            return true;
        }
        if (!empty($user->deleted)) {
            return true;
        }
        return false;
    }

    /**
     * Hand a learning path to a new owner and make sure they are an editor.
     *
     * Capability checks live in the calling web service; this is the mechanic.
     *
     * @param int $lpid Learning path id.
     * @param int $newownerid New owner user id.
     * @return void
     */
    public static function set_owner(int $lpid, int $newownerid): void {
        global $DB;
        $DB->get_record('local_adele_learning_paths', ['id' => $lpid], 'id', MUST_EXIST);
        $DB->update_record('local_adele_learning_paths', (object) [
            'id' => $lpid,
            'createdby' => $newownerid,
            'timemodified' => \core\di::get(\core\clock::class)->time(),
        ]);
        if (!$DB->record_exists('local_adele_lp_editors', ['learningpathid' => $lpid, 'userid' => $newownerid])) {
            $DB->insert_record('local_adele_lp_editors', (object) [
                'learningpathid' => $lpid,
                'userid' => $newownerid,
                'timecreated' => \core\di::get(\core\clock::class)->time(),
                'timemodified' => \core\di::get(\core\clock::class)->time(),
            ]);
        }
        // Deliberately NO learnpath_updated event: that event means "the tree
        // changed" and its observer resyncs every subscriber snapshot (and
        // expects a 'json' payload the transfer does not carry - live crash on
        // paths with subscribers). Ownership is metadata; timemodified is the
        // audit trace.
    }

    /**
     * Put every owner on the list of editors of their path (decision
     * 2026-10-08: the owner always stands on that list).
     *
     * Paths whose owner was taken off the list before removing the owner was
     * refused could exist; this repairs them. Owners whose account is gone
     * are left to the successor rule (handle_vanished_owner()).
     *
     * @return int The number of owners put back.
     */
    public static function ensure_owners_are_editors(): int {
        global $DB;
        $missing = $DB->get_records_sql(
            "SELECT lp.id, lp.createdby
               FROM {local_adele_learning_paths} lp
               JOIN {user} u ON u.id = lp.createdby AND u.deleted = 0
          LEFT JOIN {local_adele_lp_editors} e ON e.learningpathid = lp.id AND e.userid = lp.createdby
              WHERE e.id IS NULL"
        );
        $now = \core\di::get(\core\clock::class)->time();
        foreach ($missing as $path) {
            $DB->insert_record('local_adele_lp_editors', (object) [
                'learningpathid' => (int) $path->id,
                'userid' => (int) $path->createdby,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        return count($missing);
    }

    /**
     * The most senior remaining editor of a path: lowest user id
     * (Senioritätsprinzip, #571), skipping vanished accounts.
     *
     * @param int $lpid Learning path id.
     * @param int $excludeuserid The vanished owner to skip.
     * @return int|null Successor user id, or null when nobody is left.
     */
    public static function find_successor(int $lpid, int $excludeuserid): ?int {
        global $DB;
        $sql = "SELECT u.id
                  FROM {local_adele_lp_editors} e
                  JOIN {user} u ON u.id = e.userid
                 WHERE e.learningpathid = :lpid
                       AND u.id <> :exclude
                       AND u.deleted = 0
              ORDER BY u.id ASC";
        $successor = $DB->get_records_sql($sql, ['lpid' => $lpid, 'exclude' => $excludeuserid], 0, 1);
        return $successor ? (int) reset($successor)->id : null;
    }

    /**
     * Try to pass one orphaned path to its successor.
     *
     * Without a successor the path keeps its createdby and shows up as
     * "ownerless" in the tile listing (via add_path_people), where Admin /
     * Adele Manager see the warning - deliberately no silent admin takeover.
     *
     * @param int $lpid Learning path id.
     * @param int $vanishedownerid The vanished owner.
     * @return bool Whether a successor took over.
     */
    public static function handle_vanished_owner(int $lpid, int $vanishedownerid): bool {
        $successor = self::find_successor($lpid, $vanishedownerid);
        if ($successor === null) {
            return false;
        }
        self::set_owner($lpid, $successor);
        return true;
    }

    /**
     * Event-driven half (#571): a user was deleted - pass on their paths and
     * drop their editor memberships.
     *
     * @param int $userid The deleted user.
     * @return void
     */
    public static function handle_user_deleted(int $userid): void {
        global $DB;
        foreach ($DB->get_records('local_adele_learning_paths', ['createdby' => $userid], '', 'id') as $lp) {
            self::handle_vanished_owner((int) $lp->id, $userid);
        }
        // A deleted account must not linger in editor lists (and must never be
        // picked as a successor of some OTHER vanished owner later).
        $DB->delete_records('local_adele_lp_editors', ['userid' => $userid]);
    }

    /**
     * Safety-net half (#571): sweep every path whose owner vanished without the
     * event having been processed (external user sync, missed event).
     *
     * @return array ['reassigned' => int, 'ownerless' => int]
     */
    public static function sweep(): array {
        global $DB;
        $sql = "SELECT lp.id, lp.createdby
                  FROM {local_adele_learning_paths} lp
             LEFT JOIN {user} u ON u.id = lp.createdby
                 WHERE u.id IS NULL OR u.deleted = 1";
        $reassigned = 0;
        $ownerless = 0;
        foreach ($DB->get_records_sql($sql) as $lp) {
            if (self::handle_vanished_owner((int) $lp->id, (int) $lp->createdby)) {
                $reassigned++;
            } else {
                $ownerless++;
            }
        }
        return ['reassigned' => $reassigned, 'ownerless' => $ownerless];
    }
}
