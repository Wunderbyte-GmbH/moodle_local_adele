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
 * The data object of a node as the components receive it (props.data).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { merge } from './merge';
import { makeFeedback } from './feedbackFactory';

/**
 * @param {object} overrides
 * @returns {object}
 */
export function makeNode(overrides = {}) {
  const { feedback, ...rest } = overrides;
  return merge({
    node_id: 'dndnode_1',
    fullname: 'Testkurs 01',
    course_node_id: [10],
    progress: 0,
    manualrestriction: false,
    manualrestrictionvalue: false,
    manualcompletion: false,
    manualcompletionvalue: false,
    animations: { seenrestriction: true, seencompletion: true },
    completion: {
      feedback: makeFeedback(feedback || {}),
      master: { completion: false, restriction: false },
      restrictioncriteria: {},
      singlerestrictionnode: [],
    },
  }, rest);
}
