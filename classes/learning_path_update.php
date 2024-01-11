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
use local_adele\helper\user_path_relation;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local adele.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_path_update {
    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function updated_learning_path($event) {
        // Get the user path relations.
        $manualconditions = [
            'manualcompletion',
            'manualcompletionvalue',
            'manualrestriction',
            'manualrestrictionvalue',
        ];
        $userpathrelation = new user_path_relation();
        $records = $userpathrelation->get_user_path_relations($event->other['learningpathid']);
        foreach ($records as $userpath) {
            $userpath->json = self::passnodevalues($event->other['json'], $userpath->json);
            $eventsingle = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
                ],
            ]);
            $eventsingle->trigger();
        }
    }

    /**
     * Observer for course completed
     *
     * @param string $newtree
     * @param string $oldtree
     * @return array
     */
    public static function passnodevalues($newtree, $oldtree) {
        $oldpath = json_decode($oldtree, true);
        $userpathjson = json_decode($newtree, true);
        // Check if new path has manual.
        $oldvalues = [];
        foreach ($oldpath['tree']['nodes'] as $node) {
            $oldvalues[$node['id']] = array(
                'manualcompletion' => $node['data']['manualcompletion'],
                'manualcompletionvalue' => $node['data']['manualcompletionvalue'],
                'manualrestriction' => $node['data']['manualrestriction'],
                'manualrestrictionvalue' => $node['data']['manualrestrictionvalue'],
            );
        }
        foreach ($userpathjson['tree']['nodes'] as &$node) {
            if ($oldvalues[$node['id']]) {
                $node['data'] = array_merge($node['data'], $oldvalues[$node['id']]);
            }
        }
        return $userpathjson;
    }
}
