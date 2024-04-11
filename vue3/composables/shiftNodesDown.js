// generate a new id
const  shiftNodesDown = (newNodeData, nodes) => {
  let shiftedNodes = [newNodeData.node_id]
  const extraImageShift = newNodeData.selected_course_image ? 200 : 0
  const alreadyShifted = new Set();
  while(shiftedNodes.length > 0){
    const currentNodeId = shiftedNodes.shift();
    nodes.forEach((node) => {
      if (
        node.type === 'custom' &&
        node.parentCourse.includes(currentNodeId) &&
        !alreadyShifted.has(currentNodeId)
      ) {
        node.position.y += 350 + node.dimensions.height / 4 + extraImageShift;
        node.position.y = Math.round(node.position.y / 150) * 150
        if (node.childCourse.length > 0) {
            shiftedNodes.push(node.id);
        }
      }
    });
    alreadyShifted.add(currentNodeId);
  }
}

export default shiftNodesDown;