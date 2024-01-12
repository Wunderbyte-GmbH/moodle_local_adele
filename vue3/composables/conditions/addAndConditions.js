import addCustomEdge from "../addCustomEdge";

const  addAndConditions = (intersectingnode, edges, id) => {
  // Get child and parent nodes and edges.
  const parentNodes = intersectingnode.closestnode.parentCourse
  const childNodes = intersectingnode.closestnode.childCourse
  let newEdges = [];
  let newRestrictions = intersectingnode.closestnode.restriction;
  let newOtherRestrictions = [];
  let newCompletions = intersectingnode.closestnode.completion;

  edges.value.forEach((edge) => {
    if (edge.id.indexOf('-') == -1 &&
      edge.id.indexOf(intersectingnode.closestnode.id) != -1) {
        const destination = edge.id.replace(intersectingnode.closestnode.id, '');
        if (intersectingnode.closestnode.id != edge.source) {
          newEdges.push(addCustomEdge(id,destination))
        }else {
          newEdges.push(addCustomEdge(destination, id))
          newOtherRestrictions.push(destination)
        }
    }
  })

  return {
    parentNodes: parentNodes,
    childNodes: childNodes,
    newEdges: newEdges,
    newRestrictions: newRestrictions,
    newCompletions: newCompletions,
    newOtherRestrictions: newOtherRestrictions
  };
}

export default addAndConditions;