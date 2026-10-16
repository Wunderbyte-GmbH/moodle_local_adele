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
 * A learning path as the store and the editor hold it.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { merge } from './merge';

/**
 * A tree node of the path.
 *
 * @param {string} id
 * @param {object} overrides e.g. { parentCourse: ['dndnode_1'] }
 * @returns {object}
 */
export function makeTreeNode(id, overrides = {}) {
  return merge({
    id,
    type: 'custom',
    parentCourse: ['starting_node'],
    childCourse: [],
    data: { node_id: id, course_node_id: [10] },
    restriction: { nodes: [], edges: [] },
    completion: { nodes: [], edges: [] },
  }, overrides);
}

/**
 * @param {object[]} nodes
 * @param {object} overrides
 * @returns {object}
 */
export function makeLearningPath(nodes = [], overrides = {}) {
  return merge({
    id: 1,
    name: 'Linear A1',
    description: '',
    visibility: 1,
    createdby: 2,
    json: { tree: { nodes, edges: [] } },
  }, overrides);
}
