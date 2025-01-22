interface NodeData {
  label?: string;
  value?: {
    scales?: {
      parent?: {
        scale?: string;
      };
    };
  };
  error?: boolean;
}

interface Node {
  id: string;
  data: NodeData
}

interface Conditions {
  nodes: Node[]
}

type FindNode = (id: string) => Node  | undefined;

const  validateQuizGlobal = (conditions: Conditions, findNode: FindNode, quizsetting: string): number => {
  let globalwarning = 0
  if (quizsetting == 'all_quiz_global') {
    conditions.nodes.forEach((node) => {
      if (
        node.data.label == 'catquiz' &&
        (
          !node.data.value?.scales?.parent?.scale ||
          node.data.value.scales.parent.scale == ''
        )
      ) {
        const invalidNode = findNode(node.id)
        if (invalidNode) {
          invalidNode.data.error = true
          globalwarning += 1
        }
      } else if (node.data.error) {
        const invalidNode = findNode(node.id);
        if (invalidNode) {
          delete invalidNode.data.error;
        }
      }
    })
  }
  return globalwarning
}
export default validateQuizGlobal;