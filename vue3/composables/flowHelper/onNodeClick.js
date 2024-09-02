// stepwise set the zomm level
const onNodeClick = (event, zoomLock, setCenter, store) => {

  zoomLock.value = false
  setCenter(
    event.node.position.x + event.node.dimensions.width/2,
    event.node.position.y + event.node.dimensions.height/2,
    { zoom: 1, duration: 500}
  ).then(() => {
    zoomLock.value = true
  })
  if (event.node.data.animations) {
    let triggerws = false
    if (
      event.node.data.animations.seenrestriction  == false
    ) {
      triggerws = true
      event.node.data.animations.seenrestriction = true
    }
    if (
      event.node.data.animations.seencompletion == false
    ) {
      triggerws = true
      event.node.data.animations.seencompletion = true
    }
    if (
      triggerws &&
      store.state.user == store.state.lpuserpathrelation.user_id
    ) {
      store.dispatch('setNodeAnimations',{
        nodeid: event.node.id,
        animations: event.node.data.animations
      })
    }

  }
  return 1
}

export default onNodeClick;