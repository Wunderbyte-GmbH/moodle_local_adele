// Build flow-chart with edges and nodes
const  loadFlowChart = (flow, view) => {
    if (flow) {
        if (view == 'teacher') {
          flow.nodes.forEach((nodes) => {
            nodes.draggable = false
            nodes.deletable = false
          })
          flow.edges.forEach((edge) => {
            edge.deletable = false
          })
        }
        return {
          nodes: flow.nodes,
          edges: flow.edges,
        }
    }
}

export default loadFlowChart;