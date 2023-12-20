// generate a new id
const  removeDropzoneNodes = (nodes) => {
    let only_custom_nodes = [];
    nodes.forEach((node) => {
        if ( node.type != 'dropzone') {
            only_custom_nodes.push(node);
        }
    })
    return only_custom_nodes;
}

export default removeDropzoneNodes;