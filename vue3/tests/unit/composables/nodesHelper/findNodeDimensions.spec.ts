import findNodeDimensions from '../../../../composables/nodesHelper/findNodeDimensions';

describe('findNodeDimensions', () => {

  interface Node {
    id: string;
    dimensions?: {
      height: number;
    };
  }

  let node: Node;
  let findNode: jest.MockedFunction<(id: string) => Node>;

  beforeEach(() => {
    node = { id: 'node1' };

    // Mock findNode function
    findNode = jest.fn().mockReturnValue({
      id: 'node1',
      dimensions: { height: 150 },
    });

    // Reset mocks
    (document.getElementById as jest.Mock) = jest.fn();
    (document.querySelector as jest.Mock) = jest.fn();
  });

  it('should return height from element if it has animVal', () => {
    // Mocking getElementById to return an element with animVal
    (document.getElementById as jest.Mock).mockReturnValue({
      height: { animVal: { value: 100 } },
    });

    const result = findNodeDimensions(node, findNode);

    expect(result).toBe(100);
    expect(findNode).not.toHaveBeenCalled();
  });

  it('should return height from findNode if element does not have animVal but querySelector finds it', () => {
    // Simulate getElementById returning null
    (document.getElementById as jest.Mock).mockReturnValue(null);

    // Mocking querySelector to return an element
    (document.querySelector as jest.Mock).mockReturnValue({});

    const result = findNodeDimensions(node, findNode);

    expect(findNode).toHaveBeenCalledWith('node1');
    expect(result).toBe(150);
  });

  it('should return default height (200) if no element or dimensions are found', () => {
    // Simulate both getElementById and querySelector returning null
    (document.getElementById as jest.Mock).mockReturnValue(null);
    (document.querySelector as jest.Mock).mockReturnValue(null);

    const result = findNodeDimensions(node, findNode);

    expect(result).toBe(200);
    expect(findNode).not.toHaveBeenCalled();
  });

  it('should return default height (200) if findNode does not return dimensions', () => {
    // Simulate getElementById returning null
    (document.getElementById as jest.Mock).mockReturnValue(null);
    (document.querySelector as jest.Mock).mockReturnValue({});
    findNode.mockReturnValue({ id: 'node1' });

    const result = findNodeDimensions(node, findNode);
    expect(findNode).toHaveBeenCalledWith('node1');
    expect(result).toBe(200);
  });

});