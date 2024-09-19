import parentNodes from '../../../../components/charthelper/parentNodes.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';

describe('parentNodes.vue', () => {
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          charthelper_parent_nodes: 'Parent Nodes',
          charthelper_no_parent_nodes: 'No parent nodes available',
        },
      },
    });
  });

  it('renders fallback message when props.parentNodes is empty', () => {
    const wrapper = mount(parentNodes, {
      global: {
        plugins: [store],
      },
      props: { parentNodes: [] },
    });

    expect(wrapper.find('.card-title').text()).toContain('Parent Nodes');
    const fallbackMessage = wrapper.find('li.list-group-item').text();
    expect(fallbackMessage).toBe('No parent nodes available');
  });

  it('renders parent nodes when props.parentNodes is provided', () => {
    const propsParentNodes = [
      { data: { fullname: 'Node 1' } },
      { data: { fullname: 'Node 2' } },
    ];

    const wrapper = mount(parentNodes, {
      global: {
        plugins: [store],
      },
      props: { parentNodes: propsParentNodes },
    });

    expect(wrapper.find('.card-title').text()).toContain('Parent Nodes');
    const listItems = wrapper.findAll('li.list-group-item');
    expect(listItems.length).toBe(2);
    expect(listItems[0].text()).toBe('Node 1');
    expect(listItems[1].text()).toBe('Node 2');
  });


});