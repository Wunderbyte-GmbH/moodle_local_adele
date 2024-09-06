import outerGraphDisplay from '../../../../composables/flowHelper/outerGraphDisplay';
import { MarkerType } from '@vue-flow/core';

describe('outerGraphDisplay', () => {
  let edges, findNode, addEdges;

  beforeEach(() => {
    edges = [
      {
        id: 'edge1',
        source: 'node1',
        target: 'node2',
        data: { hidden: false },
      },
    ];

    findNode = jest.fn((id) => {
      if (id === 'node1') {
        return { id: 'node1', data: { module: 'moduleA' } };
      }
      if (id === 'node2') {
        return { id: 'node2', data: { module: 'moduleB' } };
      }
      return null;
    });

    addEdges = jest.fn();
  });

  it('should hide the existing edges', () => {
    const result = outerGraphDisplay(edges, findNode, addEdges);

    expect(result[0].data.hidden).toBe(true);
  });

  it('should not add a new edge if the edge name includes undefined', () => {
    // Modify findNode to return undefined for one of the nodes
    findNode = jest.fn((id) => {
      if (id === 'node1') {
        return { id: 'node1', data: { module: 'moduleA' } };
      }
      if (id === 'node2') {
        return { id: 'node2', data: { module: undefined } };
      }
      return null;
    });

    outerGraphDisplay(edges, findNode, addEdges);

    expect(addEdges).not.toHaveBeenCalled();
  });
});