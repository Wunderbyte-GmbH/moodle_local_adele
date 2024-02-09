import { shallowMount } from '@vue/test-utils';
import ChildNodesComponent from '../../../components/charthelper/childNodes.vue';

describe('ChildNodes', () => {
  it('renders child nodes when provided via props', () => {
    const childNodes = [
      { data: { fullname: 'Child Node 1' } },
      { data: { fullname: 'Child Node 2' } },
      { data: { fullname: 'Child Node 3' } },
    ];

    const wrapper = shallowMount(ChildNodesComponent, {
      props: { childNodes },
    });

    // Assert card title
    expect(wrapper.find('.card-title').text()).toContain('Child Nodes:');

    // Assert each child node is rendered
    childNodes.forEach(node => {
      expect(wrapper.text()).toContain(node.data.fullname);
    });
  });

  it('displays "No child nodes found" message when no child nodes are provided', () => {
    const wrapper = shallowMount(ChildNodesComponent, {
      props: { childNodes: [] },
    });

    // Assert card title
    expect(wrapper.find('.card-title').text()).toContain('Child Nodes:');

    // Assert "No child nodes found" message is displayed
    expect(wrapper.text()).toContain('No child nodes found.');
  });
});
