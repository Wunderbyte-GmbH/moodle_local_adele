import { mount, shallowMount } from '@vue/test-utils';
import DropdownInput from '../../../../components/nodes_items/DropdownInput.vue';
import { createStore } from 'vuex/dist/vuex.cjs.js';

describe('DropdownComponent', () => {
  let store

  const testItems = [
    { id: '1', name: 'Test One', coursename: 'Course A' },
    { id: '2', name: 'Test Two', coursename: 'Course B' },
  ];

  let wrapper;
  
  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          nodes_items_none: 'None',
          nodes_items_testname: 'Test Name:',
          nodes_items_coursename: 'Course Name:'
        }
      }
    })


    wrapper = mount(DropdownInput, {
      props: {
        selectedTestId: null,
        tests: testItems,
      },
      global: {
        plugins: [store],
      }
    });
  });

  it('renders input and dropdown correctly', () => {
    expect(wrapper.find('input.form-control').exists()).toBe(true);
    expect(wrapper.find('.dropdown').exists()).toBe(false); // Initially hidden
  });

  it('shows dropdown when input is focused', async () => {
    const input = wrapper.find('input');
    await input.trigger('focus');
    expect(wrapper.find('.dropdown').exists()).toBe(true);
  });

  it('filters options based on search input', async () => {
    const input = wrapper.find('input');

    // Focus input to show dropdown
    await input.trigger('focus');
    
    // Simulate typing text
    await input.setValue('one');
    const filteredItems = wrapper.findAll('li');
    expect(filteredItems).toHaveLength(2);
    expect(filteredItems.at(1).text()).toContain('Test One');
  });

  it('selects an option and emits update:value event', async () => {
    const input = wrapper.find('input');
    await input.trigger('focus');

    const firstOption = wrapper.findAll('li').at(1); // Skip the 'None' option
    await firstOption.trigger('mousedown');
    
    expect(wrapper.emitted('update:value')).toBeTruthy();
    expect(wrapper.emitted('update:value')[0][0]).toEqual({ id: '1', name: 'Test One', coursename: 'Course A' });
  
    // Ensure dropdown closes after selection
    expect(wrapper.find('.dropdown').exists()).toBe(false);
  });

  it('closes dropdown when input loses focus', async () => {
    const input = wrapper.find('input');
    await input.trigger('focus');
    expect(wrapper.find('.dropdown').exists()).toBe(true);

    await input.trigger('blur');
    setTimeout(() => {
      expect(wrapper.find('.dropdown').exists()).toBe(false);
    }, 300); // Wait more than 200ms to account for debounce
  });
});