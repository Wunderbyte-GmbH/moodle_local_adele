interface Position {
  x: number;
  y: number;
}

interface Dimensions {
  width: number;
  height: number;
}

interface NodeData {
  opacity: string;
  bgcolor: string;
  height: string;
  width: string;
  infotext?: string; // Optional property for infotext
}

interface Node {
  id: string;
  type: string;
  position: Position;
  label: string;
  data: NodeData;
  draggable: boolean;
  parentCourse: string;
  dimensions?: Dimensions; // Optional since it may not be set initially
}

interface Store {
  state: {
    view: string;
    strings: {
      composables_new_node: string;
    };
  };
}

let startingNode: Node = {
    id: 'starting_node',
    type: 'dropzone',
    position: { x: 0 , y: 0 },
    label: `DZ node`,
    data: {
      opacity: '0.6',
      bgcolor: 'grey',
      height: '200px',
      width: '400px',
    },
    draggable: true,
    parentCourse: '',
  }

const setStartingNode = (
  removeNodes: (ids: string[]) => void,
  nextTick: (callback: () => void) => void,
  addNodes: (nodes: Node[]) => void,
  nodes: Node[],
  skip: number,
  store: Store,
  backwards: boolean = false
): void => {
  if (store.state.view != 'teacher') {
      nextTick(() => {
          let rightStartingNode: number = 0
          let shifted: boolean = false
          //calculate starting node
          nodes.forEach((node) => {
            if(node.parentCourse == 'starting_node'  &&
              node.position.x >= rightStartingNode){
              rightStartingNode = node.position.x
              if(backwards && node.dimensions){
                  rightStartingNode += node.dimensions.width/2
              }
              shifted = true
            }
            if (node.id == 'starting_node') {
              startingNode.position.y = node.position.y
            }
          })
          if(shifted) {
              startingNode.position.x = rightStartingNode + skip
          }
          startingNode.data.infotext = store.state.strings.composables_new_node;
          removeNodes(['starting_node'])

          nodes.forEach((node) => {
            while (areNodesIntersecting(startingNode, node)) {
              startingNode.position.x +=150
            }
          })
          startingNode.position = {
            x: Math.round(startingNode.position.x / 150) * 150,
            y: Math.round(startingNode.position.y / 150) * 150,
          }
          nextTick(() => {
            addNodes([startingNode])
          })
      })
    }
}

const areNodesIntersecting = (node1: Node, node2: Node) => {
  const left_outmost_point_1: number = node1.position.x - 200
  const right_outmost_point_1: number = node1.position.x + 200
  const bottom_outmost_point_1: number = node1.position.y + 100
  const top_outmost_point_1: number = node1.position.y - 100

  const left_outmost_point_2: number = node2.position.x - (node2.dimensions?.width ?? 0)/2
  const right_outmost_point_2: number = node2.position.x +  (node2.dimensions?.width ?? 0)/2
  const bottom_outmost_point_2: number = node2.position.y + (node2.dimensions?.height ?? 0)/2
  const top_outmost_point_2: number = node2.position.y - (node2.dimensions?.height ?? 0)/2

  return (
      (
        left_outmost_point_1 >= left_outmost_point_2 && left_outmost_point_1 <= right_outmost_point_2 ||
        right_outmost_point_1 <= right_outmost_point_2 && right_outmost_point_1 >= left_outmost_point_2
      )
      &&
      (
        bottom_outmost_point_1 <= bottom_outmost_point_2 && bottom_outmost_point_1 >= top_outmost_point_2 ||
        top_outmost_point_1 >= top_outmost_point_2 && top_outmost_point_1 <= bottom_outmost_point_2
      )
  )
}

export default setStartingNode;