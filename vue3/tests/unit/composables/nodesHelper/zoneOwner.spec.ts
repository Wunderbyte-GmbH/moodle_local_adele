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
 * zoneOwner.spec module (#580).
 *
 * @package    local_adele
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import zoneOwner, { SWITCH_MARGIN, centreDistance } from '../../../../composables/nodesHelper/zoneOwner';

describe('zoneOwner', () => {
  // Two nodes, one below the other, as in a chain; centres at y = 100 and 500.
  const first = { id: 'dndnode_1', position: { x: 0, y: 0 }, dimensions: { width: 400, height: 200 } };
  const second = { id: 'dndnode_2', position: { x: 0, y: 400 }, dimensions: { width: 400, height: 200 } };

  it('measures to the centre of a node', () => {
    expect(centreDistance({ x: 200, y: 100 }, first)).toBe(0);
    expect(centreDistance({ x: 200, y: 400 }, first)).toBe(300);
  });

  it('hands the zones to the node the pointer has moved to', () => {
    expect(zoneOwner({ x: 200, y: 480 }, first, second, false)).toBe(second);
  });

  it('keeps the zones while the pointer is on one of them', () => {
    // The successor zone of the first node lies beside the second node.
    expect(zoneOwner({ x: 700, y: 480 }, first, second, true)).toBe(first);
  });

  it('does not jump on the line between two nodes', () => {
    const halfway = { x: 200, y: 300 };
    expect(zoneOwner(halfway, first, second, false)).toBe(first);
    expect(zoneOwner(halfway, second, first, false)).toBe(second);
    const justOver = { x: 200, y: 300 + SWITCH_MARGIN / 2 + 1 };
    expect(zoneOwner(justOver, first, second, false)).toBe(second);
  });

  it('keeps the zones when no other node is in reach or the nearest is the same', () => {
    expect(zoneOwner({ x: 200, y: 480 }, first, null, false)).toBe(first);
    expect(zoneOwner({ x: 200, y: 480 }, first, { ...first }, false)).toBe(first);
  });
});
