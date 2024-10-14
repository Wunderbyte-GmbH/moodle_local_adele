import setZoomLevel from './setZoomLevel'

interface WheelEventWithTarget extends WheelEvent {
  target: HTMLElement;
}

const onWheel = async (
  event: WheelEventWithTarget,
  zoomLockVaraible: boolean,
  viewport: HTMLElement,
  zoomTo: number
) : Promise<void> => {
  const isScrollTarget = event.target.closest('.vue-flow__pane')
  const isTrackpad = Math.abs(event.deltaY) < 2;
  const isZoomingGesture = event.ctrlKey || event.metaKey;
  const zoomingdirection = event.deltaY < 0 ? 'in' : 'out'
  if (
    isScrollTarget
  ) {
    if (isTrackpad && !isZoomingGesture) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    if(!zoomLockVaraible) {
      zoomLockVaraible = true
      await setZoomLevel(zoomingdirection, viewport, zoomTo)
      setTimeout(() => {
        zoomLockVaraible = false
      }, 500)
    }
  }
}

export default onWheel;