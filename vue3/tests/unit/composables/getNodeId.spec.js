import getNodeId from '../../../composables/getNodeId';

describe('getNodeId function test', () => {
  it('should return the correct ID when nodes array is empty', () => {
    const prefix = 'node_';
    const nodes = [];
    const result = getNodeId(prefix, nodes);
    expect(result).toBe('node_1');
  });

  it('should return the correct ID when there are no nodes with the matching prefix', () => {
    const prefix = 'node_';
    const nodes = [{ id: 'other_1' }, { id: 'other_2' }];
    const result = getNodeId(prefix, nodes);
    expect(result).toBe('node_1');
  });

  it('should return the next highest ID when there are nodes with the matching prefix', () => {
    const prefix = 'node_';
    const nodes = [
      { id: 'node_1' },
      { id: 'node_2' },
      { id: 'node_3' }
    ];
    const result = getNodeId(prefix, nodes);
    expect(result).toBe('node_4');
  });
  it('should handle gaps in the node ID sequence', () => {
    const prefix = 'node_';
    const nodes = [
      { id: 'node_1' },
      { id: 'node_3' },
      { id: 'node_5' }
    ];
    const result = getNodeId(prefix, nodes);
    expect(result).toBe('node_6');
  });
  it('should return 1 when no matching prefix is found', () => {
    const prefix = 'node_';
    const nodes = [
      { id: 'another_1' },
      { id: 'something_2' }
    ];
    const result = getNodeId(prefix, nodes);
    expect(result).toBe('node_1');
  });

  it('should only consider nodes with the exact matching prefix', () => {
    const prefix = 'node_';
    const nodes = [
      { id: 'node_1' },
      { id: 'other_2' },
      { id: 'node_2' },
      { id: 'notnode_3' }
    ];
    const result = getNodeId(prefix, nodes);
    expect(result).toBe('node_3');
  });

  it('should return the correct ID when the prefix appears later in the ID', () => {
    const prefix = 'node_';
    const nodes = [
      { id: 'prefix_node_1' },
      { id: 'prefix_node_2' }
    ];
    const result = getNodeId(prefix, nodes);
    expect(result).toBe('node_1');
  });

  it('should handle complex prefixes', () => {
    const prefix = 'complex_node_';
    const nodes = [
      { id: 'complex_node_1' },
      { id: 'complex_node_2' },
      { id: 'complex_node_3' }
    ];
    const result = getNodeId(prefix, nodes);
    expect(result).toBe('complex_node_4');
  });

  it('should handle complex prefixes', () => {
    const prefix = 'complex_node_';
    const nodes = [
      { id: 'complex_node_2' },
      { id: 'complex_node_3' },
      { id: 'complex_node_1' },
    ];
    const result = getNodeId(prefix, nodes);
    expect(result).toBe('complex_node_4');
  });
});