// generate a new id
const  recalculateParentChild = (tree, parentNode, childNode, startNode) => {
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
        }
    })
    return tree;
}
export default recalculateParentChild;