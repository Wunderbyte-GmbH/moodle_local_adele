// generate a new id

let startingNode = {
    id: 'starting_node',
    type: 'dropzone',
    position: { x: 0 , y: 0 },
    label: `DZ node`,
    data: {
      opacity: '0.6',
      bgcolor: 'grey',
      height: '200px',
      width: '400px',
    },
    draggable: true,
    parentCourse: '',
  }

const  setStartingNode = (removeNodes, nextTick, addNodes, nodes, skip, store, backwards = false) => {
  if (store.state.view != 'teacher') {
      nextTick(() => {
          let rightStartingNode = 0
          let shifted = false
          //calculate starting node
          nodes.forEach((node) => {
            if(node.parentCourse == 'starting_node'  &&
              node.position.x >= rightStartingNode){
              rightStartingNode = node.position.x
              if(backwards){
                  rightStartingNode += node.dimensions.width/2
              }
              shifted = true
            }
            if (node.id == 'starting_node') {
              startingNode.position.y = node.position.y
            }
          })
          if(shifted) {
              startingNode.position.x = rightStartingNode + skip
          }
          startingNode.data.infotext = store.state.strings.composables_new_node;
          removeNodes(['starting_node'])

          nodes.forEach((node) => {
            while (areNodesIntersecting(startingNode, node)) {
              startingNode.position.x +=150
            }
          })
          startingNode.position = {
            x: Math.round(startingNode.position.x / 150) * 150,
            y: Math.round(startingNode.position.y / 150) * 150,
          }
          nextTick(() => {
            addNodes([startingNode])
          })
      })
    }
}

const areNodesIntersecting = (node1, node2) => {
  const left_outmost_point_1 = node1.position.x - 200
  const right_outmost_point_1 = node1.position.x + 200
  const bottom_outmost_point_1 = node1.position.y + 100
  const top_outmost_point_1 = node1.position.y - 100

  const left_outmost_point_2 = node2.position.x - node2.dimensions.width/2
  const right_outmost_point_2 = node2.position.x +  node2.dimensions.width/2
  const bottom_outmost_point_2 = node2.position.y + node2.dimensions.height/2
  const top_outmost_point_2 = node2.position.y - node2.dimensions.height/2

  return (
      (
        left_outmost_point_1 >= left_outmost_point_2 && left_outmost_point_1 <= right_outmost_point_2 ||
        right_outmost_point_1 <= right_outmost_point_2 && right_outmost_point_1 >= left_outmost_point_2
      )
      &&
      (
        bottom_outmost_point_1 <= bottom_outmost_point_2 && bottom_outmost_point_1 >= top_outmost_point_2 ||
        top_outmost_point_1 >= top_outmost_point_2 && top_outmost_point_1 <= bottom_outmost_point_2
      )
  )
}

export default setStartingNode;