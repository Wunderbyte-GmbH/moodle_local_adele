import shiftNodesDown from '../../../composables/shiftNodesDown';

interface Node {
  id: string;
  node_id: string;
  type: string,
  parentCourse: string[];
  childCourse: string[];
  selected_course_image?: string;
  dimensions: Dimensions;
  position: Position;
}

interface Dimensions {
  width: number;
  height: number;
}

interface Position {
  x: number;
  y: number;
}


describe('shiftNodesDown', () => {
  let nodes: Node[];

  beforeEach(() => {
      nodes = [
          {
              id: 'node1',
              type: 'custom',
              parentCourse: ['newNode'],
              childCourse: ['node2'],
              node_id: 'dndnode1',
              selected_course_image: 'imagepath',
              position: { x: 100, y: 100 },
              dimensions: { width: 100, height: 100 },
          },
          {
              id: 'node2',
              type: 'custom',
              parentCourse: ['node1'],
              childCourse: ['node3', 'node4'],
              node_id: 'dndnode2',
              selected_course_image: 'imagepath',
              position: { x: 200, y: 200 },
              dimensions: { width: 100, height: 100 },
          },
          {
              id: 'node3',
              type: 'custom',
              parentCourse: ['node2'],
              childCourse: [],
              node_id: 'dndnode3',
              selected_course_image: 'imagepath',
              position: { x: 300, y: 300 },
              dimensions: { width: 100, height: 100 },
          },
          {
              id: 'node4',
              type: 'custom',
              parentCourse: ['node2'],
              childCourse: [],
              node_id: 'dndnode4',
              selected_course_image: 'imagepath',
              position: { x: 400, y: 400 },
              dimensions: { width: 100, height: 100 },
          },
          {
            id: 'node5',
            type: 'custom',
            parentCourse: [],
            childCourse: [],
            node_id: 'dndnode5',
            position: { x: 600, y: 400 },
            dimensions: { width: 100, height: 100 },
        },
      ];
  });

  it('should shift nodes down by the correct amount without an extra image shift', () => {
    const newNodeData: Node = {
      id: 'newNode1',
      node_id: 'newNode',
      type: 'custom',
      parentCourse: [],
      childCourse: [],
      position: { x: 0, y: 0 },
      dimensions: { width: 100, height: 100 },
    };
    shiftNodesDown(newNodeData, nodes);
    expect(nodes[0].position.y).toBe(450);
    expect(nodes[1].position.y).toBe(600);
    expect(nodes[2].position.y).toBe(750);
    expect(nodes[3].position.y).toBe(750);
    expect(nodes[4].position.y).toBe(400);
  });

  it('should shift nodes down by the correct amount with an extra image shift', () => {
    const newNodeData: Node = {
      id: 'newNode1',
      node_id: 'newNode',
      type: 'custom',
      parentCourse: [],
      childCourse: [],
      position: { x: 0, y: 0 },
      dimensions: { width: 100, height: 100 },
      selected_course_image: 'image.png', // Image is present
    };
    shiftNodesDown(newNodeData, nodes);
    expect(nodes[0].position.y).toBe(750);
    expect(nodes[1].position.y).toBe(750);
    expect(nodes[2].position.y).toBe(900);
    expect(nodes[3].position.y).toBe(1050);
    expect(nodes[4].position.y).toBe(400);
  });
});