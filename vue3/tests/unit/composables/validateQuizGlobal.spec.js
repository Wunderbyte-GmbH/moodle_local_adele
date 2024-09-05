import validateQuizGlobal from '../../../composables/validateQuizGlobal';

describe('validateQuizGlobal', () => {
  let conditions;
  let findNodeMock;

  beforeEach(() => {
    // Initial conditions and mock
    conditions = {
      nodes: [
        {
          id: 'node1',
          data: {
            label: 'catquiz',
            value: {
              scales: {
                parent: {
                  scale: 'scale1',
                },
              },
            },
            error: false,
          },
        },
        {
          id: 'node2',
          data: {
            label: 'catquiz',
            value: {
              scales: {
                parent: {
                  scale: '',
                },
              },
            },
            error: false,
          },
        },
      ],
    };

    // Mock function to simulate findNode
    findNodeMock = jest.fn((id) => {
      return conditions.nodes.find(node => node.id === id);
    });
  });

  it('returns 0 warnings when quizsetting is not "all_quiz_global"', () => {
    const warnings = validateQuizGlobal(conditions, findNodeMock, 'some_other_quiz_setting');
    expect(warnings).toBe(0);
    expect(findNodeMock).not.toHaveBeenCalled();
  });

  it('returns the correct number of warnings for invalid quiz settings', () => {
    const warnings = validateQuizGlobal(conditions, findNodeMock, 'all_quiz_global');

    // Check if warnings are detected
    expect(warnings).toBe(1);

    // Ensure that findNode is called for the node with an invalid scale
    expect(findNodeMock).toHaveBeenCalledWith('node2');

    // Ensure that the error flag is set on the invalid node
    expect(conditions.nodes[1].data.error).toBe(true);
  });

  it('removes error flag if node becomes valid', () => {
    // Start with an error node
    conditions.nodes[0].data.error = true;

    // Run validation where conditions are met
    const warnings = validateQuizGlobal(conditions, findNodeMock, 'all_quiz_global');

    // Since the node is valid now, the error should be removed
    expect(warnings).toBe(1); // only node2 is invalid
    expect(conditions.nodes[0].data.error).toBe(undefined); // error removed for node1
  });

});