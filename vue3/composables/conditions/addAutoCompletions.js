
// generate a new id
const  addAutoCompletions = (node, store) => {
    node.completion = {
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
            "childCondition": [
              "condition_1_feedback"
            ],
            "data": {
              "description": store.state.strings.course_description_condition_course_completed,
              "description_before": store.state.strings.course_description_before_condition_course_completed,
              "description_after": store.state.strings.course_description_after_condition_course_completed,
              "id": 150,
              "label": "course_completed",
              "name": store.state.strings.course_name_condition_course_completed,
              "node_id": 'condition_1',
              "visibility": true
            },
            "draggable": false,
            "deletable": false,
            "events": {},
            "id": "condition_1",
            "label": "custom node",
            "parentCondition": [
              "starting_condition"
            ],
            "position": {
              "x": 683,
              "y": 245
            },
            "type": "custom"
          },
          {
            "data": {
              "childCondition": 'condition_1',
              "feedback_before": "",
              "feedback_after": "",
              "feedback_before_checkmark": false,
              "feedback_after_checkmark": false,
            },
            "draggable": false,
            "deletable": false,
            "events": {},
            "id": "condition_1_feedback",
            "label": store.state.strings.composables_feedback_node,
            "position": {
              "x": 683,
              "y": -250
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
    node.position = {
      x: Math.round(node.position.x / 150) * 150,
      y: Math.round(node.position.y / 150) * 150,
    }
    return node;
}

export default addAutoCompletions;