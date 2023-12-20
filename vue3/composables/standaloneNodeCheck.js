// generate a new id
const  standaloneNodeCheck = (tree) => {
    if (tree.nodes.length == 1) {
        return false;
    }
    let standalone = false;
    let node_connected = []
    tree.edges.forEach((edge) => {
        if (!node_connected.includes(edge.source)) {
            node_connected.push(edge.source);
        }
        if (!node_connected.includes(edge.target)) {
            node_connected.push(edge.target);
        }
    })
    tree.nodes.forEach((node) => {
        if (!node_connected.includes(node.id)) {
            standalone = true;
        }
    })
    return standalone;
}
export default standaloneNodeCheck;