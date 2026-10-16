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
 * userSearch.vue - the editors of a path as a matrix (Jest issue, G2):
 * view x add / remove / transfer ownership, search results present or not,
 * a person already on the list, a confirmation declined, and a refusal by
 * the server.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount, flushPromises } from '@vue/test-utils';
import userSearch from '../../../../components/charthelper/userSearch.vue';
import { makeStore } from '../../fixtures/storeFactory';

jest.mock('lodash', () => ({ debounce: jest.fn((fn) => fn) }));

const OWNER = { id: 2, firstname: 'Olga', lastname: 'Owner' };
const EDITOR = { id: 3, firstname: 'Eddi', lastname: 'Editor' };
const NEWBIE = { id: 4, firstname: 'Nina', lastname: 'New' };

/**
 * The editor list of a path owned by OLGA, with EDDI as a second editor.
 *
 * @param {string} view
 * @param {object} answers Overrides of the store actions.
 */
async function editors(view, answers = {}) {
  const actions = {
    getLpEditUsers: jest.fn().mockResolvedValue([{ ...OWNER }, { ...EDITOR }]),
    getFoundUsers: jest.fn().mockResolvedValue({ list: [{ ...NEWBIE }], warnings: '' }),
    createLpEditUsers: jest.fn().mockResolvedValue(true),
    removeLpEditUsers: jest.fn().mockResolvedValue(true),
    setLpOwner: jest.fn().mockResolvedValue({ success: true }),
    ...answers,
  };
  const store = makeStore({ view, learningPathID: 1, learningpath: { ownerid: 2, ownername: 'Olga Owner' } }, { actions });
  const wrapper = mount(userSearch, { global: { plugins: [store] }, attachTo: document.body });
  await flushPromises();
  const card = (person) => wrapper.findAll('.card-user').find((c) => c.text().includes(person.firstname));
  return { wrapper, actions, store, card };
}

/** Type into the search and wait for the result list. */
async function search(wrapper, text) {
  await wrapper.find('input.user-search-input').setValue(text);
  await flushPromises();
}

describe('userSearch - view x action', () => {
  afterEach(() => jest.restoreAllMocks());

  it.each([
    ['manager', true],
    ['assistant', false],
    ['teacheredit', false],
  ])('view=%s: add yes, remove a non-owner yes, transfer ownership %s, the owner never removable', async (view, transfer) => {
    jest.spyOn(window, 'confirm').mockReturnValue(true);
    const e = await editors(view);
    // Owner card: crown, no removal, no transfer button.
    expect(e.card(OWNER).find('.owner-crown').exists()).toBe(true);
    expect(e.card(OWNER).find('button.text-danger').exists()).toBe(false);
    // The other editor: removable; ownership transferable only by a manager.
    expect(e.card(EDITOR).find('button.text-danger').exists()).toBe(true);
    expect(e.card(EDITOR).find('.setowner-btn').exists()).toBe(transfer);

    await search(e.wrapper, 'Nina');
    await e.wrapper.find('.user-item').trigger('click');
    expect(e.actions.createLpEditUsers).toHaveBeenCalledWith(expect.anything(), { lpid: 1, userid: 4 });
    expect(e.card(NEWBIE)).toBeTruthy();

    await e.card(EDITOR).find('button.text-danger').trigger('click');
    expect(e.actions.removeLpEditUsers).toHaveBeenCalledWith(expect.anything(), { lpid: 1, userid: 3 });
    expect(e.card(EDITOR)).toBeUndefined();
    e.wrapper.unmount();
  });
});

describe('userSearch - search and list', () => {
  afterEach(() => jest.restoreAllMocks());

  it('no result: the "nobody found" notice, no list', async () => {
    const e = await editors('manager', { getFoundUsers: jest.fn().mockResolvedValue({ list: [], warnings: '' }) });
    await search(e.wrapper, 'Zzz');
    expect(e.wrapper.find('.user-list').exists()).toBe(false);
    expect(e.wrapper.text()).toContain('nousersfound');
    e.wrapper.unmount();
  });

  it('a warning of the server is shown instead of the list', async () => {
    const e = await editors('manager', { getFoundUsers: jest.fn().mockResolvedValue({ list: [{ ...NEWBIE }], warnings: 'Too many' }) });
    await search(e.wrapper, 'N');
    expect(e.wrapper.text()).toContain('Too many');
    expect(e.wrapper.find('.user-list').exists()).toBe(false);
    e.wrapper.unmount();
  });

  it('a person already on the list is not added twice', async () => {
    const e = await editors('manager', { getFoundUsers: jest.fn().mockResolvedValue({ list: [{ ...EDITOR }], warnings: '' }) });
    await search(e.wrapper, 'Eddi');
    await e.wrapper.find('.user-item').trigger('click');
    expect(e.wrapper.findAll('.card-user').filter((c) => c.text().includes('Eddi'))).toHaveLength(1);
    e.wrapper.unmount();
  });

  it('a declined confirmation removes nobody', async () => {
    jest.spyOn(window, 'confirm').mockReturnValue(false);
    const e = await editors('manager');
    await e.card(EDITOR).find('button.text-danger').trigger('click');
    expect(e.actions.removeLpEditUsers).not.toHaveBeenCalled();
    expect(e.card(EDITOR)).toBeTruthy();
    e.wrapper.unmount();
  });

  it('with the owner alone on the list there is nothing to remove', async () => {
    const e = await editors('manager', { getLpEditUsers: jest.fn().mockResolvedValue([{ ...OWNER }]) });
    expect(e.wrapper.findAll('button.text-danger')).toHaveLength(0);
    e.wrapper.unmount();
  });
});

