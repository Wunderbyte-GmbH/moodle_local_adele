// generate a new id

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

const  setStartingNode = (removeNodes, nextTick, addNodes, nodes, backwards = false) => {
    removeNodes(['starting_node'])
    nextTick(() => {
        let rightStartingNode = 0
        let shifted = false
        //calculate starting node 
        console.log(nodes)
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
            startingNode.position.x = rightStartingNode + 600
        }
        addNodes([startingNode])
    })
}

export default setStartingNode;