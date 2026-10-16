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
 * Which node the drop zones of a running drag belong to (#580).
 *
 * The zones are drawn around ONE node. They used to stay at the node the
 * pointer came near first, until the pointer crossed the starting marker or
 * moved far away - whoever dragged past one node to another was offered the
 * zones at the wrong one. Now they follow the pointer: they belong to the
 * node whose centre is nearest.
 *
 * Two exceptions keep them usable:
 * - While the pointer is ON one of the drawn zones, they stay. A zone lies
 *   beside its node and can be nearer to a neighbouring node than to its
 *   own; without this, a zone could vanish under the pointer aiming at it.
 * - Another node takes over only when it is nearer by a margin, so the
 *   zones do not jump back and forth on the line between two nodes.
 *
 * @package    local_adele
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface Point {
  x: number;
  y: number;
}

interface FlowNode {
  id: string;
  position: Point;
  dimensions: { width: number; height: number };
}

/** By how much (canvas units) another node must be nearer to take over. */
export const SWITCH_MARGIN = 30;

/**
 * The distance between a point and the centre of a node.
 *
 * @param position The point, in canvas coordinates.
 * @param node The node.
 */
export const centreDistance = (position: Point, node: FlowNode): number =>
  Math.hypot(
    position.x - (node.position.x + node.dimensions.width / 2),
    position.y - (node.position.y + node.dimensions.height / 2)
  );

/**
 * Decide which node owns the zones from now on.
 *
 * @param position The pointer, in canvas coordinates.
 * @param current The node the zones are drawn around.
 * @param nearest The node nearest to the pointer, if any is in reach.
 * @param pointerOnZone Whether the pointer is on one of the drawn zones.
 * @returns current or nearest.
 */
const zoneOwner = <T extends FlowNode>(
  position: Point, current: T, nearest: T | null, pointerOnZone: boolean
): T => {
  if (!nearest || nearest.id === current.id || pointerOnZone) {
    return current;
  }
  return centreDistance(position, nearest) + SWITCH_MARGIN < centreDistance(position, current) ? nearest : current;
};

export default zoneOwner;
