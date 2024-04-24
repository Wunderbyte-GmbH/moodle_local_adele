// stepwise set the zomm level

const zoomSteps = [ 0.2, 0.35, 0.7, 1.5]

const setZoomLevel = async (action, zoomLock, viewport, zoomTo) => {
  zoomLock.value = false
  let newViewport = viewport.value.zoom
  let currentStepIndex = zoomSteps.findIndex(step => newViewport < step);
  if (currentStepIndex === -1) {
    currentStepIndex = zoomSteps.length;
  }
  if (action === 'in') {
    if (currentStepIndex < zoomSteps.length) {
      newViewport = zoomSteps[currentStepIndex];
    } else {
      newViewport = zoomSteps[currentStepIndex - 2]
    }
  } else if (action === 'out') {
    if (currentStepIndex > 0) {
      newViewport = zoomSteps[currentStepIndex - 1];
    } else {
      newViewport = zoomSteps[zoomSteps.length - 2]
    }
  }
  if (newViewport != undefined) {
    await zoomTo(newViewport, { duration: 500}).then(() => {
      zoomLock.value = true
    })
  }
}

export default setZoomLevel;