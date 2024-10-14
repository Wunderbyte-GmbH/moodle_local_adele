interface Node {
  id: string;
  dimensions?: {
    height: number;
  };
}

interface CustomSVGElement {
  height: {
    animVal: {
      value: number;
    };
  };
}

const findNodeDimensions = (node: Node, findNode: (id: string) => Node): number => {
  const element = document.getElementById(node.id) as CustomSVGElement | null;

  if (element && element.height?.animVal) {
    return element.height.animVal.value;
  }

  const queryElement = document.querySelector('[data-id="' + node.id + '"]');

  if (queryElement) {
    const foundNode = findNode(node.id);

    if (foundNode.dimensions) {
      return foundNode.dimensions.height;
    }
  }
  return 200;
}

export default findNodeDimensions;