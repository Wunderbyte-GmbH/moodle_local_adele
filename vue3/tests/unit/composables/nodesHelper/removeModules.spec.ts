import removeModules from '../../../../composables/nodesHelper/removeModules';

interface Node {
  id: string;
  type: string
}

interface Tree {
  nodes: Node[];
}
describe('removeModules', () => {
  let tree: Tree, removeNodes: jest.Mock<any, any>;

  beforeEach(() => {
    tree = {
      nodes: [
        { id: 'node1', type: 'custom' },
        { id: 'node2', type: 'moduleA' },
        { id: 'node3', type: 'custom' },
        { id: 'node4', type: 'moduleB' },
      ],
    };

    removeNodes = jest.fn();
  });

  it('should filter out nodes without "module" in their type when removeNodes is null', () => {
    const expectedTree: Tree = {
      nodes: [
        { id: 'node1', type: 'custom' },
        { id: 'node3', type: 'custom' },
      ],
    };

    const result = removeModules(tree);
    expect(result).toEqual(expectedTree);
  });

  it('should call removeNodes with nodes that have "module" in their type', () => {
    removeModules(tree, removeNodes);

    // Ensure removeNodes was called with the correct nodes
    expect(removeNodes).toHaveBeenCalledWith([{ id: 'node2', type: 'moduleA' },{ id: 'node4', type: 'moduleB' }]);
  });

  it('should return 1 as nothing happens', () => {
    const result =  removeModules(null as unknown as Tree, removeNodes);
    expect(result).toEqual(1);
  });
});