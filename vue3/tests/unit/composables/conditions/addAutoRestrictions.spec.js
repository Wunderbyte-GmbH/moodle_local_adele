import addAutoRestrictions from '../../../../composables/conditions/addAutoRestrictions';

describe('addAutoRestrictions', () => {
  let newNode, oldNode, store;

  beforeEach(() => {
    // Mock the newNode and oldNode structures
    newNode = { id: 'new_node', restriction: undefined };
    oldNode = { id: 'old_node', restriction: undefined };

    // Mock the store object
    store = {
      state: {
        strings: {
          course_description_condition_parent_node_completed: 'Parent node completion condition',
          course_restricition_before_condition_parent_node_completed: 'Restriction before condition',
          course_name_condition_parent_node_completed: 'Parent Node Condition',
          composables_feedback_node: 'Feedback Node',
        },
      },
    };
  });


  it('should add restriction to newNode when relation is child', () => {
    const result = addAutoRestrictions(newNode, oldNode, 'child', store);

    // Check that the restriction has been added to newNode
    expect(result.restriction).toBeDefined();
    expect(result.restriction.nodes).toHaveLength(2); // Two nodes: condition_1 and condition_1_feedback
    expect(result.restriction.nodes[0].data.label).toBe('parent_node_completed');
    expect(result.restriction.nodes[0].data.value.node_id).toBe(oldNode.id);
    expect(result.restriction.nodes[1].label).toBe(store.state.strings.composables_feedback_node);
  });

  it('should add restriction to oldNode when relation is parent and oldNode has no restriction', () => {
    const result = addAutoRestrictions(newNode, oldNode, 'parent', store);

    // Check that the restriction has been added to oldNode
    expect(result.restriction).toBeDefined();
    expect(result.restriction.nodes).toHaveLength(2); // Two nodes: condition_1 and condition_1_feedback
    expect(result.restriction.nodes[0].data.label).toBe('parent_node_completed');
    expect(result.restriction.nodes[0].data.value.node_id).toBe(newNode.id);
    expect(result.restriction.nodes[1].label).toBe(store.state.strings.composables_feedback_node);
  });

  it('should not modify oldNode when relation is parent and oldNode has a restriction', () => {
    // Simulate oldNode having a restriction already
    oldNode.restriction = { nodes: [{ id: 'condition_1', data: {} }] };

    const result = addAutoRestrictions(newNode, oldNode, 'parent', store);

    // Since oldNode already has a restriction, it should be returned unmodified
    expect(result).toBe(oldNode);
    expect(result.restriction.nodes).toHaveLength(1); // Should not be expanded
  });

  it('should return oldNode unchanged when relation is and', () => {
    const result = addAutoRestrictions(newNode, oldNode, 'and', store);

    // When the relation is 'and', the function should just return the oldNode
    expect(result).toBe(oldNode);
  });

  it('should return oldNode unchanged when relation is and', () => {
    const result = addAutoRestrictions(newNode, oldNode, 'or', store);

    // When the relation is 'and', the function should just return the oldNode
    expect(result).toBe(null);
  });

});