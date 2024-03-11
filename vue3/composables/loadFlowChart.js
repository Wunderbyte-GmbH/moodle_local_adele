// Build flow-chart with edges and nodes
const  loadFlowChart = (flow, view) => {
    if (view == 'teacher') {
      flow.nodes.forEach((nodes) => {
        nodes.draggable = false
        nodes.deletable = false
      })
      flow.edges.forEach((edge) => {
        edge.deletable = false
      })
    }
    return flow
}

export default loadFlowChart;