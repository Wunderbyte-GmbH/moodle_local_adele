import onNodeClick from '../../../../composables/flowHelper/onNodeClick';

describe('onNodeClick', () => {
  let event;
  let setCenter;
  let store;

  beforeEach(() => {
    event = {
      node: {
        id: 'test-node',
        position: { x: 100, y: 200 },
        dimensions: { width: 50, height: 50 },
        data: {
          animations: {
            seenrestriction: false,
            seencompletion: false,
          }
        }
      }
    };
    setCenter = jest.fn(() => Promise.resolve());
    store = {
      state: {
        user: 1,
        lpuserpathrelation: { user_id: 1 }
      },
      dispatch: jest.fn()
    };
  });

  it('should should set the center to the given node', async () => {
    await onNodeClick(event, setCenter, store);
    expect(setCenter).toHaveBeenCalledWith(
      125, // 100 + 50/2
      225, // 200 + 50/2
      { zoom: 1, duration: 500 }
    );

    await setCenter();
  });

  it('should trigger the web service', async () => {
    await onNodeClick(event, setCenter, store);

    expect(store.dispatch).toHaveBeenCalledWith('setNodeAnimations', {
      nodeid: 'test-node',
      animations: {
        seenrestriction: true,
        seencompletion: true,
      },
    });
  });

  it('should not trigger the web service if no conditions are met', async () => {
    event.node.data.animations.seenrestriction = true;
    event.node.data.animations.seencompletion = true;
    await onNodeClick(event, setCenter, store);
    expect(store.dispatch).not.toHaveBeenCalled();

    event.node.data.animations = {};
    await onNodeClick(event, setCenter, store);
    expect(store.dispatch).not.toHaveBeenCalled();
  });

  it('should not trigger the web service if no conditions are met', async () => {
    event.node.data = true;
    const result = await onNodeClick(event, setCenter, store);
    expect(store.dispatch).not.toHaveBeenCalled();
    expect(result).toBe(1);
  });
});