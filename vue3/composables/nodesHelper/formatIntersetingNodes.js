// generate a new id

const  formatIntersetingNodes = (nodesIntersecting, node, intersectingNode,
    closestNode, insideStartingNode) => {
    if(nodesIntersecting){
        intersectingNode = { closestnode: closestNode, dropzone: node};
        node.data = { ...node.data, ...{
            opacity: '0.75',
            bgcolor: 'chartreuse',
            infotext: 'Drop to connect here',
          }
        }
    }else{
        node.data = { ...node.data, ...{
            opacity: '0.6',
            bgcolor: 'grey',
            infotext: 'New Staring node',
          }
        }
        if(node.id == 'dropzone_parent'){
            node.data.infotext = 'Drop zone Parent'
        }else if(node.id == 'dropzone_child'){
            node.data.infotext = 'Drop zone Child'
        }else if(node.id == 'dropzone_and'){
            node.data.infotext = 'And drop zone'
        }else {
            insideStartingNode = true;
        }
    }
    return {
        node: node,
        insideStartingNode: insideStartingNode,
        intersectingNode: intersectingNode,
    }
}

export default formatIntersetingNodes;