// generate a new id
const  drawDropzone = (closestNode) => {
    let newDrop = {
        nodes: [],
        edges: [],
    }
    const dropZoneCourseNodes = {
        parent: {
            name: 'Drop zone Parent',
            positionY: -350,
            positionX: 0,
            type: 'dropzone',
        },
        child: {
            name: 'Drop zone Child',
            positionY: 350,
            positionX: 0,
            type: 'dropzone',
        },
        and: {
            name: 'And drop zone',
            positionY: 200,
            positionX: 450,
            type: 'adddropzone',
        }
        }
        let data = {
            opacity: '0.6',
            bgcolor: 'grey',
            height: '200px',
        }

    //check if closest node has childerns TODO   
    for (const key in dropZoneCourseNodes){
        data.infotext = dropZoneCourseNodes[key].name 
        data.width = dropZoneCourseNodes[key].width 
        let position = {
            x: 0, 
            y: 0
        }
        if (key != 'and') {
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
        const newNode = {
            id: 'dropzone_' + key,
            type: dropZoneCourseNodes[key].type,
            position: position,
            label: `default node`,
            data: data
        }
        newDrop.nodes.push(newNode);

        let targetHandle = 'source_and'
        let sourceHandle =  'target'

        if(key == 'child'){
            targetHandle = 'target_and'
            sourceHandle =  'source'
        } 

        if (key != 'and') {
            const newEdge = {
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

function getOffsetX(closestNode, relation){
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

function getOffsetY(closestNode){
    return closestNode.position.y
}

export default drawDropzone;