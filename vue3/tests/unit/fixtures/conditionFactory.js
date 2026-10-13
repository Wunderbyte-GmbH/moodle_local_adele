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
 * Conditions of the criteria editors: the condition node and its feedback
 * node, as the editor stores them.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { merge } from './merge';

/**
 * A condition node.
 *
 * @param {string} id e.g. 'condition_1'
 * @param {string} label e.g. 'parent_courses', 'timed', 'manual'
 * @param {object} overrides
 * @returns {object}
 */
export function makeCondition(id, label, overrides = {}) {
  return merge({
    id,
    type: 'custom',
    data: { label, node_id: id, visibility: true, value: {} },
    parentCondition: ['starting_condition'],
    childCondition: [`${id}_feedback`],
  }, overrides);
}

/**
 * The feedback node of a condition.
 *
 * @param {string} conditionid
 * @param {object} overrides
 * @returns {object}
 */
export function makeConditionFeedback(conditionid, overrides = {}) {
  return merge({
    id: `${conditionid}_feedback`,
    type: 'feedback',
    data: {
      childCondition: conditionid,
      visibility: true,
      feedback_before: '',
      feedback_before_checkmark: true,
    },
  }, overrides);
}
