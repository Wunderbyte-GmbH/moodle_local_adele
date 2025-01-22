import TimeWarning from '../../../../components/nodes_items/TimeWarning.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

// Mock the store

describe('TimeWarning.vue', () => {
  let wrapper;
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          nodes_warning_time_restriction: 'Time restriction warning message',
        },
      },
    });

    wrapper = mount(TimeWarning, {
      global: {
        plugins: [store],
      },
    });
  });

  it('renders the tooltip container and icon', () => {
    const container = wrapper.find('.tooltip-container');
    const icon = wrapper.find('.fa-exclamation-triangle');
    expect(container.exists()).toBe(true);
    expect(icon.exists()).toBe(true);
  });

});