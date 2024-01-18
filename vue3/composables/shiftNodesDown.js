// generate a new id
const  shiftNodesDown = (newNodeId, nodes) => {
  let shiftedNodes = [newNodeId]
  const alreadyShifted = new Set();
  while(shiftedNodes.length > 0){
    const currentNodeId = shiftedNodes.shift();
    nodes.forEach((node) => {
      if (
        node.type === 'custom' &&
        node.parentCourse.includes(currentNodeId) &&
        !alreadyShifted.has(currentNodeId)
      ) {
        node.position.y += 500 + node.dimensions.height / 4;
        if (node.childCourse.length > 0) {
            shiftedNodes.push(node.id);
        }
      }
    });
    alreadyShifted.add(currentNodeId);
  }
}

export default shiftNodesDown;