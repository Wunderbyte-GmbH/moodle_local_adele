// stepwise set the zomm level
import { MarkerType } from '@vue-flow/core'

const outerGraphDisplay = (edges, findNode, addEdges) => {
  let newmoduleedgesnames = [];
  edges.forEach(
    (edge) => {
      edge.data.hidden = true
      const source = findNode(edge.source)
      const target = findNode(edge.target)
      const edgename = source.data.module + '_module' + target.data.module + '_module'
      if (
        source.data.module !== target.data.module &&
        !newmoduleedgesnames.includes(edgename)
      ) {
        newmoduleedgesnames.push(edgename)
        const newEdge = {
          id: edgename,
          source: source.data.module + '_module',
          target: target.data.module + '_module',
          sourceHandle: 'source',
          targetHandle: 'target',
          style: {
            'stroke-width': 5,
          },
          markerEnd: MarkerType.ArrowClosed,
        };
        // Add the new edge
        addEdges([newEdge]);
        edges.push(newEdge)
      }
    }
  )
  return edges
}

export default outerGraphDisplay;