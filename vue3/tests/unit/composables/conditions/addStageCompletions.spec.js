import addStagCompletions from '../../../../composables/conditions/addStagCompletions';

describe('addStagCompletions', () => {
  let node;

  it('should return the node as it is', () => {
    node = {
      completion: null,
    };
    const result = addStagCompletions(node);
    expect(result).toEqual(node);
  });

  it('should return the node with min_courses', () => {
    node = {
      completion: {
        nodes: [
          {
            type: 'custom',
            data: { label: 'course_completed', value: undefined },
          },
          {
            type: 'custom',
            data: { label: 'some_other_label', value: undefined },
          },
          {
            type: 'other_type',
            data: { label: 'course_completed', value: undefined },
          },
        ],
      },
    };
    const result = addStagCompletions(node);

    // Check if the completion node with 'course_completed' has its value updated
    const updatedNode = result.completion.nodes.find(
      (completion_node) =>
        completion_node.type === 'custom' &&
        completion_node.data.label === 'course_completed'
    );

    expect(updatedNode.data.value).toEqual({
      min_courses: 1,
    });
  });
});