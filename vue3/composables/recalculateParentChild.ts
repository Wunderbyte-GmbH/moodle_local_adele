interface Node {
  id: string;
  type: string;
  data?: {
    course_node_id: string | string[];
  };
  parentNodes?: string[];
  childNodes?: string[];
  [key: string]: any;
}

interface Edge {
  id: string,
  sourceHandle: string;
  source: string;
  target: string;
}

interface Tree {
  nodes: Node[];
  edges: Edge[];
}

const  recalculateParentChild = (tree: Tree, parentNode: string, childNode: string, startNode: string): Tree => {
    tree.nodes.forEach((node) => {
        if (
          node.type == 'custom' ||
          node.type == 'orcourses'
        ) {
            node[parentNode] = []
            node[childNode] = []
            tree.edges.forEach((edge) => {
                if (!edge.sourceHandle.includes('or')) {
                    if (edge.source == node.id &&
                        !node[childNode].includes(edge.target)) {
                        node[childNode].push(edge.target);
                    }
                    if (edge.target == node.id &&
                        !node[parentNode].includes(edge.source)) {
                        node[parentNode].push(edge.source);
                    }
                }
            })
            if(node[parentNode].length == 0){
                node[parentNode].push(startNode);
            }
            if (
              node.data?.course_node_id
            ) {
              if (node.data.course_node_id.length > 1) {
                node.type = 'orcourses'
              } else {
                node.type = 'custom'
              }
            }
        }
    })
    return tree;
}
export default recalculateParentChild;