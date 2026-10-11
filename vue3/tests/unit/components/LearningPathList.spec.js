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
 * Learning Path List.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import LearningPathList from '../../../components/LearningPathList.vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';

jest.mock('vuex', () => ({ useStore: jest.fn() }));
jest.mock('vue-router', () => ({ useRouter: jest.fn() }));
jest.mock('@kyvg/vue3-notification', () => ({ notify: jest.fn() }));

describe('LearningPathList.vue visibility toggle', () => {
  const makeStore = (lp) => ({
    // A new/copied path is NOT in the page-load editablepaths snapshot.
    state: {
      learningpaths: [lp],
      viewlearningpaths: [],
      editablepaths: {},
      view: 'assistant',
      undoNodes: [],
      contextid: 1,
      user: 2,
      learningPathID: 0,
      strings: new Proxy({}, { get: (_t, k) => String(k) }),
    },
    dispatch: jest.fn().mockResolvedValue(undefined),
    commit: jest.fn(),
  });

  const lpBase = { id: 42, name: '1 copy', description: '1', visibility: 1, image: '' };

  const doMount = () => mount(LearningPathList, {
    global: {
      stubs: { HelpingSlider: true },
      directives: { tooltip: {} },
    },
  });

  it('shows the toggle for an owned (self-created/copied) path missing from editablepaths', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, isowner: 'true' }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    // The control is a <button> since #575 B2; it used to be an <a href="">.
    expect(wrapper.find('button.icon-link.position-absolute').exists()).toBe(true);
  });

  it('hides the toggle for a non-owned path missing from editablepaths', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, isowner: 'false' }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('button.icon-link.position-absolute').exists()).toBe(false);
  });

  it('shows the duplicate button for an assistant on their own path (#471)', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, isowner: 'true' }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.fa-copy').exists()).toBe(true);
  });

  it('hides the duplicate button for an assistant on a path they do not own (#471)', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, isowner: 'false' }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.fa-copy').exists()).toBe(false);
  });

  it('shows the owner (name + email) on the tile (#487)', async () => {
    useStore.mockReturnValue(makeStore({
      ...lpBase,
      isowner: 'false',
      owner: { name: 'Olive Owner', email: 'olive@example.com' },
    }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    const text = wrapper.text();
    expect(text).toContain('Olive Owner');
    expect(text).toContain('olive@example.com');
  });

  it('shows the owner on a view-only tile too (#487)', async () => {
    const store = makeStore(lpBase);
    store.state.learningpaths = [];
    store.state.viewlearningpaths = [
      { ...lpBase, visibility: 1, owner: { name: 'Vic Viewer', email: 'vic@example.com' } },
    ];
    useStore.mockReturnValue(store);
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    const text = wrapper.text();
    expect(text).toContain('Vic Viewer');
    expect(text).toContain('vic@example.com');
  });
});

describe('LearningPathList.vue ownerless handling (#571)', () => {
  const makeStore = (lp, view = 'manager') => ({
    state: {
      learningpaths: [lp],
      viewlearningpaths: [],
      editablepaths: {},
      view,
      undoNodes: [],
      contextid: 1,
      user: 2,
      learningPathID: 0,
      strings: new Proxy({}, { get: (_t, k) => String(k) }),
    },
    dispatch: jest.fn().mockResolvedValue(undefined),
    commit: jest.fn(),
  });

  const lpBase = { id: 7, name: 'Orphan LP', description: 'x', visibility: 1, image: '', isowner: 'true' };

  const doMount = () => mount(LearningPathList, {
    global: {
      stubs: { HelpingSlider: true },
      directives: { tooltip: {} },
    },
  });

  it('shows the ownerless badge and the manager banner for an orphaned path', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, ownerless: true, owner: { name: '', email: '' } }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.ownerless-badge').exists()).toBe(true);
    expect(wrapper.find('.ownerless-banner').exists()).toBe(true);
  });

  it('shows neither badge nor banner when the owner is alive', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, ownerless: false, owner: { name: 'Alice A', email: 'a@a.at' } }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.ownerless-badge').exists()).toBe(false);
    expect(wrapper.find('.ownerless-banner').exists()).toBe(false);
  });

  it('hides the banner from non-managers', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, ownerless: true, owner: { name: '', email: '' } }, 'assistant'));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.ownerless-banner').exists()).toBe(false);
  });
});


describe('LearningPathList.vue opening a path (#585)', () => {
  it('leaves loading the path to the editor - the list does not request it', async () => {
    const store = {
      state: {
        learningpaths: [{ id: 42, name: 'Path', description: 'd', visibility: 1, image: '', isowner: 'true' }],
        viewlearningpaths: [],
        editablepaths: {},
        view: 'manager',
        undoNodes: [],
        contextid: 1,
        user: 2,
        learningPathID: 0,
        strings: new Proxy({}, { get: (_t, k) => String(k) }),
      },
      dispatch: jest.fn().mockResolvedValue(undefined),
      commit: jest.fn(),
    };
    const push = jest.fn();
    useStore.mockReturnValue(store);
    useRouter.mockReturnValue({ push });
    const wrapper = mount(LearningPathList, {
      global: { stubs: { HelpingSlider: true }, directives: { tooltip: {} } },
    });
    await wrapper.vm.$nextTick();

    const edit = wrapper.findAll('button').find((button) => (button.attributes('aria-label') || '').startsWith('edit'));
    expect(edit).toBeTruthy();
    await edit.trigger('click');
    await wrapper.vm.$nextTick();

    expect(store.state.learningPathID).toBe(42);
    expect(push).toHaveBeenCalledWith({ name: 'learningpath-edit', params: { learningpathId: 42 } });
    // Two loads of the same path raced; the later answer overwrote the title
    // the person had typed in the meantime.
    expect(store.dispatch).not.toHaveBeenCalledWith('fetchLearningpath');
  });
});
