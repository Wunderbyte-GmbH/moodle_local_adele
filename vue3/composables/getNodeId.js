// generate a new id
const  getNodeId = (prefix, nodes) => {
    let highestId = 1;
    nodes.forEach((node) => {
    if (node.id.includes(prefix)) {
        const currentId = Number(node.id.slice(node.id.indexOf('_') + 1));
        if (highestId <= currentId) {
            highestId = currentId + 1;
        }
    }
    });

    return `${prefix}${highestId}`;
}

export default getNodeId;