interface Node {
  id: string;
  node_id: string;
  type: string,
  parentCourse: string[];
  childCourse: string[];
  selected_course_image?: string;
  dimensions: Dimensions;
  position: Position;
}

interface Dimensions {
  width: number;
  height: number;
}

interface Position {
  x: number;
  y: number;
}

const  shiftNodesDown = (newNodeData: Node, nodes: Node[]) => {
  let shiftedNodes: string[] = [newNodeData.node_id]
  const extraImageShift = newNodeData.selected_course_image ? 200 : 0
  const alreadyShifted = new Set<string>();
  while(shiftedNodes.length > 0){
    const currentNodeId = shiftedNodes.shift();

    if (!currentNodeId) {
      continue; // Skip if currentNodeId is undefined
    }
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