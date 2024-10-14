import recalculateParentChild from '../../../composables/recalculateParentChild';

interface Node {
  id: string;
  type: string;
  data?: {
    course_node_id: string | string[]; // Allow string or string[] for flexibility
  };
  parentNodes?: string[];
  childNodes?: string[];
  [key: string]: any;
}

interface Edge {
  id: string;
  sourceHandle: string;
  source: string;
  target: string;
}

interface CustomTree {
  nodes: Node[];
  edges: Edge[];
}

describe('recalculateParentChild', () => {
  let tree: CustomTree;
  let parentNode: string;
  let childNode: string;
  let startNode: string;
  let result: CustomTree;

  beforeEach(() => {
    // Define the initial tree structure
    tree = {
      nodes: [
        { id: '1', type: 'custom', data: { course_node_id: '12' }, parentNodes: [], childNodes: [] },
        { id: '2', type: 'custom', data: { course_node_id: ['12', '23'] }, parentNodes: [], childNodes: [] },
        { id: '3', type: 'orcourses', data: { course_node_id: ['12', '23'] }, parentNodes: [], childNodes: [] },
        { id: '4', type: 'other', data: { course_node_id: '12' }, parentNodes: [], childNodes: [] },
      ],
      edges: [
        { id: '1e', source: '1', target: '2', sourceHandle: 'handle1' },
        { id: '2e', source: '2', target: '3', sourceHandle: 'handle2' },
        { id: '3e', source: '3', target: '1', sourceHandle: 'handle3' },
        { id: '4e', source: '1', target: '4', sourceHandle: 'orHandle' },
      ],
    };

    parentNode = 'parentNodes';
    childNode = 'childNodes';
    startNode = 'start';
  });

  it('should correctly update parent and child nodes', () => {
    result = recalculateParentChild(tree, parentNode, childNode, startNode);

    const node1 = result.nodes.find(node => node.id === '1');
    const node2 = result.nodes.find(node => node.id === '2');
    const node3 = result.nodes.find(node => node.id === '3');
    const node4 = result.nodes.find(node => node.id === '4');

    expect(node1?.[parentNode]).toEqual(['3']);
    expect(node1?.[childNode]).toEqual(['2']);

    expect(node2?.[parentNode]).toEqual(['1']);
    expect(node2?.[childNode]).toEqual(['3']);

    expect(node3?.[parentNode]).toEqual(['2']);
    expect(node3?.[childNode]).toEqual(['1']);

    expect(node4?.[parentNode]).toEqual([]);
    expect(node4?.[childNode]).toEqual([]);
  });

  it('should not add orHandles to parent or child nodes', () => {
    result = recalculateParentChild(tree, parentNode, childNode, startNode);

    result.nodes.forEach(node => {
      expect(node[childNode].includes('4')).toBe(false); // '4' is connected via 'orHandle'
      expect(node[parentNode].includes('3')).toBe(node.id === '1'); // '3' is connected to '1'
    });
  });

  it('should add startNode to parentNode if no parents found', () => {
    tree.edges = []; // No edges, so all nodes should only have startNode as parent

    const result = recalculateParentChild(tree, parentNode, childNode, startNode);

    result.nodes.forEach(node => {
      if (node.type === 'custom' || node.type === 'orcourses') {
        expect(node[parentNode]).toEqual([startNode]);
      } else {
        expect(node[parentNode]).toEqual([]);
      }
    });
  });

  it('should change the node type based on course_node_id length', () => {
    tree.nodes = [
      { id: '1', type: 'orcourses', data: { course_node_id: ['12'] }, parentNodes: [], childNodes: [] },
      { id: '2', type: 'custom', data: { course_node_id: ['12', '23'] }, parentNodes: [], childNodes: [] },
    ];

    const result = recalculateParentChild(tree, parentNode, childNode, startNode);

    const node1 = result.nodes.find(node => node.id === '1');
    const node2 = result.nodes.find(node => node.id === '2');

    expect(node1?.type).toEqual('custom'); // Single course_node_id, type becomes 'custom'
    expect(node2?.type).toEqual('orcourses'); // Multiple course_node_ids, type becomes 'orcourses'
  });
});
