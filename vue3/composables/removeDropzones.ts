// generate a new id
interface Node {
  id: string,
  type: string;
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

const  removeDropzones = (tree: Tree): Tree => {
    tree.nodes = tree.nodes.filter((node) => node.type.indexOf('dropzone') === -1);
    tree.edges = tree.edges.filter((edge) => !edge.target.includes('dropzone_'));
    return tree;
}

export default removeDropzones;