// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * user Search.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import userSearch from '../../../../components/charthelper/userSearch.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
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
      strings: {
        selectuser: 'select a user',
        editordeleteconfirmation: 'Are you sure you want to delete this user?',
        nousersfound: 'No users found',
        searchuser: 'Search user',
        onlysetaftersaved: 'Set only after saved',
      }
    };

    actions = {
      getFoundUsers: jest.fn().mockResolvedValue({ list: [{ id: 1, firstname: 'John', lastname: 'Doe' }], warnings: '' }),
      getLpEditUsers: jest.fn().mockResolvedValue([
        { id: 2, firstname: 'Jane', lastname: 'Smith' },
        { id: 3, firstname: 'Jack', lastname: 'White' }
      ]),
      createLpEditUsers: jest.fn(),
      removeLpEditUsers: jest.fn(),
      setLpOwner: jest.fn().mockResolvedValue({ success: true }),
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
    expect(wrapper.findAll('.card-user').length).toBe(3); // Now 3 users should be selected
  });

  it('removes a user when the remove button is clicked', async () => {
    window.confirm = jest.fn(() => true);

    const wrapper = mount(userSearch, {
      global: {
        plugins: [store],
      },
    });

    await wrapper.vm.$nextTick();
    await flushPromises();

    const userCards = wrapper.findAll('.card-user');
    expect(userCards.length).toBe(2);

    const removeButtons = wrapper.findAll('.btn-link');
    expect(removeButtons.length).toBe(2);

    await removeButtons[0].trigger('click');

    expect(actions.removeLpEditUsers).toHaveBeenCalledWith(expect.anything(), {
      lpid: state.learningPathID,
      userid: 2,
    });

    expect(wrapper.findAll('.card-user').length).toBe(1);
    expect(wrapper.find('.card-user').text()).not.toContain('Jane Smith');
  });

  it('removes no user when confirmation is cancelled', async () => {
    window.confirm = jest.fn(() => false);

    const wrapper = mount(userSearch, {
      global: {
        plugins: [store],
      },
    });

    await wrapper.vm.$nextTick();
    await flushPromises();

    const userCards = wrapper.findAll('.card-user');
    expect(userCards.length).toBe(2);

    const removeButtons = wrapper.findAll('.btn-link');
    expect(removeButtons.length).toBe(2);

    await removeButtons[0].trigger('click');

    expect(actions.removeLpEditUsers).not.toHaveBeenCalled();
    expect(wrapper.findAll('.card-user').length).toBe(2);
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

describe('userSearch.vue owner transfer (#488)', () => {
  const buildStore = (view, ownerid = 99) => createStore({
    state: {
      learningPathID: 1,
      view,
      learningpath: { ownerid, ownername: 'Owen Owner', ownerless: false },
      strings: {
        setowner_button: 'Make owner',
        setowner_confirm: 'Hand this learning path to:',
        setowner_current: 'Current owner',
        owner_label: 'Owner:',
      },
    },
    actions: {
      getFoundUsers: jest.fn().mockResolvedValue({ list: [], warnings: '' }),
      getLpEditUsers: jest.fn().mockResolvedValue([{ id: 2, firstname: 'Jane', lastname: 'Smith' }]),
      createLpEditUsers: jest.fn(),
      removeLpEditUsers: jest.fn(),
      setLpOwner,
    },
  });
  let setLpOwner;

  beforeEach(() => {
    setLpOwner = jest.fn().mockResolvedValue({ success: true });
  });

  const doMount = async (view, ownerid = 99) => {
    const wrapper = mount(userSearch, { global: { plugins: [buildStore(view, ownerid)] } });
    await wrapper.vm.$nextTick();
    await flushPromises();
    return wrapper;
  };

  it('shows the make-owner button for the Adele Manager only', async () => {
    const manager = await doMount('manager');
    expect(manager.find('.setowner-btn').exists()).toBe(true);
    const assistant = await doMount('assistant');
    expect(assistant.find('.setowner-btn').exists()).toBe(false);
  });

  it('dispatches setLpOwner after confirmation', async () => {
    window.confirm = jest.fn(() => true);
    const wrapper = await doMount('manager');
    await wrapper.find('.setowner-btn').trigger('click');
    expect(setLpOwner).toHaveBeenCalledWith(
      expect.anything(),
      { lpid: 1, userid: 2 }
    );
  });

  it('does nothing when the confirmation is declined', async () => {
    window.confirm = jest.fn(() => false);
    const wrapper = await doMount('manager');
    await wrapper.find('.setowner-btn').trigger('click');
    expect(setLpOwner).not.toHaveBeenCalled();
  });
});

describe('userSearch.vue owner indication (#488)', () => {
  const buildStore = (view, ownerid) => createStore({
    state: {
      learningPathID: 1,
      view,
      learningpath: { ownerid, ownername: 'Jane Smith', ownerless: false },
      strings: {
        setowner_button: 'Make owner',
        setowner_current: 'Current owner',
        owner_label: 'Owner:',
      },
    },
    actions: {
      getFoundUsers: jest.fn().mockResolvedValue({ list: [], warnings: '' }),
      getLpEditUsers: jest.fn().mockResolvedValue([
        { id: 2, firstname: 'Jane', lastname: 'Smith' },
        { id: 3, firstname: 'Jack', lastname: 'White' },
      ]),
      createLpEditUsers: jest.fn(),
      removeLpEditUsers: jest.fn(),
      setLpOwner: jest.fn().mockResolvedValue({ success: true }),
    },
  });

  const doMount = async (view, ownerid) => {
    const wrapper = mount(userSearch, { global: { plugins: [buildStore(view, ownerid)] } });
    await wrapper.vm.$nextTick();
    await flushPromises();
    return wrapper;
  };

  it('marks the current owner with a golden crown (indicator, no button) for everyone', async () => {
    const wrapper = await doMount('teacheredit', 2);
    expect(wrapper.find('.owner-crown').exists()).toBe(true);
    expect(wrapper.find('.setowner-btn').exists()).toBe(false);
  });

  it('shows grey action crowns to the manager on non-owner editors only', async () => {
    const wrapper = await doMount('manager', 2);
    const cards = wrapper.findAll('.card-user');
    expect(cards.length).toBe(2);
    expect(wrapper.findAll('.owner-crown').length).toBe(1);
    expect(wrapper.findAll('.setowner-btn').length).toBe(1);
  });

  it('shows the owner name next to the selection heading', async () => {
    const wrapper = await doMount('teacheredit', 2);
    expect(wrapper.find('.owner-label').text()).toContain('Jane Smith');
  });

  // Decision 2026-10-08 ("Regel B"): the owner always stays on the list;
  // ownership is handed over with the crown, not by removal.
  it.each(['manager', 'assistant', 'teacheredit'])(
    'offers no remove button on the owner card (view %s), but on the others',
    async (view) => {
      const wrapper = await doMount(view, 2);
      const cards = wrapper.findAll('.card-user');
      const owner = cards.filter((card) => card.find('.owner-crown').exists());
      const others = cards.filter((card) => !card.find('.owner-crown').exists());
      expect(owner).toHaveLength(1);
      expect(owner[0].find('button.text-danger').exists()).toBe(false);
      expect(others.length).toBeGreaterThan(0);
      others.forEach((card) => expect(card.find('button.text-danger').exists()).toBe(true));
    },
  );
});

