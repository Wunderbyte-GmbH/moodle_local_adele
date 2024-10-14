interface Conditions {
  nodes: Node[];
}

interface Node {
  id: string;
  data: NodeData;
}

interface NodeData {
  label: string;
  value?: {
    testid?: string | null;
    quizid?: string | null;
  };
  error?: boolean;
}

const  validateNodes = (conditions: Conditions, findNode: (id: string) => Node) => {
  let invalidNodes = false
  conditions.nodes.forEach((node) => {
    if (
      (
        node.data.label == 'catquiz' ||
        node.data.label == 'modquiz'
      ) &&
      (
        node.data.value == null ||
        (
          node.data.value.testid == null &&
          node.data.value.quizid == null
        )
      )
    ) {
      let invalidNode = findNode(node.id)
      if (invalidNode) {
        invalidNode.data.error = true;
        invalidNodes = true;
      }
    } else if (node.data.error) {
      let invalidNode = findNode(node.id)
      delete(invalidNode.data.error)
    }
  })
  return invalidNodes
}
export default validateNodes;