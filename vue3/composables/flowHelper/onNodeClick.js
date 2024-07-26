// stepwise set the zomm level

const onNodeClick = (event, zoomLock, setCenter) => {
  zoomLock.value = false
  setCenter(
    event.node.position.x + event.node.dimensions.width/2,
    event.node.position.y + event.node.dimensions.height/2,
    { zoom: 1, duration: 500}
  ).then(() => {
    zoomLock.value = true
  })
}

export default onNodeClick;