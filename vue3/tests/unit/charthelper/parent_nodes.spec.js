import { shallowMount } from '@vue/test-utils';
import ParentNodesComponent from '../../../components/charthelper/parentNodes.vue';

describe('ParentNodes component', () => {
  it('renders parent nodes when provided via props', () => {
    const parentNodes = [
      { data: { fullname: 'Parent Node 1' } },
      { data: { fullname: 'Child Node 2' } },
      { data: { fullname: 'Child Node 3' } },
    ];

    const wrapper = shallowMount(ParentNodesComponent, {
      props: { parentNodes },
    });

    // Assert card title
    expect(wrapper.find('.card-title').text()).toContain('Parent Nodes:');

    // Assert each child node is rendered
    parentNodes.forEach(node => {
      expect(wrapper.text()).toContain(node.data.fullname);
    });
  });

  it('displays "No parent nodes found" message when no parent nodes are provided', () => {
    const wrapper = shallowMount(ParentNodesComponent, {
      props: { parentNodes: [] },
    });

    // Assert card title
    expect(wrapper.find('.card-title').text()).toContain('Parent Nodes:');

    // Assert "No child nodes found" message is displayed
    expect(wrapper.text()).toContain('No parent nodes found.');
  });
});
