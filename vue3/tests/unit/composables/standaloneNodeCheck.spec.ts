import standaloneNodeCheck from '../../../composables/standaloneNodeCheck';

interface Node {
  id: string,
}

interface Edge {
  id: string,
  target: string;
  source: string;
}

interface Tree {
  nodes: Node[];
  edges: Edge[];
}

describe('standaloneNodeCheck', () => {
  it('returns false when there is only one node', () => {
    const tree: Tree = {
      nodes: [{ id: 'node1' }],
      edges: [],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(false);
  });

  it('returns false when all nodes are connected', () => {
    const tree: Tree = {
      nodes: [
        { id: 'node1' },
        { id: 'node2' },
        { id: 'node3' },
      ],
      edges: [
        { id: 'edge1', source: 'node1', target: 'node2' },
        { id: 'edge1', source: 'node2', target: 'node3' },
      ],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(false);
  });

  it('returns true when there is a standalone node', () => {
    const tree: Tree = {
      nodes: [
        { id: 'node1' },
        { id: 'node2' },
        { id: 'node3' },
      ],
      edges: [
        { id: 'edge1', source: 'node1', target: 'node2' },
      ],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(true);
  });

  it('returns true when there is a standalone node', () => {
    const tree: Tree = {
      nodes: [
        { id: 'node1' },
        { id: 'node2' },
        { id: 'node3' },
      ],
      edges: [
        { id: 'edge1', source: 'node1', target: 'node2' },
        { id: 'edge2', source: 'node1', target: 'node3' },
        { id: 'edge3', source: 'node2', target: 'node3' },
      ],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(false);
  });
});