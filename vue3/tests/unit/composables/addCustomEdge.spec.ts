import addCustomEdge from '../../../composables/addCustomEdge';
import { MarkerType } from '@vue-flow/core';

interface CustomEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle: string;
  targetHandle: string;
  style: {
    'stroke-width': number;
    'position': string;
    'z-index': number;
  };
  markerEnd: MarkerType;
}

describe('addCustomEdge', () => {
  it('should generate a new edge with correct properties', () => {
    const source: string = 'node1';
    const target: string = 'node2';

    const expectedEdge: CustomEdge = {
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