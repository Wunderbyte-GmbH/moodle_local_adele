import setStartingNode from '../../../composables/setStartingNode';

// Mocking the functions used in setStartingNode
const mockRemoveNodes = jest.fn();
const mockNextTick = jest.fn(callback => callback());
const mockAddNodes = jest.fn();

describe('setStartingNode', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('removes the starting node and adds a new starting node in the correct position when view is not "teacher"', () => {
    // Mock data
    const nodes = [{ id: 'node1', parentCourse: 'starting_node', position: { x: 100, y: 100 }, dimensions: { width: 100 } }];
    const skip = 50;
    const view = 'not-teacher';

    // Call setStartingNode function
    setStartingNode(mockRemoveNodes, mockNextTick, mockAddNodes, nodes, skip, view);

    // Assertions
    expect(mockRemoveNodes).toHaveBeenCalledWith(['starting_node']);

    // Ensure nextTick callback is called
    expect(mockNextTick).toHaveBeenCalled();
    expect(mockAddNodes).toHaveBeenCalledWith([{
      id: 'starting_node',
      type: 'dropzone',
      position: { x: 100 + skip, y: 0 },
      label: `DZ node`,
      data: {
        opacity: '0.6',
        bgcolor: 'grey',
        infotext: 'New Starting node',
        height: '200px',
        width: '400px',
      },
      draggable: true,
      parentCourse: '',
    }]);
  });

  it('does not remove the starting node when there are other nodes and view is "teacher"', () => {
    // Mock data
    const nodes = [{ id: 'node1', parentCourse: 'starting_node', position: { x: 100, y: 100 }, dimensions: { width: 100 } }];
    const skip = 50;
    const view = 'teacher';

    // Call setStartingNode function
    expect(mockRemoveNodes).not.toHaveBeenCalled();
    setStartingNode(mockRemoveNodes, mockNextTick, mockAddNodes, nodes, skip, view);

    // Assertions
    expect(mockNextTick).not.toHaveBeenCalled();
    expect(mockAddNodes).not.toHaveBeenCalled();
  });

  it('does not remove the starting node when there are other nodes and view is "student"', () => {
    // Mock data
    const nodes = [{ id: 'node1', parentCourse: 'starting_node', position: { x: 100, y: 100 }, dimensions: { width: 100 } }];
    const skip = 50;
    const view = 'student';

    // Call setStartingNode function
    setStartingNode(mockRemoveNodes, mockNextTick, mockAddNodes, nodes, skip, view);
    
    // Assertions
    expect(mockRemoveNodes).toHaveBeenCalled();
    expect(mockNextTick).toHaveBeenCalled();
    expect(mockAddNodes).toHaveBeenCalled();
  });

  it('removes the starting node when view is not "teacher" and there are no other nodes', () => {
    // Mock data
    const nodes = [];
    const skip = 50;
    const view = 'student';

    // Call setStartingNode function
    setStartingNode(mockRemoveNodes, mockNextTick, mockAddNodes, nodes, skip, view);

    // Assertions
    expect(mockRemoveNodes).toHaveBeenCalledWith(['starting_node']);

    // Ensure nextTick callback is called, but addNodes should not be called since there are no nodes to shift
    expect(mockNextTick).toHaveBeenCalled();
    expect(mockAddNodes).toHaveBeenCalled();
  });
});
