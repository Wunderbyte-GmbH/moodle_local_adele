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
          nextTick(() => {
            addNodes([startingNode])
          })
      })
    }
}

export default setStartingNode;