// Build flow-chart with edges and nodes
interface Node {
  id: string,
  draggable: boolean;
  deletable: boolean;
}

interface Edge {
  id: string,
  deletable: boolean;
}

interface FlowChart {
  nodes: Node[];
  edges: Edge[];
}

const  loadFlowChart = (flow: FlowChart, view: string): FlowChart => {
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