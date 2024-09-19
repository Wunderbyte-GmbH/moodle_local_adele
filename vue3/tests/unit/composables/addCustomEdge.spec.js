import addCustomEdge from '../../../composables/addCustomEdge';
import { MarkerType } from '@vue-flow/core';

describe('addCustomEdge', () => {
  it('should generate a new edge with correct properties', () => {
    const source = 'node1';
    const target = 'node2';

    const expectedEdge = {
      id: 'node1node2',
      source: 'node2',
      target: 'node1',
      sourceHandle: 'source',
      targetHandle: 'target',
      style: {
        'stroke-width': 5,
        'position': 'relative',
        'z-index': -10
      },
      markerEnd: MarkerType.ArrowClosed,
    };

    const result = addCustomEdge(source, target);

    expect(result).toEqual(expectedEdge);
  });
});