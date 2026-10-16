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
 * issue574_575_node_state.spec module.
 *
 * A rendered course node must say what it is and what state it is in,
 * without anybody having to read its colour: data-testid and data-status
 * (#574), role and accessible name (#575 B1).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import CustomNodeEdit from '../../../../components/nodes/CustomNodeEdit.vue';

const strings = {
  node_status_accessible: 'accessible',
  node_status_closed: 'locked',
  node_status_completed: 'completed',
  node_status_not_accessible: 'not accessible yet',
  node_status_unknown: 'state unknown',
  go_to_course: 'Go to course',
  node_not_accessible: 'Not accessible',
};

const store = createStore({
  state: {
    strings,
    view: 'student',
    wwwroot: 'https://example.test',
    availablecourses: [],
    lpuserpathrelation: { image: '' },
    feedbacksettings: { show_feedback: false, show_info: false },
  },
});

const learningpath = {
  json: {
    tree: {
      nodes: [{ id: 'node-1', parentCourse: ['starting_node'], restriction: { nodes: [] } }],
    },
  },
};

const nodeData = (status) => ({
  node_id: 'node-1',
  fullname: 'Test Kurs 08',
  course_node_id: [10],
  progress: 0,
  animations: { seenrestriction: true, seencompletion: true },
  completion: {
    feedback: {
      status,
      status_restriction: 'before',
      completion: { before: null, inbetween: null, after: null, after_all: null },
    },
    restrictioncriteria: {},
    singlerestrictionnode: [],
  },
});

const mountNode = (status) => mount(CustomNodeEdit, {
  props: { data: nodeData(status), learningpath, zoomstep: 1 },
  global: {
    plugins: [store],
    stubs: { Handle: true, NodeInformation: true, UserInformation: true, MasterConditions: true },
    directives: { tooltip: {} },
  },
});

describe('course node: machine-readable identity and state (#574, #575)', () => {
  it('carries its business identity as data-testid', () => {
    const root = mountNode('accessible');
    expect(root.attributes('data-testid')).toBe('learningpath-node-node-1');
  });

  it.each([
    ['accessible', 'accessible'],
    ['completed', 'completed'],
    ['not_accessible', 'not accessible yet'],
    ['closed', 'locked'],
  ])('renders data-status %s and names the node with its state', (status, label) => {
    const root = mountNode(status);
    expect(root.attributes('data-status')).toBe(status);
    // role is what makes the name reachable: aria-label on a bare div is not
    // exposed to assistive technology.
    expect(root.attributes('role')).toBe('group');
    expect(root.attributes('aria-label')).toBe('Test Kurs 08 - ' + label);
  });

  it('changes the attribute when the state changes at runtime', async () => {
    const wrapper = mountNode('not_accessible');
    expect(wrapper.attributes('data-status')).toBe('not_accessible');

    await wrapper.setProps({ data: nodeData('completed') });

    expect(wrapper.attributes('data-status')).toBe('completed');
    expect(wrapper.attributes('aria-label')).toBe('Test Kurs 08 - completed');
  });

  it('reports an unknown state rather than the backend error string', () => {
    const root = mountNode('error: loop limit exceeded');
    expect(root.attributes('data-status')).toBe('unknown');
  });
});
