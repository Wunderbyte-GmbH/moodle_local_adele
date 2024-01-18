// Build flow-chart with edges and nodes

import { useVueFlow } from '@vue-flow/core';

const  loadFlowChart = (flow) => {
    if (flow) {
        const { setNodes, setEdges } = useVueFlow();
        setNodes(flow.nodes)
        setEdges(flow.edges)
    }
}

export default loadFlowChart;