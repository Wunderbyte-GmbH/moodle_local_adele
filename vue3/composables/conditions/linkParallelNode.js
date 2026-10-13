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
 * Link a node placed in parallel (zone "Alternative node") into the
 * relations of its neighbours.
 *
 * The parallel node shares the predecessors and successors of the node it was
 * dropped next to. Its own lists say so; the neighbours' lists did not: each
 * successor kept its old parentCourse until the path was saved and reloaded,
 * so everything that reads parentCourse in between - the predecessor
 * condition's "n of m" in particular - saw one predecessor too few (found
 * with chain E2).
 *
 * @param {Function} findNode Looks a node up by id.
 * @param {string} id The parallel node.
 * @param {string[]} parents Its predecessors (may contain 'starting_node').
 * @param {string[]} children Its successors.
 * @package    local_adele
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const linkParallelNode = (findNode, id, parents, children) => {
  (children || []).forEach((childid) => {
    const child = findNode(childid)
    if (child) {
      child.parentCourse = [...(child.parentCourse || []).filter((p) => p !== id), id]
    }
  });
  (parents || []).filter((parentid) => parentid !== 'starting_node').forEach((parentid) => {
    const parent = findNode(parentid)
    if (parent) {
      parent.childCourse = [...(parent.childCourse || []).filter((c) => c !== id), id]
    }
  });
}

export default linkParallelNode;
