import drawDropzone from '../../../../composables/nodesHelper/drawDropzone';

describe('drawDropzone', () => {
  let closestNode, store;

  beforeEach(() => {
    closestNode = {
      id: 'node1',
      position: { x: 100, y: 100 },
      computedPosition: { y: 150 },
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

  it('should handle nodes without parent/childCourse properly', () => {
    closestNode.parentCourse = [];
    closestNode.childCourse = [];

    const result = drawDropzone(closestNode, store);

    const parentDropZone = result.nodes.find(node => node.id === 'dropzone_parent');
    const childDropZone = result.nodes.find(node => node.id === 'dropzone_child');

    expect(parentDropZone.position).toEqual({ x: 100, y: -150 });
    expect(childDropZone.position).toEqual({ x: 100, y: 450 });
  });

  it('should offset x-position when parentCourse or childCourse is not empty', () => {
    closestNode.parentCourse = ['some_course'];
    closestNode.childCourse = ['some_course'];

    const result = drawDropzone(closestNode, store);

    const parentDropZone = result.nodes.find(node => node.id === 'dropzone_parent');
    const childDropZone = result.nodes.find(node => node.id === 'dropzone_child');

    // Offset x-position by 500 when courses are defined
    expect(parentDropZone.position.x).toBe(600); // 100 + 500
    expect(childDropZone.position.x).toBe(600);  // 100 + 500
  });



});
