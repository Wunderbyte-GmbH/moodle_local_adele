// stepwise set the zomm level

const zoomSteps = [ 0.2, 0.25, 0.35, 0.55, 0.85, 1.15, 1.5]

const setZoomLevel = async (action, viewport, zoomTo) => {
  try {
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
    zoomTo(newViewport, { duration: 500})
    return newViewport
  } catch (error) {
    console.error("Error during zoom operation:", error)
  }
}

export default setZoomLevel;