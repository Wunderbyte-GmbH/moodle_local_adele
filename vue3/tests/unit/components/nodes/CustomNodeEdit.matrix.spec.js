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
 * CustomNodeEdit.vue - the node card as a matrix (Jest issue, area A2, L, M):
 * view x status x time condition x feedback settings.
 *
 * Checked per combination: play or lock, the hover hint, the time ring only
 * with a time condition, information and feedback area only where switched
 * on, master and manual controls only in the teacher view, and the hooks of
 * #574 (data-testid, data-status) and #575 (role, accessible name).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import CustomNodeEdit from '../../../../components/nodes/CustomNodeEdit.vue';
import { makeStore } from '../../fixtures/storeFactory';
import { makeNode } from '../../fixtures/nodeFactory';
import { makeLearningPath, makeTreeNode } from '../../fixtures/learningPathFactory';
import { makeCondition } from '../../fixtures/conditionFactory';
import { knownDefect } from '../../fixtures/knownDefect';

const STRINGS = {
  go_to_course: 'Go to course',
  node_not_accessible: 'Not accessible',
  node_status_accessible: 'accessible',
  node_status_completed: 'completed',
  node_status_not_accessible: 'locked',
  node_status_closed: 'closed',
};

const TIMED = {
  none: [],
  timed: [makeCondition('condition_1', 'timed')],
  timed_duration: [makeCondition('condition_1', 'timed_duration')],
  unrelated: [makeCondition('condition_1', 'parent_courses')],
};

const SETTINGS = {
  'info+feedback': { show_info: true, show_feedback: true },
  'info only': { show_info: true, show_feedback: false },
  'feedback only': { show_info: false, show_feedback: true },
  neither: { show_info: false, show_feedback: false },
};

/**
 * Mount one combination.
 *
 * @param {object} c view, status, timed, settings, manual flags, restriction state
 */
async function card(c) {
  const node = makeNode({
    node_id: 'dndnode_1',
    fullname: 'Testkurs 01',
    manualrestriction: !!c.manualrestriction,
    manualcompletion: !!c.manualcompletion,
    feedback: { status: c.status, status_restriction: c.statusRestriction || 'before' },
  });
  const wrapper = mount(CustomNodeEdit, {
    props: {
      data: node,
      learningpath: makeLearningPath([makeTreeNode('dndnode_1', { restriction: { nodes: TIMED[c.timed || 'none'] } })]),
      zoomstep: 1,
    },
    global: {
      plugins: [makeStore({ view: c.view, strings: STRINGS, feedbacksettings: SETTINGS[c.settings || 'info+feedback'] })],
      stubs: {
        Handle: true,
        NodeInformation: true,
        ProgressBar: true,
        UserInformation: true,
        MasterConditions: true,
        RestrictionOutPutItem: true,
        CompletionOutPutItem: true,
      },
    },
  });
  await wrapper.vm.$nextTick();
  return wrapper;
}

const rows = [];
for (const view of ['manager', 'teacher', 'student']) {
  for (const status of ['closed', 'not_accessible', 'accessible', 'completed']) {
    for (const timed of Object.keys(TIMED)) {
      rows.push({ view, status, timed });
    }
  }
}

