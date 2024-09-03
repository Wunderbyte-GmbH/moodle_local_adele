import shiftNodesDown from '../../../composables/shiftNodesDown';

describe('shiftNodesDown', () => {
  let nodes;

  beforeEach(() => {
      nodes = [
          {
              id: 'node1',
              type: 'custom',
              parentCourse: 'newNode',
              childCourse: ['node2'],
              position: { x: 100, y: 100 },
              dimensions: { height: 100 },
          },
          {
              id: 'node2',
              type: 'custom',
              parentCourse: 'node1',
              childCourse: ['node3', 'node4'],
              position: { x: 200, y: 200 },
              dimensions: { height: 100 },
          },
          {
              id: 'node3',
              type: 'custom',
              parentCourse: 'node2',
              childCourse: [],
              position: { x: 300, y: 300 },
              dimensions: { height: 100 },
          },
          {
              id: 'node4',
              type: 'custom',
              parentCourse: 'node2',
              childCourse: [],
              position: { x: 400, y: 400 },
              dimensions: { height: 100 },
          },
          {
            id: 'node5',
            type: 'custom',
            parentCourse: '',
            childCourse: [],
            position: { x: 600, y: 400 },
            dimensions: { height: 100 },
        },
      ];
  });

  it('should shift nodes down by the correct amount without an extra image shift', () => {
    const newNodeData = { node_id: 'newNode', selected_course_image: false };
    shiftNodesDown(newNodeData, nodes);
    expect(nodes[0].position.y).toBe(450);
    expect(nodes[1].position.y).toBe(600);
    expect(nodes[2].position.y).toBe(750);
    expect(nodes[3].position.y).toBe(750);
    expect(nodes[4].position.y).toBe(400);
  });

  it('should shift nodes down by the correct amount with an extra image shift', () => {
    const newNodeData = { node_id: 'newNode', selected_course_image: true };
    shiftNodesDown(newNodeData, nodes);
    expect(nodes[0].position.y).toBe(750);
    expect(nodes[1].position.y).toBe(750);
    expect(nodes[2].position.y).toBe(900);
    expect(nodes[3].position.y).toBe(1050);
    expect(nodes[4].position.y).toBe(400);
  });


});