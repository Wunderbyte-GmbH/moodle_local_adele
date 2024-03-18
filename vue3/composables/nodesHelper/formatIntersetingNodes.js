const  formatIntersetingNodes = (nodesIntersecting, node, intersectingNode,
    closestNode, insideStartingNode, store) => {
    if(nodesIntersecting){
        intersectingNode = { closestnode: closestNode, dropzone: node};
        node.data = { ...node.data, ...{
            opacity: '0.75',
            bgcolor: 'chartreuse',
            infotext: store.state.strings.completion_drop_here,
          }
        }
    }else{
        node.data = { ...node.data, ...{
            opacity: '0.6',
            bgcolor: 'grey',
            infotext: store.state.strings.composables_new_node,
          }
        }
        if(node.id == 'dropzone_parent'){
            node.data.infotext = store.state.strings.composables_drop_zone_parent
        }else if(node.id == 'dropzone_child'){
            node.data.infotext = store.state.strings.composables_drop_zone_child
        }else if(node.id == 'dropzone_and'){
            node.data.infotext = store.state.strings.composables_drop_zone_add
        }else if(node.id == 'dropzone_or'){
            node.data.infotext = store.state.strings.composables_drop_zone_or
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