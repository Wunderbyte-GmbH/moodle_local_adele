import getNodeId from '../../../composables/getNodeId';

describe('getNodeId', () => {
  it('Search for the node id given a prefixe', () => {
    const source = 'node1';
    const prefix = 'dndnode';
    const nodes = [
      { id: 'dndnode_1' },
      { id: 'dndnode_2' },
      { id: 'dndnode_3' },
    ];

    const expectedNodeId = prefix + (nodes.length + 1);

    const result = getNodeId(prefix, nodes);

    expect(result).toEqual(expectedNodeId);
  });
});