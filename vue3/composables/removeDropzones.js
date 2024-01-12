// generate a new id
const  removeDropzones = (tree) => {
    let only_custom_nodes = [];
    tree.nodes.forEach((node) => {
        if (node.type.indexOf('dropzone') == -1) {
            only_custom_nodes.push(node);
        }
    })
    tree.nodes = only_custom_nodes;
    let only_custom_edges = [];
    tree.edges.forEach((edge) => {
        if ( !edge.target.includes('dropzone_')) {
            only_custom_edges.push(edge);
        }
    })
    tree.edges = only_custom_edges;
    return tree;
}

export default removeDropzones;