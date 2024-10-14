interface Node {
  id: string,
}

interface Edge {
  id: string,
  target: string;
  source: string;
}

interface Tree {
  nodes: Node[];
  edges: Edge[];
}

const  standaloneNodeCheck = (tree: Tree): boolean => {
    if (tree.nodes.length === 1) {
        return false;
    }
    let node_connected = new Set<string>();

    tree.edges.forEach((edge) => {
      node_connected.add(edge.source);
      node_connected.add(edge.target);
    });
    for (const node of tree.nodes) {
      if (!node_connected.has(node.id)) {
        return true;
      }
    }
    return false;
}
export default standaloneNodeCheck;