describe('userSearch - when the server refuses', () => {
  afterEach(() => jest.restoreAllMocks());

  it('a refused removal leaves the person on the list (#599)', async () => {
    // removeUser() takes the card away without waiting for the answer; the
    // store action does not even return the request.
    jest.spyOn(window, 'confirm').mockReturnValue(true);
    const e = await editors('manager', { removeLpEditUsers: jest.fn().mockRejectedValue(new Error('nopermissions')) });
    await e.card(EDITOR).find('button.text-danger').trigger('click');
    await flushPromises();
    expect(e.card(EDITOR)).toBeTruthy();
    e.wrapper.unmount();
  });

  it('a refused addition does not show the person as an editor (#599)', async () => {
    const e = await editors('manager', { createLpEditUsers: jest.fn().mockRejectedValue(new Error('nopermissions')) });
    await search(e.wrapper, 'Nina');
    await e.wrapper.find('.user-item').trigger('click');
    await flushPromises();
    expect(e.card(NEWBIE)).toBeUndefined();
    e.wrapper.unmount();
  });
});

describe('userSearch - the suggestion list', () => {
  it('a click inside the field or the list keeps it open, a click anywhere else closes it', async () => {
    const e = await editors('manager');
    await search(e.wrapper, 'Nina');
    expect(e.wrapper.find('.user-list').exists()).toBe(true);

    // Inside: the field itself, then the list.
    e.wrapper.find('input.user-search-input').element.click();
    await e.wrapper.vm.$nextTick();
    expect(e.wrapper.find('.user-list').exists()).toBe(true);
    e.wrapper.find('.user-list').element.click();
    await e.wrapper.vm.$nextTick();
    expect(e.wrapper.find('.user-list').exists()).toBe(true);

    // Outside.
    document.body.click();
    await e.wrapper.vm.$nextTick();
    expect(e.wrapper.find('.user-list').exists()).toBe(false);
    e.wrapper.unmount();
  });
});

describe('userSearch - handing the path over (#488)', () => {
  afterEach(() => jest.restoreAllMocks());

  it('a declined confirmation hands nothing over: no request, the owner stays', async () => {
    jest.spyOn(window, 'confirm').mockReturnValue(false);
    const e = await editors('manager');
    await e.card(EDITOR).find('.setowner-btn').trigger('click');
    await flushPromises();
    expect(e.actions.setLpOwner).not.toHaveBeenCalled();
    expect(e.store.state.learningpath.ownerid).toBe(2);
    expect(e.card(OWNER).find('.owner-crown').exists()).toBe(true);
    e.wrapper.unmount();
  });
});

describe('userSearch - who owns the path (#571)', () => {
  const ownerLabel = async (learningpath) => {
    const actions = {
      getLpEditUsers: jest.fn().mockResolvedValue([{ ...EDITOR }]),
      getFoundUsers: jest.fn().mockResolvedValue({ list: [], warnings: '' }),
    };
    const store = makeStore({
      view: 'manager', learningPathID: 1, learningpath,
      strings: { main_ownerless: 'No owner', owner_label: 'Owner:' },
    }, { actions });
    const wrapper = mount(userSearch, { global: { plugins: [store] } });
    await flushPromises();
    const label = wrapper.find('.owner-label');
    const result = { name: label.find('b').exists() ? label.find('b').text() : '', badge: label.find('.badge').exists() };
    wrapper.unmount();
    return result;
  };

  it.each([
    ['an owner: named', { ownerid: 2, ownername: 'Olga Owner', ownerless: false }, { name: 'Olga Owner', badge: false }],
    ['ownerless: the warning badge instead of a name', { ownerid: 0, ownername: '', ownerless: true }, { name: '', badge: true }],
    ['not known yet: neither', { ownerid: 0, ownername: '', ownerless: false }, { name: '', badge: false }],
  ])('%s', async (_label, learningpath, expected) => {
    expect(await ownerLabel(learningpath)).toEqual(expected);
  });
});
