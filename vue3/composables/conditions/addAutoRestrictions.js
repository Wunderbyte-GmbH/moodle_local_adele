// generate a new id
const  addAutoRestrictions = (newNode, oldNode, relation) => {
  if (relation == 'child') {
    // Add restriction to new node.
    newNode.restriction = createRestriction(newNode.id, oldNode.data.course_node_id)
    return newNode
  } else if (relation == 'parent') {
    // Add restriction to already exsisting node.
    if (oldNode.restriction != undefined) {
      oldNode = expandRestriction(newNode, oldNode)
    } else {
      oldNode.restriction = createRestriction(oldNode.id, newNode.data.course_node_id)
    }
    return oldNode
  }
}

function createRestriction (node_id, course_id) {
  return {
    "edges": [],
    "nodes": [
      {
        "childCondition": [],
        "data": {
          "description": "Only if a certain course of this learning path is completed",
          "id": 150,
          "label": "specific_course",
          "name": "Certain course completed",
          "node_id": node_id,
          "value": {
            "courseid": course_id
          },
          "visibility": true
        },
        "draggable": false,
        "events": {},
        "id": 'condition_1',
        "label": "custom node",
        "parentCondition": [
          "starting_condition"
        ],
        "position": {
          "x": 735,
          "y": 247.5
        },
        "type": "custom"
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

function expandRestriction (newNode, oldNode) {
  let x = null
  let y = null
  let id = 0
  let rightesNodeId = null
  oldNode.restriction.nodes.forEach((node) => {
    if (x == null || x < node.position.x) {
       x = node.position.x
      rightesNodeId = node.id
    }
    if (y == null || y > node.position.y) {
      y = node.position.y
    }

    let tmp_id = parseInt(node.id.replace('condition_', '')); 
    if (id == 0 || id < tmp_id) {
      id = tmp_id
    }
  })
  id = id +1
  oldNode.restriction.nodes.push({
    "childCondition": [],
    "data": {
      "description": "Only if a certain course of this learning path is completed",
      "id": 150,
      "label": "specific_course",
      "name": "Certain course completed",
      "node_id": 'condition_' + id,
      "value": {
        "courseid": newNode.data.course_node_id
      },
      "visibility": true
    },
    "draggable": false,
    "events": {},
    "id": 'condition_' + id,
    "label": "custom node",
    "parentCondition": [
      "starting_condition"
    ],
    "position": {
      "x": x +450,
      "y": y
    },
    "type": "custom"
  })
  oldNode.restriction.edges.push({
    "data": {
      "text": "OR",
      "type": "disjunctional"
    },
    "events": {},
    "id": rightesNodeId + "-condition_" + id,
    "source": rightesNodeId,
    "sourceHandle": "source_or",
    "target": "condition_" + id,
    "targetHandle": "target_or",
    "type": "condition"
  })
  // Add edge:
  return oldNode;
}

export default addAutoRestrictions;