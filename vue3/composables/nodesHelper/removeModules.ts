interface Node {
  id: string;
  type: string
}

interface Tree {
  nodes: Node[];
}

const  removeModules = (tree: Tree, removeNodes?: (nodes: Node[]) => void): Tree | number => {
  if (removeNodes == null) {
    tree.nodes = tree.nodes.filter(node => !node.type.includes('module'));
    return tree;
  }
  if (tree) {
    const moduleNodes = tree.nodes.filter(node => node.type.includes('module'));
    if (moduleNodes.length > 0) {
      removeNodes(moduleNodes);
    }
  }
  return 1
}

export default removeModules;