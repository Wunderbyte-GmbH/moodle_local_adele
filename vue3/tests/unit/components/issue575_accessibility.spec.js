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
 * issue575_accessibility.spec module.
 *
 * Covers the three packages that have no visible surface of their own and
 * would therefore rot unnoticed: the live region (B4), the list alternative
 * to the graph (B3) and the keyboard route into the editor (B5).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// store.js pulls in Moodle's AMD modules, which only exist inside Moodle.
// Same stubs as tests/unit/store.spec.js; nothing here calls them.
jest.mock('core/ajax', () => ({ __esModule: true, default: { call: jest.fn(() => [Promise.resolve({})]) } }), { virtual: true });
jest.mock('core/localstorage', () => ({ __esModule: true, default: { get: jest.fn(), set: jest.fn() } }), { virtual: true });
jest.mock('core/notification', () => ({ __esModule: true, default: { alert: jest.fn() } }), { virtual: true });

import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import A11yLiveRegion from '../../../components/A11yLiveRegion.vue';
import LearningPathOutline from '../../../components/flowchart/LearningPathOutline.vue';
import KeyboardInsertDialog from '../../../components/flowchart/KeyboardInsertDialog.vue';
import { newlyReachedNodes } from '../../../store';

const strings = {
  node_status_accessible: 'accessible',
  node_status_closed: 'locked',
  node_status_completed: 'completed',
  node_status_not_accessible: 'not accessible yet',
  node_status_unknown: 'state unknown',
  node_not_accessible: 'Not accessible',
  outline_title: 'Learning path as a list',
  outline_state: 'State:',
  outline_prerequisites: 'Prerequisites:',
  outline_no_prerequisites: 'none',
  outline_open_course: 'Open course',
  outline_empty: 'This learning path has no courses yet.',
  insert_dialog_title: 'Insert course',
  insert_dialog_target: 'Attach to',
  insert_dialog_relation: 'Where should the course go?',
  insert_dialog_start: 'Start of the learning path',
  insert_dialog_after: 'after the selected course',
  insert_dialog_before: 'before the selected course',
  insert_dialog_and: 'in parallel (AND)',
  insert_dialog_or: 'as an alternative (OR)',
  insert_dialog_confirm: 'Insert',
  btncancel: 'Close',
};

const node = (id, name, status, parents = []) => ({
  id,
  parentCourse: parents,
  data: {
    fullname: name,
    course_node_id: [Number(id.replace(/\D/g, '')) || 1],
    completion: { feedback: { status } },
  },
});

const relationWith = (nodes) => ({ json: { tree: { nodes } } });

const storeWith = (state = {}) => createStore({
  state: { strings, wwwroot: 'https://example.test', ...state },
});

describe('live region (#575 B4)', () => {
  it('is present and empty before anything happens', () => {
    const wrapper = mount(A11yLiveRegion, { global: { plugins: [storeWith({ announcement: '' })] } });
    const region = wrapper.find('[data-testid="learningpath-announcer"]');
    // The element has to exist before the text appears in it, otherwise the
    // change is never announced.
    expect(region.exists()).toBe(true);
    expect(region.attributes('aria-live')).toBe('polite');
    expect(region.attributes('role')).toBe('status');
    expect(region.text()).toBe('');
  });

  it('shows what the store announced', async () => {
    const store = storeWith({ announcement: '' });
    const wrapper = mount(A11yLiveRegion, { global: { plugins: [store] } });
    store.state.announcement = 'Now accessible: Test Kurs 08';
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain('Now accessible: Test Kurs 08');
  });
});

describe('state changes worth announcing (#575 B4)', () => {
  const before = relationWith([node('n1', 'Kurs A', 'not_accessible'), node('n2', 'Kurs B', 'closed')]);

  it('reports only what actually improved', () => {
    const after = relationWith([node('n1', 'Kurs A', 'accessible'), node('n2', 'Kurs B', 'closed')]);
    expect(newlyReachedNodes(before, after)).toEqual({ accessible: ['Kurs A'], completed: [] });
  });

  it('stays silent when a recompute changes nothing', () => {
    expect(newlyReachedNodes(before, before)).toEqual({ accessible: [], completed: [] });
  });

  it('says nothing on the first load, when every node is new', () => {
    expect(newlyReachedNodes(null, before)).toEqual({ accessible: [], completed: [] });
  });

  it('reports a completion separately from an unlock', () => {
    const after = relationWith([node('n1', 'Kurs A', 'completed'), node('n2', 'Kurs B', 'accessible')]);
    expect(newlyReachedNodes(before, after)).toEqual({ accessible: ['Kurs B'], completed: ['Kurs A'] });
  });
});

