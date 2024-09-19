import childNodes from '../../../../components/charthelper/childNodes.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';

describe('childNodes.vue', () => {
  let store;

  beforeEach(() => {
    // Mock Vuex store
    store = createStore({
      state: {
        strings: {
          charthelper_child_nodes: 'Child Nodes',
          charthelper_no_child_nodes: 'No child nodes available',
        },
      },
    });
  });

  it('renders fallback message when props.childNodes is empty', () => {
    const wrapper = mount(childNodes, {
      global: {
        plugins: [store], // Inject Vuex store
      },
      props: { childNodes: [] },
    });

    expect(wrapper.find('.card-title').text()).toContain('Child Nodes');
    const fallbackMessage = wrapper.find('li.list-group-item').text();
    expect(fallbackMessage).toBe('No child nodes available');
  });

  it('renders child nodes when props.childNodes is provided', () => {
    const propsChildNodes = [
      { data: { fullname: 'Node 1' } },
      { data: { fullname: 'Node 2' } },
    ];

    const wrapper = mount(childNodes, {
      global: {
        plugins: [store],
      },
      props: { childNodes: propsChildNodes },
    });

    expect(wrapper.find('.card-title').text()).toContain('Child Nodes');
    const listItems = wrapper.findAll('li.list-group-item');
    expect(listItems.length).toBe(2);
    expect(listItems[0].text()).toBe('Node 1');
    expect(listItems[1].text()).toBe('Node 2');
  });


});