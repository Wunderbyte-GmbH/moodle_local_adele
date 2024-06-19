import removeModules from './removeModules'
import findNodeDimensions from './findNodeDimensions'
import darkenColor from './darkenColor'

const drawModules = async (learningpath, addNodes, removeNodes, findNode, draggedNode = null, deletedNodeId = null) => {
  if (learningpath.json.modules) {
    await removeModules(learningpath.json.tree, removeNodes)
    let allModules = []
    let userpath = false

    learningpath.json.modules.forEach( async module => {
      let newModule = {
        type: 'module',
        position: {},
        label: `module node`,
        draggable: false,
        selectable: false,
        data: module
      }
      let insertModule = false
      let rightestNode = null
      let lowestNode = null
      newModule.id = module.id + '_module'

      let active = false
      learningpath.json.tree.nodes.forEach(node => {
        if (node.data.module == newModule.data.id &&
          deletedNodeId != node.id) {
            if (node.data.completion ) {
              userpath = true
              if (node.data.completion.singlerestrictionnode.length == 0) {
                active = true
              } else {
                for (let key in node.data.completion.singlerestrictionnode) {
                  if (node.data.completion.singlerestrictionnode[key]) {
                    active = true
                  }
                }
              }
            }

          insertModule = true
          if (draggedNode && draggedNode.id == node.id) {
            node.position = draggedNode.position
          }
          if (newModule.position.x == undefined || newModule.position.x > node.position.x) {
            newModule.position.x = node.position.x
          }

          if (rightestNode == null || rightestNode.position.x < node.position.x) {
            rightestNode = node
          }
          if (newModule.position.y == undefined || newModule.position.y > node.position.y) {
            newModule.position.y = node.position.y
          }
          if (lowestNode == null || lowestNode.position.y < node.position.y) {
            lowestNode = node
          }
        }
      })

      if (insertModule) {

        // Check if rightestNode and lowestNode are assigned values
        if (rightestNode && lowestNode) {
          if (userpath && !active) {
            newModule.data.color_inactive = darkenColor(newModule.data.color, 0.5)
            newModule.data.opacity = '0.6'
            newModule.zIndex = 1
          } else {
            newModule.data.opacity = '0.2'
            newModule.zIndex = -10
          }

          const lowestNodeDimensions = findNode(lowestNode.id)
          let lowestNodeHeight = 0
          if (lowestNodeDimensions.dimensions) {
            lowestNodeHeight = lowestNodeDimensions.dimensions.height
          }
          const height = Math.abs(newModule.position.y - lowestNode.position.y) + lowestNodeHeight + 80
          const width = Math.abs(newModule.position.x - rightestNode.position.x) + 500
          newModule.data.height = height + 'px'
          newModule.data.width = width + 'px'
          newModule.position.y -= 40
          newModule.position.x -= 50
        }
        allModules.push(newModule)
      } else {
        removeNodes([module.id + '_module'])
      }
    });
    addNodes(allModules)
  }
}

export default drawModules;
