import removeDropzones from '../../../composables/removeDropzones';

interface Node {
  id: string,
  type: string;
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

describe('removeDropzone', () => {
  it('should remove nodes and edges with "dropzone" in their type', () => {
    const tree: Tree = {
        nodes: [
            { id: '1', type: 'custom' },
            { id: '2', type: 'dropzone' },
            { id: '3', type: 'custom' },
            { id: '4', type: 'dropzone_special' }
        ],
        edges: [
            { id: 'e1', source: '1', target: '2' },
            { id: 'e2', source: '3', target: '4' },
            { id: 'e3', source: '1', target: 'dropzone_3' }
        ]
    };

    const result = removeDropzones(tree);

    expect(result.nodes).toHaveLength(2);
    expect(result.nodes).toEqual([
        { id: '1', type: 'custom' },
        { id: '3', type: 'custom' }
    ]);
    expect(result.edges).toHaveLength(2);
    expect(result.edges).toEqual([
      { id: 'e1', source: '1', target: '2' },
      { id: 'e2', source: '3', target: '4' }
    ]);
});
});