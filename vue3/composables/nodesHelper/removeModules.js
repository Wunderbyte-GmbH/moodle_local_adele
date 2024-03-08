// generate a new id
const  removeModules = (tree, removeNodes) => {
  if (removeNodes == null) {
    let only_custom_nodes = [];
    tree.nodes.forEach((node) => {
        if (node.type.indexOf('module') == -1) {
            only_custom_nodes.push(node);
        }
    })
    tree.nodes = only_custom_nodes;
    return tree
  }
  if (tree) {
    tree.nodes.forEach((node) => {
        if (node.type.indexOf('module') != -1) {
          removeNodes([node])
        }
    })
  }
  return 1
}

export default removeModules;