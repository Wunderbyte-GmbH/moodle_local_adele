interface Node {
  restriction?: Restriction;
}

interface Restriction {
  nodes: RestrictionNode[];
}

interface RestrictionNode {
  id: string;
  data: RestrictionNodeData;
}

interface RestrictionNodeData {
  value: RestrictionNodeDataValue;
}

interface RestrictionNodeDataValue {
  courses_id: number[];
  courseid?: number | null;
  min_courses: number;
  node_id: number |number[];
}

const  removeNodeConditions = (node: Node, removedid: number): Node => {
    if(node.restriction) {
      node.restriction.nodes.forEach((noderestriction) => {
        if (
          !noderestriction.id.includes('_feedback') &&
          noderestriction.data.value
        ) {
            if (
              noderestriction.data.value.courses_id &&
              noderestriction.data.value.courses_id.includes(removedid)
            ) {
              noderestriction.data.value.courses_id = noderestriction.data.value.courses_id.filter(id => id !== removedid);
              if (noderestriction.data.value.min_courses > noderestriction.data.value.courses_id.length) {
                noderestriction.data.value.min_courses -=1
              }
            }
            if (
              noderestriction.data.value.node_id
            ) {
              if (
                Array.isArray(noderestriction.data.value.node_id) &&
                noderestriction.data.value.node_id.includes(removedid)
              ) {
                noderestriction.data.value.node_id = noderestriction.data.value.node_id.filter(id => id !== removedid);
              } else if (noderestriction.data.value.node_id == removedid) {
                delete node.restriction;
              }
            }
            if (
              noderestriction.data.value.courseid &&
              noderestriction.data.value.courseid == removedid
            ) {
              noderestriction.data.value.courseid = null
            }
        }
      })
    }
    return node;
}

export default removeNodeConditions;