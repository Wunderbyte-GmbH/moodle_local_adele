import standaloneNodeCheck from '../../../composables/standaloneNodeCheck';

describe('standaloneNodeCheck', () => {
  it('returns false when there is only one node', () => {
    const tree = {
      nodes: [{ id: 'node1' }],
      edges: [],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(false);
  });

  it('returns false when all nodes are connected', () => {
    const tree = {
      nodes: [
        { id: 'node1' },
        { id: 'node2' },
        { id: 'node3' },
      ],
      edges: [
        { source: 'node1', target: 'node2' },
        { source: 'node2', target: 'node3' },
      ],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(false);
  });

  it('returns true when there is a standalone node', () => {
    const tree = {
      nodes: [
        { id: 'node1' },
        { id: 'node2' },
        { id: 'node3' },
      ],
      edges: [
        { source: 'node1', target: 'node2' },
      ],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(true);
  });
  it('returns true when there is a standalone node', () => {
    const tree = {
      nodes: [
        { id: 'node1' },
        { id: 'node2' },
        { id: 'node3' },
      ],
      edges: [
        { source: 'node1', target: 'node2' },
        { source: 'node1', target: 'node3' },
        { source: 'node2', target: 'node3' },
      ],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(false);
  });
});