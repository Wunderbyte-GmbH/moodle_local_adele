import setZoomLevel from '../../../../composables/flowHelper/setZoomLevel';

jest.useFakeTimers();
describe('setZoomLevel', () => {

  let zoomLock, viewport, zoomTo;

  beforeEach(() => {
    zoomLock = { value: true };
    viewport = { value: { zoom: 0.5 } };
    zoomTo = jest.fn(() => Promise.resolve());
  });

  it('should zoom in to the next zoom step', async () => {
    await setZoomLevel('in', zoomLock, viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledWith(0.55, { duration: 500 });
    jest.runAllTimers();
    expect(zoomLock.value).toBe(true);
  });

  it('should zoom out to the previous zoom step', async () => {
    viewport.value.zoom = 0.55;
    await setZoomLevel('out', zoomLock, viewport, zoomTo);

    expect(zoomTo).toHaveBeenCalledWith(0.35, { duration: 500 });
    jest.runAllTimers();
    expect(zoomLock.value).toBe(true);
  });

  it('should set zoom to the smallest value if zooming out at the lowest level', async () => {
    viewport.value.zoom = 0.2;
    await setZoomLevel('out', zoomLock, viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledWith(0.25, { duration: 500 });
    jest.runAllTimers();
    expect(zoomLock.value).toBe(true);
  });

  it('should set zoom to the highest value if zooming in at the highest level', async () => {
    viewport.value.zoom = 1.5;
    await setZoomLevel('in', zoomLock, viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledWith(1.15, { duration: 500 });
    jest.runAllTimers();
    expect(zoomLock.value).toBe(true);
  });

  it('should lock zoom after timeout if zoomTo is not called', async () => {
    viewport.value.zoom = 0.55;
    await setZoomLevel('in', zoomLock, viewport, zoomTo);

    expect(zoomTo).toHaveBeenCalled();
    jest.advanceTimersByTime(800);
    expect(zoomLock.value).toBe(true);
  });

  it('should set undefined zoom step', async () => {
    viewport.value.zoom = 0.15;
    await setZoomLevel('out', zoomLock, viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledWith(1.15, { duration: 500 });
  });

});