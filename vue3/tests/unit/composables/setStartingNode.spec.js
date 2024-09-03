import setStartingNode from '../../../composables/setStartingNode';

describe('setStartingNode', () => {
  let removeNodesMock, nextTickMock, addNodesMock, storeMock, nodes;

    beforeEach(() => {
      removeNodesMock = jest.fn();
      nextTickMock = jest.fn((cb) => cb());
      addNodesMock = jest.fn();
      storeMock = {
          state: {
              view: 'student',
              strings: {
                  composables_new_node: 'New node info text',
              },
          },
      };
      nodes = [
          { id: 'node1', position: { x: 100, y: 100 }, dimensions: { width: 200, height: 100 }, parentCourse: 'starting_node' },
          { id: 'node2', position: { x: 400, y: 100 }, dimensions: { width: 200, height: 100 }, parentCourse: 'starting_node' },
      ];
  });

  it('should not execute if the store view is teacher', () => {
    storeMock.state.view = 'teacher';
    setStartingNode(removeNodesMock, nextTickMock, addNodesMock, nodes, 150, storeMock);
    expect(removeNodesMock).not.toHaveBeenCalled();
    expect(addNodesMock).not.toHaveBeenCalled();
  });

  it('should calculate the correct position for the starting node', () => {
    setStartingNode(removeNodesMock, nextTickMock, addNodesMock, nodes, 150, storeMock);

    expect(removeNodesMock).toHaveBeenCalledWith(['starting_node']);
    expect(addNodesMock).toHaveBeenCalledWith([expect.objectContaining({
      id: 'starting_node',
      position: { x: 900, y: 0 },
      data: {
          infotext: 'New node info text',
          opacity: '0.6',
          bgcolor: 'grey',
          height: '200px',
          width: '400px',
      },
      draggable: true,
      type: 'dropzone',
      label: 'DZ node',
      parentCourse: '',
  })]);
  });

  it('should adjust the position to avoid intersections', () => {
    nodes.push({
        id: 'starting_node',
        position: { x: 750, y: 0 },
        dimensions: { width: 200, height: 100 },
        parentCourse: 'starting_node'
    });

    setStartingNode(removeNodesMock, nextTickMock, addNodesMock, nodes, 150, storeMock, true);

    expect(removeNodesMock).toHaveBeenCalledWith(['starting_node']);
    expect(addNodesMock).toHaveBeenCalledWith([expect.objectContaining({
        id: 'starting_node',
        position: { x: 1050, y: 0 },
        data: {
            infotext: 'New node info text',
            opacity: '0.6',
            bgcolor: 'grey',
            height: '200px',
            width: '400px',
        },
        draggable: true,
        type: 'dropzone',
        label: 'DZ node',
        parentCourse: '',
    })]);
  });
});