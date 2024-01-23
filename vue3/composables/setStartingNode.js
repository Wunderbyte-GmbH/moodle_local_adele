// generate a new id

import { useStore } from 'vuex';

let startingNode = {
    id: 'starting_node',
    type: 'dropzone',
    position: { x: 0 , y: 0 },
    label: `DZ node`,
    data: {
      opacity: '0.6',
      bgcolor: 'grey',
      infotext: 'New Starting node',
      height: '200px',
      width: '400px',
    },
    draggable: false,
    parentCourse: '',
  }

const  setStartingNode = (removeNodes, nextTick, addNodes, nodes, skip, backwards = false) => {
  const store = useStore()  
  removeNodes(['starting_node'])
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
          })
          if(shifted) {
              startingNode.position.x = rightStartingNode + skip
          }
          addNodes([startingNode])
      })
    }

}

export default setStartingNode;