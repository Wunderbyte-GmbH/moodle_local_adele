// generate a new id
import { MarkerType } from '@vue-flow/core'

const  addCustomEdge = (source, target) => {
    const previewEdge = {
        id: source + target,
        source: target,
        target: source,
        sourceHandle: 'source',
        targetHandle: 'target',
        style: {
          'stroke-width': 5,
        },
        markerEnd: MarkerType.ArrowClosed,
       };

    return previewEdge;
}

export default addCustomEdge;