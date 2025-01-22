
// generate a new id
const  addAutoRestrictions = (newNode, oldNode, relation, store) => {
  if (relation == 'child') {
    // Add restriction to new node.
    newNode.restriction = createRestriction(newNode.id, oldNode.id, store)
    return newNode
  } else if (relation == 'parent') {
    // Add restriction to already exsisting node.
    if (oldNode.restriction != undefined) {
      return oldNode
    } else {
      oldNode.restriction = createRestriction(oldNode.id, newNode.id, store)
    }
    return oldNode
  } else if (relation == 'and') {
    // Add restriction to already exsisting node.
    return oldNode
  }
  return null
}

function createRestriction (node_id, parent_node_id, store) {

  return {
    "edges": [
      {
        "data": {},
        "deletable": false,
        "events": {},
        "id": "condition_1-condition_feedback",
        "source": "condition_1",
        "sourceHandle": "target_and",
        "sourceX": 858,
        "sourceY": 241,
        "target": "condition_1_feedback",
        "targetHandle": "source_feedback",
        "targetX": 858,
        "targetY": 199,
        "type": "default"
      }
    ],
    "nodes": [
      {
        "childCondition": [],
        "data": {
          "description": store.state.strings.course_description_condition_parent_courses,
          "description_before": store.state.strings.course_restricition_before_condition_parent_courses,
          "id": 150,
          "label": "parent_courses",
          "name": store.state.strings.course_name_condition_parent_courses,
          "node_id": 'condition_1',
          "value": {
            "node_id": parent_node_id
          },
          "visibility": true
        },
        "draggable": false,
        "deletable": false,
        "events": {},
        "id": 'condition_1',
        "label": "custom node",
        "parentCondition": [
          "starting_condition"
        ],
        "position": {
          "x": 683,
          "y": 247.5
        },
        "type": "custom"
      },
      {
        "data": {
          "childCondition": 'condition_1',
          "visibility": true,
          "feedback_before": store.state.strings.course_restricition_before_condition_parent_courses,
          "feedback_before_checkmark": true,
        },
        "draggable": false,
        "deletable": false,
        "events": {},
        "id": "condition_1_feedback",
        "label": store.state.strings.composables_feedback_node,
        "position": {
          "x": 683,
          "y": -100
        },
        "type": "feedback"
      }
    ],
    "position": [
      0,
      0
    ],
    "viewport": {
      "x": 0,
      "y": 0,
      "zoom": 1
    },
    "zoom": 1
  }
}

export default addAutoRestrictions;