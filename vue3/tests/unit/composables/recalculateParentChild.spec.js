import recalculateParentChild from '../../../composables/recalculateParentChild';

describe('recalculateParentChild', () => {
  let tree;
  let parentNode;
  let childNode;
  let startNode;
  let result;

  beforeEach(() => {
    // Define the initial tree structure
    tree = {
      nodes: [
        { id: '1', type: 'custom', parentNodes: [], childNodes: [] },
        { id: '2', type: 'custom', parentNodes: [], childNodes: [] },
        { id: '3', type: 'orcourses', parentNodes: [], childNodes: [] },
        { id: '4', type: 'other', parentNodes: [], childNodes: [] },
      ],
      edges: [
        { source: '1', target: '2', sourceHandle: 'handle1' },
        { source: '2', target: '3', sourceHandle: 'handle2' },
        { source: '3', target: '1', sourceHandle: 'handle3' },
        { source: '1', target: '4', sourceHandle: 'orHandle' },
      ],
    };

    parentNode = 'parentNodes';
    childNode = 'childNodes';
    startNode = 'start';
    result = recalculateParentChild(tree, parentNode, childNode, startNode);
  });

  it('should correctly update parent and child nodes', () => {
    const node1 = result.nodes.find(node => node.id === '1');
    const node2 = result.nodes.find(node => node.id === '2');
    const node3 = result.nodes.find(node => node.id === '3');
    const node4 = result.nodes.find(node => node.id === '4');
    expect(node1[parentNode]).toEqual(['3']);
    expect(node1[childNode]).toEqual(['2']);

    expect(node2[parentNode]).toEqual(['1']);
    expect(node2[childNode]).toEqual(['3']);

    expect(node3[parentNode]).toEqual(['2']);
    expect(node3[childNode]).toEqual(['1']);

    expect(node4[parentNode]).toEqual([]);
    expect(node4[childNode]).toEqual([]);
  });

  it('should not add orHandles to parent or child nodes', () => {
    result.nodes.forEach(node => {
      expect(node[childNode].includes('4')).toBe(false);
      expect(node[parentNode].includes('3')).toBe(node.id === '1');
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
});