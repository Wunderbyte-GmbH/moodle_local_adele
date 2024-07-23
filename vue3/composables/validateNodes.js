// generate a new id
const  validateNodes = (conditions, findNode) => {
  let invalidNodes = false
  conditions.nodes.forEach((node) => {
    if (
      (
        node.data.label == 'catquiz' ||
        node.data.label == 'modquiz'
      ) &&
      (
        node.data.value == null ||
        (
          node.data.value.testid == null &&
          node.data.value.quizid == null
        )
      )
    ) {
      let invalidNode = findNode(node.id)
      invalidNode.data.error = true
      invalidNodes = true
    } else if (node.data.error) {
      let invalidNode = findNode(node.id)
      delete(invalidNode.data.error)
    }
  })
  return invalidNodes
}
export default validateNodes;