describe('list alternative to the graph (#575 B3)', () => {
  const learningpath = relationWith([
    node('n2', 'Kurs B', 'not_accessible', ['n1']),
    node('n1', 'Kurs A', 'accessible', ['starting_node']),
  ]);

  const mountOutline = (path = learningpath) => mount(LearningPathOutline, {
    props: { learningpath: path },
    global: { plugins: [storeWith()] },
  });

  it('lists the path in its business order, not in drawing order', () => {
    const items = mountOutline().findAll('li');
    expect(items).toHaveLength(2);
    expect(items[0].text()).toContain('Kurs A');
    expect(items[1].text()).toContain('Kurs B');
  });

  it('names the state of every course as text', () => {
    const items = mountOutline().findAll('li');
    expect(items[0].attributes('data-status')).toBe('accessible');
    expect(items[0].text()).toContain('accessible');
    expect(items[1].text()).toContain('not accessible yet');
  });

  it('names the prerequisites instead of drawing them', () => {
    const items = mountOutline().findAll('li');
    expect(items[0].text()).toContain('none');
    expect(items[1].text()).toContain('Kurs A');
  });

  it('offers the course link exactly where the graph does', () => {
    const items = mountOutline().findAll('li');
    // Accessible: link. Not accessible: no link, and it says so.
    expect(items[0].find('a').attributes('href')).toContain('/course/view.php?id=1');
    expect(items[1].find('a').exists()).toBe(false);
    expect(items[1].text()).toContain('Not accessible');
  });

  it('lists a node caught in a cycle rather than dropping it', () => {
    const cyclic = relationWith([
      node('n1', 'Kurs A', 'accessible', ['n2']),
      node('n2', 'Kurs B', 'accessible', ['n1']),
    ]);
    expect(mountOutline(cyclic).findAll('li')).toHaveLength(2);
  });

  it('says so when the path is empty', () => {
    expect(mountOutline(relationWith([])).text()).toContain('no courses yet');
  });
});

describe('keyboard route into the editor (#575 B5)', () => {
  const course = { fullname: 'Kurs C', course_node_id: [7] };
  const nodes = [
    { id: 'starting_node', data: {} },
    { id: 'dropzone_parent', data: {} },
    node('n1', 'Kurs A', 'accessible'),
  ];

  const mountDialog = () => mount(KeyboardInsertDialog, {
    props: { course, nodes },
    attachTo: document.body,
    global: { plugins: [storeWith()] },
  });

  it('is a modal dialog named after the course being inserted', () => {
    const dialog = mountDialog().find('[data-testid="learningpath-insert-dialog"]');
    expect(dialog.attributes('role')).toBe('dialog');
    expect(dialog.attributes('aria-modal')).toBe('true');
    expect(dialog.attributes('aria-label')).toContain('Kurs C');
  });

  it('offers the start and the real nodes as targets, but no dropzone', () => {
    const options = mountDialog().find('[data-testid="learningpath-insert-target"]').findAll('option');
    expect(options.map((option) => option.text())).toEqual(['Start of the learning path', 'Kurs A']);
  });

  it('hands over node and relation, so the drop path can do the insertion', async () => {
    const wrapper = mountDialog();
    await wrapper.find('[data-testid="learningpath-insert-target"]').setValue('n1');
    await wrapper.find('[data-testid="learningpath-insert-relation"]').setValue('dropzone_before');
    await wrapper.find('[data-testid="learningpath-insert-relation"]').setValue('dropzone_parent');
    await wrapper.find('[data-testid="learningpath-insert-confirm"]').trigger('click');

    expect(wrapper.emitted('insert')).toHaveLength(1);
    expect(wrapper.emitted('insert')[0][0]).toEqual({
      course, targetId: 'n1', dropzone: 'dropzone_parent',
    });
  });

  it('attaches to the start without asking for a relation', async () => {
    const wrapper = mountDialog();
    await wrapper.find('[data-testid="learningpath-insert-confirm"]').trigger('click');
    expect(wrapper.emitted('insert')[0][0].dropzone).toBe('starting_node');
  });

  it('takes the focus when it opens and gives it back when it closes', async () => {
    const opener = document.createElement('button');
    document.body.appendChild(opener);
    opener.focus();
    expect(document.activeElement).toBe(opener);

    const wrapper = mountDialog();
    await wrapper.vm.$nextTick();
    expect(document.activeElement).toBe(wrapper.find('[data-testid="learningpath-insert-target"]').element);

    wrapper.unmount();
    expect(document.activeElement).toBe(opener);
    opener.remove();
  });

  it('cancels on Escape', async () => {
    const wrapper = mountDialog();
    await wrapper.find('.local-adele-dialog-backdrop').trigger('keydown.esc');
    expect(wrapper.emitted('cancel')).toHaveLength(1);
  });
});
