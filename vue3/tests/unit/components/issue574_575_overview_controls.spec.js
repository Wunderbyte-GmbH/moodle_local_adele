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
 * issue574_575_overview_controls.spec module.
 *
 * The overview must be addressable by business identity rather than by
 * position or by a text that occurs several times on the page (#574), and
 * its icon controls must be real, named, keyboard-operable buttons
 * (#575 B2).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import LearningPathList from '../../../components/LearningPathList.vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';

jest.mock('vuex', () => ({ useStore: jest.fn() }));
jest.mock('vue-router', () => ({ useRouter: jest.fn() }));
jest.mock('@kyvg/vue3-notification', () => ({ notify: jest.fn() }));

const strings = {
  learningpath_visibility: 'Visible to users',
  make_visible: 'Make visible',
  make_invisible: 'Make invisible',
  duplicate: 'Duplicate',
  edit: 'Edit',
  delete: 'Delete',
  view: 'View',
};

const makeStore = (learningpaths, viewlearningpaths = []) => ({
  state: {
    learningpaths,
    viewlearningpaths,
    editablepaths: {},
    view: 'manager',
    undoNodes: [],
    contextid: 1,
    user: 2,
    learningPathID: 0,
    strings: new Proxy(strings, { get: (t, k) => (k in t ? t[k] : String(k)) }),
  },
  dispatch: jest.fn().mockResolvedValue(undefined),
  commit: jest.fn(),
});

const path = (id, name, visibility = 1) => ({
  id, name, description: 'x', visibility, image: '', isowner: 'true',
});

const doMount = () => mount(LearningPathList, {
  global: {
    stubs: { HelpingSlider: true },
    directives: { tooltip: {} },
  },
});

describe('learning path overview: stable hooks and named controls (#574, #575)', () => {
  beforeEach(() => {
    useRouter.mockReturnValue({ push: jest.fn() });
  });

  it('renders exactly one row per learning path, keyed by its real id', async () => {
    useStore.mockReturnValue(makeStore(
      [path(42, 'Erster Pfad'), path(43, 'Zweiter Pfad')],
      [path(44, 'Nur ansehbar')],
    ));
    const wrapper = doMount();
    await wrapper.vm.$nextTick();

    for (const id of [42, 43, 44]) {
      expect(wrapper.findAll(`[data-testid="learningpath-row-${id}"]`)).toHaveLength(1);
    }
    expect(wrapper.findAll('[data-testid^="learningpath-row-"]')).toHaveLength(3);
  });

  it('offers the create control under a stable hook', async () => {
    useStore.mockReturnValue(makeStore([path(42, 'Erster Pfad')]));
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('[data-testid="learningpath-create"]').exists()).toBe(true);
  });

  it('exposes the visibility control as a pressed toggle button with a name', async () => {
    useStore.mockReturnValue(makeStore([path(42, 'Erster Pfad', 1)]));
    const wrapper = doMount();
    await wrapper.vm.$nextTick();

    const toggle = wrapper.find('[data-testid="learningpath-visibility-toggle-42"]');
    expect(toggle.element.tagName).toBe('BUTTON');
    expect(toggle.attributes('aria-pressed')).toBe('true');
    expect(toggle.attributes('aria-label')).toBe('Visible to users: Erster Pfad');
    expect(toggle.attributes('title')).toBe('Make invisible');
  });

  it('reports the unpressed state for a hidden path', async () => {
    useStore.mockReturnValue(makeStore([path(42, 'Erster Pfad', 0)]));
    const wrapper = doMount();
    await wrapper.vm.$nextTick();

    const toggle = wrapper.find('[data-testid="learningpath-visibility-toggle-42"]');
    expect(toggle.attributes('aria-pressed')).toBe('false');
    expect(toggle.attributes('title')).toBe('Make visible');
  });

  it('leaves no icon control as an href="" click decoy', async () => {
    useStore.mockReturnValue(makeStore([path(42, 'Erster Pfad')], [path(44, 'Nur ansehbar')]));
    const wrapper = doMount();
    await wrapper.vm.$nextTick();

    expect(wrapper.findAll('a[href=""]')).toHaveLength(0);
    const controls = wrapper.findAll('button.icon-link');
    expect(controls.length).toBeGreaterThan(0);
    for (const control of controls) {
      // Every icon-only control carries a name, and its icon is decorative.
      expect(control.attributes('aria-label')).toBeTruthy();
      expect(control.find('i').attributes('aria-hidden')).toBe('true');
    }
  });
});
