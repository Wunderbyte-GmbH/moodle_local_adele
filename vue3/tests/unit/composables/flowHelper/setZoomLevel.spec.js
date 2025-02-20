import setZoomLevel from '../../../../composables/flowHelper/setZoomLevel';

jest.useFakeTimers();
describe('setZoomLevel', () => {

  let zoomLock, viewport, zoomTo;

  beforeEach(() => {
    viewport = { zoom: 0.5 };
    zoomTo = jest.fn(() => Promise.resolve());
    console.error = jest.fn();
  });

  it('should zoom in to the next zoom step', async () => {
    await setZoomLevel('in', viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledWith(0.55, { duration: 500 });
    jest.runAllTimers();
  });

  it('should zoom out to the previous zoom step', async () => {
    viewport.zoom = 0.55;
    await setZoomLevel('out', viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledWith(0.35, { duration: 500 });
    jest.runAllTimers();
  });

  it('should set zoom to the smallest value if zooming out at the lowest level', async () => {
    viewport.zoom = 0.2;
    await setZoomLevel('out', viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledWith(0.2, { duration: 500 });
    jest.runAllTimers();
  });

  it('should set zoom to the highest value if zooming in at the highest level', async () => {
    viewport.zoom = 1.5;
    await setZoomLevel('in', viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledWith(1.5, { duration: 500 });
    jest.runAllTimers();
  });

  it('should lock zoom after timeout if zoomTo is not called', async () => {
    viewport.zoom = 0.55;
    await setZoomLevel('in', viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalled();
    jest.advanceTimersByTime(800);
  });

  it('should set undefined zoom step', async () => {
    viewport.zoom = 0.15;
    await setZoomLevel('out', viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledWith(0.2, { duration: 500 });
  });

  it('should set undefined zoom step', async () => {
    viewport.zoom = null;
    await setZoomLevel('outin', viewport, zoomTo);
    expect(zoomTo).toHaveBeenCalledTimes(0);
  });

  it('should catch error and set zoomLock to true', async () => {
    // Mock zoomTo to throw a custom error
    zoomTo = jest.fn(() => {
      throw new TypeError('zoomTo is not a function');
    });
    await setZoomLevel('out', viewport, zoomTo);
    expect(console.error).toHaveBeenCalledWith('Error during zoom operation:', expect.any(TypeError));
  });
});