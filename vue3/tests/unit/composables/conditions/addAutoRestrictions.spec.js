import { nextTick } from 'vue';
import addAutoRestrictions from '../../../../composables/conditions/addAutoRestrictions';


describe('addAutoRestrictions', () => {
  const newNode = {
    id: 'new_node',
    label: 'custom node',
    data: { course_node_id: 'course_id' },
    type: 'custom'
  };
  let oldNode = {
    id: 'old_node',
    data: { course_node_id: 'course_id' }
  };
  it('adds restriction to new node when relation is "child"', () => {
    const relation = 'child';
    const result = addAutoRestrictions(newNode, oldNode, relation);

    expect(result.restriction).toBeTruthy();
    expect(result.restriction.nodes.length).toBe(1);
    expect(result.restriction.edges.length).toBe(0);
    // Add more assertions as needed
  });

  it('adds restriction to already existing node when relation is "parent" and oldNode has no existing restriction', () => {
    const relation = 'parent';
    const result = addAutoRestrictions(newNode, oldNode, relation);

    expect(result.restriction).toBeTruthy();
    expect(result.restriction.nodes.length).toBe(1);
    expect(result.restriction.edges.length).toBe(0);
    // Add more assertions as needed
  });

  it('expands existing restriction when relation is "parent" and oldNode has an existing restriction', async () => {
    oldNode = {
      restriction: {
        nodes: [
          { 
            id: 'condition_1', 
            position: { x: 100, y: 100 },
            label: 'custom node',
            type: 'custom'
          }
        ],
        edges: []
      }
    };
    const relation = 'parent';
    const result = addAutoRestrictions(newNode, oldNode, relation);

    expect(result.restriction).toBeTruthy();
    expect(result.restriction.nodes.length).toBe(2);
    expect(result.restriction.edges.length).toBe(1);
    // Add more assertions as needed
  });

  it('expands existing restriction when relation is "and"', async () => {
    const oldNode = {
      restriction: {
        nodes: [
          { 
            id: 'condition_1', 
            position: { x: 100, y: 100 },
            label: 'custom node',
            parentCondition: [ 'starting_condition' ],
            type: 'custom',
          }
        ],
        edges: []
      }
    };
    const relation = 'and';
    const result = addAutoRestrictions(newNode, oldNode, relation);
    await nextTick(); // Await nextTick() here
  
    expect(result.restriction).toBeTruthy();
    expect(result.restriction.nodes.length).toBe(2);
  });
});