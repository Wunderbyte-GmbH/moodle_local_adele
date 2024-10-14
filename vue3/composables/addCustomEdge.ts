// generate a new id
import { MarkerType } from '@vue-flow/core'

interface CustomEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle: string;
  targetHandle: string;
  style: {
    'stroke-width': number;
    'position': string;
    'z-index': number;
  };
  markerEnd: MarkerType;
}

const  addCustomEdge = (source: string, target: string): CustomEdge => {
    const previewEdge: CustomEdge = {
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