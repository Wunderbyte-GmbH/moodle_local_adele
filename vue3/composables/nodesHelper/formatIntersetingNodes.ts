interface NodeData {
  opacity?: string;
  bgcolor?: string;
  infotext?: string;
}

interface Node {
  id: string;
  data: NodeData
}

interface StoreState {
  strings: {
    completion_drop_here: string;
    composables_new_node: string;
    composables_drop_zone_parent: string;
    composables_drop_zone_child: string;
    composables_drop_zone_add: string;
    composables_drop_zone_or: string;
  };
}

interface Store {
  state: StoreState;
}

interface IntersectingNode {
  closestnode: string;
  dropzone: Node;
}

const  formatIntersetingNodes = (
    nodesIntersecting: boolean, node: Node, intersectingNode: IntersectingNode | null,
    closestNode: string, insideStartingNode: boolean, store: Store
  ) => {
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