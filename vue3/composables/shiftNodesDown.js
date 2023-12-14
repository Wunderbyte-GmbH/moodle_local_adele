// generate a new id
const  shiftNodesDown = (newNodeId, nodes) => {
    let shiftedNodes = [newNodeId]
    while(true){
        shiftedNodes.forEach((shiftedNode) => {
            nodes.forEach((node) => {
                if(node.type == 'custom' && node.parentCourse.includes(shiftedNode)){
                    node.position.y += 500 + node.dimensions.height/4
                    if(node.childCourse.length > 0 ){
                        node.childCourse.forEach((child) => {
                        shiftedNodes.push(child)
                        })
                    }
                }
            })
            const index = shiftedNodes.indexOf(shiftedNode);
            if (index !== -1) {
                shiftedNodes.splice(index, 1);
            }
        })
        break
    }
}

export default shiftNodesDown;