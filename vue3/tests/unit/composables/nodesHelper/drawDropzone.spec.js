import drawDropzone from '../../../../composables/nodesHelper/drawDropzone';

describe('drawDropzone', () => {
  it('generates drop zones correctly based on the closest node', () => {
    // Mock closestNode object
    const closestNode = {
      id: 'node1',
      dimensions: { height: 100 },
      position: { x: 100, y: 100 },
      childCourse: '',
      parentCourse: '',
      computedPosition: { y: 150 }
    };

    // Call drawDropzone function
    const result = drawDropzone(closestNode);

    // Assertions
    expect(result.nodes.length).toBe(4);
    expect(result.edges.length).toBe(2); // Two edges will be added

    // Check drop zones' positions
    expect(result.nodes[0].position).toEqual({ x: 100, y: -150 }); // Parent drop zone
    expect(result.nodes[1].position).toEqual({ x: 100, y: 250 }); // Child drop zone
    expect(result.nodes[2].position).toEqual({ x: 550, y: 100 }); // And drop zone
    expect(result.nodes[3].position).toEqual({ x: -250, y: 100 }); // Or drop zone

    // Check drop zones' types
    expect(result.nodes[0].type).toBe('dropzone'); // Parent drop zone type
    expect(result.nodes[1].type).toBe('dropzone'); // Child drop zone type
    expect(result.nodes[2].type).toBe('conditionaldropzone'); // And drop zone type
    expect(result.nodes[3].type).toBe('conditionaldropzone'); // Or drop zone type

    // Check drop zones' data
    expect(result.nodes[0].data.infotext).toBe('Or drop zone'); // Parent drop zone info text
    expect(result.nodes[1].data.infotext).toBe('Or drop zone'); // Child drop zone info text
    expect(result.nodes[2].data.infotext).toBe('Or drop zone'); // And drop zone info text
    expect(result.nodes[3].data.infotext).toBe('Or drop zone'); // Or drop zone info text

    // Check drop zones' IDs
    expect(result.nodes[0].id).toBe('dropzone_parent'); // Parent drop zone ID
    expect(result.nodes[1].id).toBe('dropzone_child'); // Child drop zone ID
    expect(result.nodes[2].id).toBe('dropzone_and'); // And drop zone ID
    expect(result.nodes[3].id).toBe('dropzone_or'); // Or drop zone ID
  });
});

