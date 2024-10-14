// stepwise set the zomm level
const onNodeClick = (event, setCenter, store) => {
  setCenter(
    event.node.position.x + event.node.dimensions.width/2,
    event.node.position.y + event.node.dimensions.height/2,
    { zoom: 1, duration: 500}
  )
  if (event.node.data.animations  &&
    store.state.user == store.state.lpuserpathrelation.user_id
  ) {
    let triggerws = false
    let animations = JSON.parse(JSON.stringify(event.node.data.animations));
    if (
      animations.seenrestriction  == false
    ) {
      triggerws = true
      animations.seenrestriction = true
    }
    if (
      animations.seencompletion == false
    ) {
      triggerws = true
      animations.seencompletion = true
    }
    if (
      triggerws
    ) {
      store.dispatch('setNodeAnimations',{
        nodeid: event.node.id,
        animations: animations
      })
    }

  }
  return 1
}

export default onNodeClick;