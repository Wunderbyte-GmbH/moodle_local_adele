import loadFlowChart from '../../../composables/loadFlowChart';

describe('loadFlowChart', () => {
  let flow;

  beforeEach(() => {
    // Define the flow object that will be used in each test
    flow = {
      nodes: [
        { id: '1', draggable: true, deletable: true },
        { id: '2', draggable: true, deletable: true },
      ],
      edges: [
        { id: 'e1-2', deletable: true },
      ],
    };
  });

  it('should load the view as teacher', () => {
    const view = 'teacher';
    const result = loadFlowChart(flow, view);
    result.nodes.forEach(node => {
      expect(node.draggable).toBe(false);
      expect(node.deletable).toBe(false);
    });

    // Check that all edges are not deletable
    result.edges.forEach(edge => {
      expect(edge.deletable).toBe(false);
    });
  });
  it('should load the view not as teacher', () => {
    const view = 'student';
    const result = loadFlowChart(flow, view);

    // Check that nodes and edges remain unchanged
    result.nodes.forEach((node, index) => {
      expect(node.draggable).toBe(flow.nodes[index].draggable);
      expect(node.deletable).toBe(flow.nodes[index].deletable);
    });

    result.edges.forEach((edge, index) => {
      expect(edge.deletable).toBe(flow.edges[index].deletable);
    });
  });
});