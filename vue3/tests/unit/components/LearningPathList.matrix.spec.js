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
 * LearningPathList.vue - the overview as a matrix (Jest issue, area G1):
 * view x ownership x ownerless, and which controls a card offers.
 *
 * The view is what the server put into the store; Jest only checks what the
 * overview renders for it. Whether a person gets that view is PHPUnit's and
 * Playwright's (chains R).
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
import { keyStrings } from '../fixtures/storeFactory';

jest.mock('vuex', () => ({ useStore: jest.fn() }));
jest.mock('vue-router', () => ({ useRouter: jest.fn() }));
jest.mock('@kyvg/vue3-notification', () => ({ notify: jest.fn() }));

const LP = { id: 42, name: 'Linear A1', description: 'd', visibility: 1, image: '' };

/**
 * Mount the overview with one path.
 *
 * @param {object} c view, ownership ('owner' | 'editor' | 'neither'), ownerless
 */
async function overview(c) {
  useStore.mockReturnValue({
    state: {
      learningpaths: [{ ...LP, isowner: c.ownership === 'owner' ? 'true' : 'false', ownerless: !!c.ownerless }],
      viewlearningpaths: [],
      editablepaths: c.ownership === 'editor' ? { 42: { id: 42 } } : {},
      view: c.view,
      undoNodes: [],
      contextid: 1,
      user: 2,
      learningPathID: 0,
      strings: keyStrings(),
    },
    dispatch: jest.fn().mockResolvedValue(undefined),
    commit: jest.fn(),
  });
  useRouter.mockReturnValue({ push: jest.fn() });
  const wrapper = mount(LearningPathList, { global: { stubs: { HelpingSlider: true }, directives: { tooltip: {} } } });
  await wrapper.vm.$nextTick();
  const row = wrapper.find('[data-testid="learningpath-row-42"]');
  const has = (prefix) => row.findAll('button').some((b) => (b.attributes('aria-label') || '').startsWith(prefix));
  return {
    wrapper,
    create: wrapper.find('[data-testid="learningpath-create"]').exists(),
    visibility: row.find('[data-testid="learningpath-visibility-toggle-42"]').exists(),
    duplicate: has('duplicate:'),
    edit: has('edit:'),
    remove: has('delete:'),
  };
}

const rows = [];
for (const view of ['manager', 'assistant', 'teacheredit']) {
  for (const ownership of ['owner', 'editor', 'neither']) {
    rows.push({ view, ownership });
  }
}

// The contract as the template states it (LearningPathList.vue):
//   create      manager, assistant
//   visibility  editor of the path, manager, owner
//   duplicate   manager; assistant on an own path
//   edit        editor of the path, manager, owner
//   delete      manager; assistant on an own path
const expected = ({ view, ownership }) => {
  const manager = view === 'manager';
  const owner = ownership === 'owner';
  const editor = ownership === 'editor';
  return {
    create: manager || view === 'assistant',
    visibility: editor || manager || owner,
    duplicate: manager || (view === 'assistant' && owner),
    edit: editor || manager || owner,
    remove: manager || (view === 'assistant' && owner),
  };
};

describe('LearningPathList - view x ownership', () => {
  it.each(rows)('view=$view ownership=$ownership', async (c) => {
    const shown = await overview(c);
    const { wrapper, ...controls } = shown;
    expect(controls).toEqual(expected(c));
    wrapper.unmount();
  });
});

describe('LearningPathList - ownerless paths (#571)', () => {
  it.each([
    ['manager', true, true, true],
    ['manager', false, false, false],
    ['assistant', true, false, true],
    ['assistant', false, false, false],
  ])('view=%s ownerless=%s: banner %s, badge on the card %s', async (view, ownerless, banner, badge) => {
    const { wrapper } = await overview({ view, ownership: 'neither', ownerless });
    expect(wrapper.find('.ownerless-banner').exists()).toBe(banner);
    if (banner) {
      expect(wrapper.find('.ownerless-banner').text()).toBe('ownerless_banner (1)');
    }
    expect(wrapper.find('[data-testid="learningpath-row-42"] .ownerless-badge').exists()).toBe(badge);
  });
});
