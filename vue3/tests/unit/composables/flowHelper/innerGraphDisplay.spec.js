import innerGraphDisplay from '../../../../composables/flowHelper/innerGraphDisplay';

describe('innerGraphDisplay', () => {
  let edges, removeEdges;

  beforeEach(() => {
    edges = [
      {
        id: 'edge1',
        source: 'node1',
        target: 'node2',
        data: { hidden: true },
      },
      {
        id: 'edge2',
        source: 'node1',
        target: 'node3',
      },
    ];

    removeEdges = jest.fn();
  });

  it('should hide the existing edges', () => {
    const expected = [
      {
        id: 'edge1',
        source: 'node1',
        target: 'node2',
        data: { hidden: false },
      },
    ];
    const result = innerGraphDisplay(edges, removeEdges);
    expect(result).toEqual(expected);

    // Check if edges without data are removed
    expect(removeEdges).toHaveBeenCalledWith('edge2');
  });
});