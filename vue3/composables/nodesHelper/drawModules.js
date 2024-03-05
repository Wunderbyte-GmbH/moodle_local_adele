import removeModules from './removeModules'

const drawModules = async (learningpath, addNodes, removeNodes, draggedNode = null, deletedNodeId = null) => {
  if (learningpath.json.modules) {
    await removeModules(learningpath.json.tree, removeNodes)
    let allModules = []
    learningpath.json.modules.forEach(module => {
      let newModule = {
        type: 'module',
        zIndex: -10,
        position: { x: 0 , y: 0 },
        label: `module node`,
        draggable: false,
        data: module
      }
      newModule.data.opacity = '0.2'
      let insertModule = false
      let rightestNode = null
      let lowestNode = null
      newModule.id = module.id + '_module'

      learningpath.json.tree.nodes.forEach(node => {
        if (node.data.module == newModule.data.id &&
          deletedNodeId != node.id) {
          insertModule = true
          if (draggedNode && draggedNode.id == node.id) {
            node.position = draggedNode.position
          }

          if (newModule.position.x == 0 || newModule.position.x > node.position.x) {
            newModule.position.x = node.position.x
          }
          if (rightestNode == null || rightestNode.position.x < node.position.x) {
            rightestNode = node
          }
          if (newModule.position.y == 0 || newModule.position.y > node.position.y) {
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
          const height = Math.abs(newModule.position.y - lowestNode.position.y) + 400
          const width = Math.abs(newModule.position.x - rightestNode.position.x) + 420
          newModule.data.height = height + 'px'
          newModule.data.width = width + 'px'
          newModule.position.y -= 10
          newModule.position.x -= 10
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
