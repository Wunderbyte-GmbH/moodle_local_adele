// generate a new id
const  validateQuizGlobal = (conditions, findNode, quizsetting) => {
  let globalwarning = 0
  if (quizsetting == 'all_quiz_global') {
    conditions.nodes.forEach((node) => {
      if (
        node.data.label == 'catquiz' &&
        (
          !node.data.value.scales.parent.scale ||
          node.data.value.scales.parent.scale == ''
        )
      ) {
        let invalidNode = findNode(node.id)
        invalidNode.data.error = true
        globalwarning += 1
      } else if (node.data.error) {
        let invalidNode = findNode(node.id)
        delete(invalidNode.data.error)
      }
    })
  }
  return globalwarning
}
export default validateQuizGlobal;