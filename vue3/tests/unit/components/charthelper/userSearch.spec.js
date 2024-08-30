import userSearch from '../../../../components/charthelper/userSearch.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import { debounce } from 'lodash';
import flushPromises from 'flush-promises';

jest.mock('lodash', () => ({
  debounce: jest.fn((fn) => fn),
}));

describe('userSearch.vue', () => {
  let store;
  let actions;
  let state;

  beforeEach(() => {
    state = {
      learningPathID: 1,
    };

    actions = {
      getFoundUsers: jest.fn().mockResolvedValue({ list: [{ id: 1, firstname: 'John', lastname: 'Doe' }], warnings: '' }),
      getLpEditUsers: jest.fn().mockResolvedValue([{ id: 2, firstname: 'Jane', lastname: 'Smith' }]),
      createLpEditUsers: jest.fn(),
      removeLpEditUsers: jest.fn(),
    };

    store = createStore({
      state,
      actions,
    });
  });

  it('renders correctly and searches users on input', async () => {
    const wrapper = mount(userSearch, {
      global: {
        plugins: [store],
      },
    });

    // Check that the initial users are fetched on mount
    expect(actions.getLpEditUsers).toHaveBeenCalledWith(expect.anything(), state.learningPathID);
    await wrapper.vm.$nextTick();
    await flushPromises();


    expect(wrapper.find('.card-user').exists()).toBe(true);
    expect(wrapper.find('.card-user').text()).toContain('Jane Smith');

    // Simulate user typing in the search input
    const input = wrapper.find('input');
    await input.setValue('John');
    await wrapper.vm.$nextTick();

    // Check that the search action is triggered
    expect(actions.getFoundUsers).toHaveBeenCalledWith(expect.anything(), 'John');
    await wrapper.vm.$nextTick();

    // Ensure the search results are displayed
    expect(wrapper.find('.user-item').exists()).toBe(true);
    expect(wrapper.find('.user-item').text()).toContain('John Doe');
  });

  it('adds a user when a search result is clicked', async () => {
    const wrapper = mount(userSearch, {
      global: {
        plugins: [store],
      },
    });

    // Simulate user search
    const input = wrapper.find('input');
    await input.setValue('John');
    await wrapper.vm.$nextTick();

    const userItem = wrapper.find('.user-item');
    await userItem.trigger('click');

    // Check that the user was added
    expect(actions.createLpEditUsers).toHaveBeenCalledWith(expect.anything(), {
      lpid: state.learningPathID,
      userid: 1,
    });
    expect(wrapper.findAll('.card-user').length).toBe(2); // Now 2 users should be selected
  });

  it('removes a user when the remove button is clicked', async () => {
    const wrapper = mount(userSearch, {
      global: {
        plugins: [store],
      },
    });

    await wrapper.vm.$nextTick();
    await flushPromises();

    const removeButton = wrapper.find('.btn-link');
    await removeButton.trigger('click');

    expect(actions.removeLpEditUsers).toHaveBeenCalledWith(expect.anything(), {
      lpid: state.learningPathID,
      userid: 2,
    });
    expect(wrapper.find('.card-user').exists()).toBe(false);
  });

  it('hides the user list when clicking outside', async () => {
    const wrapper = mount(userSearch, {
      global: {
        plugins: [store],
      },
    });

    // Simulate user search
    const input = wrapper.find('input');
    await input.setValue('John');
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.user-list').exists()).toBe(true);
    wrapper.vm.isListVisible = false;
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.user-list').exists()).toBe(false);

  });
});