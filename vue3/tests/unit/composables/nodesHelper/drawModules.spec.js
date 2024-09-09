import drawModules from '../../../../composables/nodesHelper/drawModules';
import removeModules from '../../../../composables/nodesHelper/removeModules';
import darkenColor from '../../../../composables/nodesHelper/darkenColor';

jest.mock('../../../../composables/nodesHelper/removeModules');
jest.mock('../../../../composables/nodesHelper/darkenColor');

describe('drawModules', () => {
  let learningpath, addNodes, removeNodes, findNode, draggedNode;

  beforeEach(() => {
    removeModules.mockResolvedValue();
    darkenColor.mockReturnValue('#000000');

    learningpath = {
      json: {
        modules: [
          { id: 'module1', color: '#ff0000' },
          { id: 'module2', color: '#00ff00' },
        ],
        tree: {
          nodes: [
            { id: 'node1', data: { module: 'module1', completion: { singlerestrictionnode: [] } }, position: { x: 100, y: 200 } },
            { id: 'node2', data: { module: 'module2', completion: { singlerestrictionnode: [true] } }, position: { x: 300, y: 400 } },
          ]
        }
      }
    };

    addNodes = jest.fn();
    removeNodes = jest.fn();
    findNode = jest.fn((id) => {
      if (id === 'node1') {
        return { dimensions: { height: 100 } };
      }
      if (id === 'node2') {
        return { dimensions: { height: 200 } };
      }
      return null;
    });

    draggedNode = null;
  });

  it('should call removeModules and addNodes', async () => {
    await drawModules(learningpath, addNodes, removeNodes, findNode, draggedNode);

    // Ensure removeModules was called
    expect(removeModules).toHaveBeenCalledWith(learningpath.json.tree, removeNodes);

    // Ensure addNodes was called with the expected modules
    expect(addNodes).toHaveBeenCalled();
    const addedModules = addNodes.mock.calls[0][0];
    expect(addedModules).toHaveLength(2);
    expect(addedModules[0].id).toBe('module1_module');
    expect(addedModules[1].id).toBe('module2_module');
  });

  it('should call removeModules and addNodes', async () => {
    learningpath.json.modules = null
    await drawModules(learningpath, addNodes, removeNodes, findNode, draggedNode);
    // Ensure addNodes is not called
    expect(addNodes).not.toHaveBeenCalled();
  });

  it('should set module properties based on completion and active status', async () => {
    await drawModules(learningpath, addNodes, removeNodes, findNode, draggedNode);

    const addedModules = addNodes.mock.calls[0][0];
    const module1 = addedModules.find(module => module.id === 'module1_module');
    expect(module1.data.opacity).toBe('0.2');
    const module2 = addedModules.find(module => module.id === 'module2_module');
    expect(module2.data.opacity).toBe('0.2');
  });

  it('should calculate correct height and width for the module', async () => {
    await drawModules(learningpath, addNodes, removeNodes, findNode, draggedNode);

    const addedModules = addNodes.mock.calls[0][0];
    const module1 = addedModules.find(module => module.id === 'module1_module');
    const module2 = addedModules.find(module => module.id === 'module2_module');

    expect(module1.data.height).toBe('180px');
    expect(module1.data.width).toBe('500px');
    expect(module1.position.y).toBe(160);
    expect(module1.position.x).toBe(50);

    expect(module2.data.height).toBe('280px');
    expect(module2.data.width).toBe('500px');
    expect(module2.position.y).toBe(360);
    expect(module2.position.x).toBe(250);
  });

  it('should remove module if insertModule is false', async () => {
    // Modify the tree so there is no matching node for module1
    learningpath.json.tree.nodes = [
      { id: 'node3', data: { module: 'module3' }, position: { x: 100, y: 200 } }
    ];

    await drawModules(learningpath, addNodes, removeNodes, findNode, draggedNode);

    // Ensure module1 is removed because insertModule is false
    expect(removeNodes).toHaveBeenCalledWith(['module1_module']);
    expect(removeNodes).toHaveBeenCalledWith(['module2_module']);
  });

  it('should use draggedNode position if draggedNode is present', async () => {
    draggedNode = { id: 'node1', position: { x: 350, y: 500 } };

    await drawModules(learningpath, addNodes, removeNodes, findNode, draggedNode);

    const addedModules = addNodes.mock.calls[0][0];
    const module1 = addedModules.find(module => module.id === 'module1_module');

    // Check that the draggedNode's position is applied
    expect(module1.position.x).toBe(300);
    expect(module1.position.y).toBe(460);
  });

  it('should make the opacity higher', async () => {
    learningpath = {
      json: {
        modules: [
          { id: 'module1', color: '#ff0000' },
        ],
        tree: {
          nodes: [
            { id: 'node1', data: { module: 'module1', completion: { singlerestrictionnode: [false] } }, position: { x: 100, y: 200 } },
          ]
        }
      }
    };

    await drawModules(learningpath, addNodes, removeNodes, findNode, draggedNode);
    const addedModules = addNodes.mock.calls[0][0];
    const module1 = addedModules.find(module => module.id === 'module1_module');
    expect(module1.data.opacity).toBe('0.6');
  });

});
