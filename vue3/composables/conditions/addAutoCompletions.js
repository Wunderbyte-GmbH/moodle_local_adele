// generate a new id
const  addAutoCompletions = (node) => {
    node.completion = {
        "edges": [
          {
            "data": {},
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
              "description": "Course has been completed by student",
              "id": 150,
              "label": "course_completed",
              "name": "Course completed",
              "node_id": 'condition_1',
              "visibility": true
            },
            "draggable": false,
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
              "feedback": ""
            },
            "draggable": false,
            "events": {},
            "id": "condition_1_feedback",
            "label": "Feedback node",
            "position": {
              "x": 683,
              "y": -5
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
    return node;
}

export default addAutoCompletions;