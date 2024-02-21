import standaloneNodeCheck from '../../../composables/standaloneNodeCheck';

describe('standaloneNodeCheck', () => {
  it('returns false if there is only one node', () => {
    const tree = {
      nodes: [{ id: 'A' }],
      edges: [],
    };
    expect(standaloneNodeCheck(tree)).toBe(false);
  });

  it('returns false if nodes are connected by edges', () => {
    const tree = {
      nodes: [
        { id: 'A' },
        { id: 'B' },
        { id: 'C' }
      ],
      edges: [
        { source: 'A', target: 'B' },
        { source: 'B', target: 'C' }
      ],
    };
    expect(standaloneNodeCheck(tree)).toBe(false);
  });

  it('returns true if there are standalone nodes', () => {
    const tree = {
      nodes: [
        { id: 'A' },
        { id: 'B' },
        { id: 'C' }
      ],
      edges: [
        { source: 'A', target: 'B' },
      ],
    };
    expect(standaloneNodeCheck(tree)).toBe(true);
  });

  it('returns false if all nodes are connected in a loop', () => {
    const tree = {
      nodes: [
        { id: 'A' },
        { id: 'B' },
        { id: 'C' }
      ],
      edges: [
        { source: 'A', target: 'B' },
        { source: 'B', target: 'C' },
        { source: 'C', target: 'A' },
      ],
    };
    expect(standaloneNodeCheck(tree)).toBe(false);
  });

  it('returns false for empty tree', () => {
    const tree = {
      nodes: [],
      edges: [],
    };
    expect(standaloneNodeCheck(tree)).toBe(false);
  });
});

