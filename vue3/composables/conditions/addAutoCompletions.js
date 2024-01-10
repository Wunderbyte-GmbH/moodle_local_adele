// generate a new id
const  addAutoCompletions = (node) => {
    node.completion = {
        "edges": [
          {
            "data": {},
            "events": {},
            "id": node.id +"-" + node.id + "_feedback",
            "source": node.id,
            "sourceHandle": "target_and",
            "sourceX": 858,
            "sourceY": 241,
            "target": node.id + "_feedback",
            "targetHandle": "source_feedback",
            "targetX": 858,
            "targetY": 199,
            "type": "default"
          }
        ],
        "nodes": [
          {
            "childCondition": [
              node.id + "_feedback"
            ],
            "data": {
              "description": "Course has been completed by student",
              "id": 150,
              "label": "course_completed",
              "name": "Course completed",
              "node_id": node.id,
              "visibility": true
            },
            "draggable": false,
            "events": {},
            "id": node.id,
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
              "childCondition": node.id,
              "feedback": ""
            },
            "draggable": false,
            "events": {},
            "id": node.id + "_feedback",
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