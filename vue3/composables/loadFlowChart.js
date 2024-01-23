// Build flow-chart with edges and nodes

import { useVueFlow } from '@vue-flow/core';
import { useStore } from 'vuex';

const  loadFlowChart = (flow) => {
    if (flow) {
        const store = useStore();
        const { setNodes, setEdges } = useVueFlow();
        if (store.state.view == 'teacher') {
          flow.nodes.forEach((nodes) => {
            nodes.draggable = false
          })
        }
        setNodes(flow.nodes)
        setEdges(flow.edges)
    }
}

export default loadFlowChart;