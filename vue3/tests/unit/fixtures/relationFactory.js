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
 * A row of the participant list: one learner's relation to the path, as
 * get_lp_user_path_relations delivers it.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { merge } from './merge';

/**
 * @param {object} overrides
 * @returns {object}
 */
export function makeRelation(overrides = {}) {
  return merge({
    id: 101,
    firstname: 'Ada',
    lastname: 'Alpha',
    rank: 1,
    progress: {
      progress: 0,
      completed_nodes: 0,
      route_completed: 0,
      route_total: 3,
    },
  }, overrides);
}
