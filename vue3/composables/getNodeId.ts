interface Node {
  id: string;
}

// Generate a new ID based on the prefix and an array of nodes
const getNodeId = (prefix: string, nodes: Node[]): string => {
    let highestId = 1;

    nodes.forEach((node: Node) => {
        if (node.id.startsWith(prefix)) {
            const currentId = Number(node.id.slice(prefix.length));
            if (highestId <= currentId) {
                highestId = currentId + 1;
            }
        }
    });
    return `${prefix}${highestId}`;
};

export default getNodeId;