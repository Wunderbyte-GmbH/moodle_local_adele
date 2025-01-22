interface Event {
  node: EventNode;
}

interface EventNode {
  id: string;
  position: Position;
  dimensions: Dimensions;
  data: EventNodeData;
}

interface Position {
  x: number;
  y: number;
}

interface Dimensions {
  width: number;
  height: number;
}

interface EventNodeData {
  animations: AnimationsData;
}

interface AnimationsData {
  seenrestriction: boolean;
  seencompletion: boolean;
}

type SetCenter = (x: number, y: number, options: { zoom: number; duration: number }) => void;

interface Store {
  state: {
    user: number;
    lpuserpathrelation: {
      user_id: number;
    };
  };
  dispatch: (action: string, payload: any) => void;
}

const onNodeClick = (event: Event, setCenter: SetCenter, store: Store) => {
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