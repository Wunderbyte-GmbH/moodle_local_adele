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
 * draw Dropzone.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import drawDropzone from '../../../../composables/nodesHelper/drawDropzone';

interface StoreState {
  strings: {
    composables_drop_zone_parent: string;
    composables_drop_zone_child: string;
    composables_drop_zone_add: string;
    composables_drop_zone_or: string;
  };
}

interface Store {
  state: StoreState;
}

interface Position {
  x: number;
  y: number;
}

interface ClosestNode {
  id: string;
  position: Position;
  computedPosition: Position;
  dimensions: { height: number };
  parentCourse?: string[];
  childCourse?: string[];
}

describe('drawDropzone', () => {
  let closestNode: ClosestNode, store: Store;

  beforeEach(() => {
    closestNode = {
      id: 'node1',
      position: { x: 100, y: 100 },
      computedPosition: { x: 100, y: 150 },
      dimensions: { height: 300 },
      parentCourse: ['starting_node'],
      childCourse: [],
    };

    store = {
      state: {
        strings: {
          composables_drop_zone_parent: 'Drop Zone Parent',
          composables_drop_zone_child: 'Drop Zone Child',
          composables_drop_zone_add: 'Drop Zone Add',
          composables_drop_zone_or: 'Drop Zone Or',
        },
      },
    };
  });

  it('should handle nodes without parent/childCourse properly', async () => {
    closestNode.parentCourse = [];
    closestNode.childCourse = [];

    const result = await drawDropzone(closestNode, store);

    const parentDropZone = result.nodes.find(node => node.id === 'dropzone_parent');
    const childDropZone = result.nodes.find(node => node.id === 'dropzone_child');

    expect(parentDropZone).toBeDefined();
    expect(childDropZone).toBeDefined();

    if (parentDropZone) {
      expect(parentDropZone.position).toEqual({ x: 100, y: -150 });
    }

    if (childDropZone) {
      expect(childDropZone.position).toEqual({ x: 100, y: 450 });
    }
  });

  // #595: a second successor / predecessor is not offered. The zones for it
  // used to be drawn 500 units to the side, where they could not be hit.
  const ids = (result: { nodes: { id: string }[] }): string[] => result.nodes.map(node => node.id).sort();

  it('offers no successor zone at a node that has a successor', async () => {
    closestNode.parentCourse = ['starting_node'];
    closestNode.childCourse = ['dndnode_2'];
    const result = await drawDropzone(closestNode, store);
    expect(ids(result)).toEqual(['dropzone_and', 'dropzone_or', 'dropzone_parent']);
    expect(result.edges.map(edge => edge.id)).toEqual(['node1-parent']);
  });

  it('offers no predecessor zone at a node that has a predecessor', async () => {
    closestNode.parentCourse = ['dndnode_1'];
    closestNode.childCourse = [];
    const result = await drawDropzone(closestNode, store);
    expect(ids(result)).toEqual(['dropzone_and', 'dropzone_child', 'dropzone_or']);
  });

  it('offers only the side zones in the middle of a chain', async () => {
    closestNode.parentCourse = ['dndnode_1'];
    closestNode.childCourse = ['dndnode_3'];
    const result = await drawDropzone(closestNode, store);
    expect(ids(result)).toEqual(['dropzone_and', 'dropzone_or']);
    expect(result.edges).toEqual([]);
  });

  it('offers all four at a single node, none of them to the side', async () => {
    const result = await drawDropzone(closestNode, store);
    expect(ids(result)).toEqual(['dropzone_and', 'dropzone_child', 'dropzone_or', 'dropzone_parent']);
    expect(result.nodes.find(node => node.id === 'dropzone_parent')!.position.x).toBe(100);
    expect(result.nodes.find(node => node.id === 'dropzone_child')!.position.x).toBe(100);
  });

  it('treats a node without the lists like one with empty lists', async () => {
    delete closestNode.parentCourse;
    delete closestNode.childCourse;
    const result = await drawDropzone(closestNode, store);
    expect(ids(result)).toEqual(['dropzone_and', 'dropzone_child', 'dropzone_or', 'dropzone_parent']);
  });

});
