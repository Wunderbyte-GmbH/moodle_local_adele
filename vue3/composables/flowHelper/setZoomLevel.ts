const zoomSteps: number[] = [ 0.2, 0.25, 0.35, 0.55, 0.85, 1.15, 1.5];

interface Viewport {
  zoom: number;
}

const setZoomLevel = async (
  action: 'in' | 'out',
  viewport: Viewport,
  zoomTo: (zoomLevel: number, options: { duration: number }) => void
): Promise<number | undefined> => {
  try {
    let newViewport = parseFloat(viewport.zoom.toFixed(2))

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
      if (currentStepIndex > 1) {
        newViewport = zoomSteps[currentStepIndex-2];
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