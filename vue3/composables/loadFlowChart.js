// Build flow-chart with edges and nodes

import { useVueFlow } from '@vue-flow/core';

const  loadFlowChart = (flow) => {
    if (flow) {
        const { setNodes, setEdges } = useVueFlow();
        const [x = 0, y = 0] = flow.position
        setNodes(flow.nodes)
        setEdges(flow.edges)
    }
}

export default loadFlowChart;