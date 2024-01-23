// Build flow-chart with edges and nodes
import { useVueFlow } from '@vue-flow/core';

const  loadFlowChart = (flow, view) => {
    if (flow) {
        const { setNodes, setEdges } = useVueFlow();
        if (view == 'teacher') {
          flow.nodes.forEach((nodes) => {
            nodes.draggable = false
            nodes.deletable = false
          })
          flow.edges.forEach((edge) => {
            edge.deletable = false
          })
        }
        setNodes(flow.nodes)
        setEdges(flow.edges)
    }
}

export default loadFlowChart;