// stepwise set the zomm level

const zoomSteps = [ 0.2, 0.25, 0.35, 0.55, 0.85, 1.15, 1.5]

const setZoomLevel = async (action, zoomLock, viewport, zoomTo) => {
  try {
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
        newViewport = zoomSteps[currentStepIndex - 1]
      }
    } else if (action === 'out') {
      if (currentStepIndex > 0) {
        newViewport = zoomSteps[currentStepIndex-1];
      } else {
        newViewport = zoomSteps[0]
      }
    }
    if (newViewport != undefined) {
      zoomTo(newViewport, { duration: 500}).then(() => {
        zoomLock.value = true
      })
    }
    return newViewport
  } catch (error) {
    console.error("Error during zoom operation:", error)
    zoomLock.value = true;
  }
}

export default setZoomLevel;