describe('CustomNodeEdit - view x status x time condition', () => {
  it.each(rows)('view=$view status=$status timed=$timed', async (c) => {
    const wrapper = await card(c);
    const open = c.status === 'accessible' || c.status === 'completed';
    expect(wrapper.find('i.fa-play').exists()).toBe(open);
    expect(wrapper.find('i.fa-lock').exists()).toBe(!open);
    expect(wrapper.find('button.icon-link').attributes('title')).toBe(open ? 'Go to course' : 'Not accessible');
    expect(wrapper.find('.icon-with-ring').exists()).toBe(c.timed === 'timed' || c.timed === 'timed_duration');

    // #574 / #575: identity, state, role and name of the node.
    const root = wrapper.find('[role="group"]');
    expect(root.attributes('data-testid')).toBe('learningpath-node-dndnode_1');
    expect(root.attributes('data-status')).toBe(c.status);
    expect(root.attributes('aria-label')).toBe(`Testkurs 01 - ${STRINGS[`node_status_${c.status}`]}`);

    // Teacher-only controls.
    const teacher = c.view === 'teacher';
    expect(wrapper.findComponent({ name: 'MasterConditions' }).exists()).toBe(teacher);
  });

  it('an accessible node whose restriction is over ("after") shows the lock', async () => {
    const wrapper = await card({ view: 'student', status: 'accessible', statusRestriction: 'after' });
    expect(wrapper.find('i.fa-lock').exists()).toBe(true);
  });

  it.each([
    ['unknown status from the server', 'error: loop limit exceeded'],
    ['no status yet', undefined],
  ])('%s: data-status "unknown"', async (_label, status) => {
    const wrapper = await card({ view: 'student', status });
    expect(wrapper.find('[role="group"]').attributes('data-status')).toBe('unknown');
  });

  knownDefect.each([
    ['unknown status from the server', 'error: loop limit exceeded'],
    ['no status yet', undefined],
  ])('[known defect, draft local_adele-issue-unknown-status-shows-play] %s: locked, not "go to course"', async (_label, status) => {
    // The icon is decided on its own (onMounted: everything that is not
    // closed / not_accessible / after gets the play symbol), not from
    // useNodeStatus - so a node whose state is unknown shows the play
    // symbol and links to its course while data-status says "unknown".
    const wrapper = await card({ view: 'student', status });
    expect(wrapper.find('i.fa-lock').exists()).toBe(true);
    expect(wrapper.find('button.icon-link').attributes('title')).toBe('Not accessible');
  });
});

describe('CustomNodeEdit - feedback settings', () => {
  it.each(Object.keys(SETTINGS))('%s', async (settings) => {
    const wrapper = await card({ view: 'student', status: 'accessible', settings });
    expect(wrapper.findComponent({ name: 'NodeInformation' }).exists()).toBe(SETTINGS[settings].show_info);
    expect(wrapper.findComponent({ name: 'UserInformation' }).exists()).toBe(SETTINGS[settings].show_feedback);
  });
});

describe('CustomNodeEdit - manual controls, teacher view only', () => {
  const combos = [];
  for (const view of ['manager', 'teacher', 'student']) {
    for (const manualrestriction of [false, true]) {
      for (const manualcompletion of [false, true]) {
        combos.push({ view, manualrestriction, manualcompletion });
      }
    }
  }
  it.each(combos)('view=$view manual access=$manualrestriction manual completion=$manualcompletion', async (c) => {
    const wrapper = await card({ ...c, status: 'accessible' });
    const teacher = c.view === 'teacher';
    expect(wrapper.findComponent({ name: 'RestrictionOutPutItem' }).exists()).toBe(teacher && c.manualrestriction);
    expect(wrapper.findComponent({ name: 'CompletionOutPutItem' }).exists()).toBe(teacher && c.manualcompletion);
  });

  it('a teacher without editing rights gets none of them (#39)', async () => {
    const node = makeNode({ manualrestriction: true, manualcompletion: true, feedback: { status: 'accessible' } });
    const wrapper = mount(CustomNodeEdit, {
      props: { data: node, learningpath: makeLearningPath([makeTreeNode('dndnode_1')]), zoomstep: 1 },
      global: {
        plugins: [makeStore({ view: 'teacher', canedit: false, strings: STRINGS })],
        stubs: { Handle: true, NodeInformation: true, ProgressBar: true, UserInformation: true, MasterConditions: true, RestrictionOutPutItem: true, CompletionOutPutItem: true },
      },
    });
    await wrapper.vm.$nextTick();
    expect(wrapper.findComponent({ name: 'MasterConditions' }).exists()).toBe(false);
    expect(wrapper.findComponent({ name: 'RestrictionOutPutItem' }).exists()).toBe(false);
    expect(wrapper.findComponent({ name: 'CompletionOutPutItem' }).exists()).toBe(false);
  });
});
