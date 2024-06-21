// stepwise set the zomm level

const innerGraphDisplay = (edges, removeEdges) => {
  let newedges = []
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