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
          'position': 'relative',
          'z-index': -10
        },
        markerEnd: MarkerType.ArrowClosed,
       };

    return previewEdge;
}

export default addCustomEdge;