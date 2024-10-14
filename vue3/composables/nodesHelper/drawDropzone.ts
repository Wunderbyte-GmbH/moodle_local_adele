interface StoreState {
  strings: {
    composables_drop_zone_parent: string;
    composables_drop_zone_child: string;
    composables_drop_zone_add: string;
    composables_drop_zone_or: string;
  };
}

interface Store {
  state: StoreState;
}

interface Position {
  x: number;
  y: number;
}

interface ClosestNode {
  id: string;
  position: Position;
  dimensions: {
    height: number;
  };
  childCourse?: string[];
  parentCourse?: string[];
  computedPosition: Position;
}

interface DropZoneNode {
  id: string;
  type: string;
  position: Position;
  label: string;
  data: {
    opacity: string;
    bgcolor: string;
    height: string;
    infotext?: string;
    width?: number;
  };
  style: {
    zIndex: number;
  };
}

interface DropZoneEdge {
  id: string;
  source: string;
  sourceHandle: string;
  target: string;
  targetHandle: string;
  type: string;
}

interface NewDrop {
  nodes: DropZoneNode[];
  edges: DropZoneEdge[];
}

interface NodeData {
  opacity: string;
  bgcolor: string;
  height: string;
  infotext?: string;
  width?: number;
}

interface DropZoneCourseNode {
  name: string;
  positionY: number;
  positionX: number;
  type: string;
  width?: number;
}

const  drawDropzone = (closestNode: ClosestNode, store: Store) => {
    let newDrop: NewDrop = {
        nodes: [],
        edges: [],
    }
    const dropZoneCourseNodes:  { [key: string]: DropZoneCourseNode } = {
        parent: {
            name: store.state.strings.composables_drop_zone_parent,
            positionY: -250,
            positionX: 0,
            type: 'dropzone',
        },
        child: {
            name: store.state.strings.composables_drop_zone_child,
            positionY: 50 + closestNode.dimensions.height,
            positionX: 0,
            type: 'dropzone',
        },
        and: {
            name: store.state.strings.composables_drop_zone_add,
            positionY: 200,
            positionX: 450,
            type: 'conditionaldropzone',
        },
        or: {
            name: store.state.strings.composables_drop_zone_or,
            positionY: 300,
            positionX: -350,
            type: 'conditionaldropzone',
        }
    }
    let data: NodeData = {
        opacity: '0.6',
        bgcolor: 'grey',
        height: '200px',
    }

    //check if closest node has childerns TODO
    for (const key in dropZoneCourseNodes){
        data.infotext = dropZoneCourseNodes[key].name
        data.width = dropZoneCourseNodes[key].width
        let position: Position = {
            x: 0,
            y: 0
        }
        if (key != 'and' &&key != 'or') {
            position = {
                x: getOffsetX(closestNode, key),
                y: closestNode.position.y + dropZoneCourseNodes[key].positionY
            }
        } else {
            position = {
                x: closestNode.position.x + dropZoneCourseNodes[key].positionX,
                y: getOffsetY(closestNode)
            }
        }
        const newNode: DropZoneNode = {
            id: 'dropzone_' + key,
            type: dropZoneCourseNodes[key].type,
            position: position,
            label: `default node`,
            data: data,
            style: {
              zIndex: 1000,
            },
        }
        newDrop.nodes.push(newNode);

        let targetHandle = 'source_and'
        let sourceHandle =  'target'

        if(key == 'child'){
            targetHandle = 'target_and'
            sourceHandle =  'source'
        }

        if (key != 'and' &&key != 'or') {
            const newEdge: DropZoneEdge = {
                id: `${closestNode.id}-${key}`,
                source: closestNode.id,
                sourceHandle: sourceHandle,
                target: newNode.id,
                targetHandle: targetHandle,
                type: 'default',
            };
            // Add the new edge
            newDrop.edges.push(newEdge);
        }
    }
    return newDrop
}

const getOffsetX = (closestNode: ClosestNode, relation: string): number => {
    let relationHandle = closestNode.childCourse
    if(relation == 'parent'){
      relationHandle = closestNode.parentCourse
    }
    if(relationHandle != undefined && (relationHandle.length == 0 ||
    relationHandle.indexOf('starting_node') != -1)){
      return closestNode.position.x
    }
    return closestNode.position.x + 500
}

const getOffsetY = (closestNode: ClosestNode): number => {
    return closestNode.computedPosition.y + closestNode.dimensions.height/2 - 100
}

export default drawDropzone;