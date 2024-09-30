
const  findNodeDimensions = (node, findNode) => {
  const element = document.getElementById(node.id);
  if (element && element.height.animVal) {
    return element.height.animVal.value;
  }
  const queryElement = document.querySelector('[data-id="' + node.id + '"]');
  if (queryElement) {
    const foundNode = findNode(node.id)
    if (foundNode.dimensions) {
      return foundNode.dimensions.height;
    }
  }
  return 200;
}

export default findNodeDimensions;