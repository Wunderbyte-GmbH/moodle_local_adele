type removeEdges = (id: string) =>void;

interface Edge {
  id: string;
  data: EdgeData;
}

interface EdgeData {
  hidden: boolean;
}

const innerGraphDisplay = (edges: Edge[], removeEdges: removeEdges): Edge[] => {
  let newedges: Edge[] = []
  edges.forEach(
    (edge) => {
      if (edge.data == undefined) {
        removeEdges(edge.id)
      } else {
        edge.data.hidden = false
        newedges.push(edge)
      }
    }
  )
  return newedges
}

export default innerGraphDisplay;