// generate a new id
const  addStageCompletions = (node) => {
  if (node.completion && node.completion.nodes) {
    node.completion.nodes.forEach((completion_node) => {
      if (completion_node.type == 'custom' && completion_node.data.label == 'course_completed' &&
        completion_node.data.value == undefined) {
          completion_node.data.value = {
            min_courses: 1,
          }
      }
    })
  }
    return node;
}

export default addStageCompletions;