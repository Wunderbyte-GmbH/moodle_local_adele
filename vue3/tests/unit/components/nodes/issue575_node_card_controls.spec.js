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
 * issue575_node_card_controls.spec module.
 *
 * The controls on a node card (#575 B2): access criteria, completion
 * criteria, edit, delete. They are icon-only buttons whose name used to live
 * in a title attribute alone - which is not exposed as an accessible name
 * here, so neither a screen reader user nor a test could address them by
 * what they do.
 *
 * The name carries the course as well, because a graph shows many cards and
 * four buttons called "Edit node" are four buttons nobody can tell apart.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import CustomNode from '../../../../components/nodes/CustomNode.vue';

const strings = {
  nodes_edit_restriction: 'Edit access criteria',
  edit_node_pretest: 'Edit completion criteria',
  edit_course_node: 'Edit node',
  flowchart_delete_button: 'Delete',
  locked: 'Locked',
  nodes_collection: 'Collection',
  node_status_accessible: 'accessible',
  node_status_not_accessible: 'not accessible yet',
  node_status_completed: 'completed',
  node_status_closed: 'locked',
  node_status_unknown: 'state unknown',
};

const store = createStore({
  state: {
    strings,
    view: 'manager',
    wwwroot: 'https://example.test',
    availablecourses: [],
    lpuserpathrelation: { image: '' },
    feedbacksettings: { show_feedback: false, show_info: false },
  },
});

const learningpath = {
  json: { tree: { nodes: [{ id: 'node-1', parentCourse: ['starting_node'], restriction: { nodes: [] } }] } },
};

const data = {
  node_id: 'node-1',
  fullname: 'Test Kurs 08',
  course_node_id: [10],
  progress: 0,
  animations: { seenrestriction: true, seencompletion: true },
  completion: {
    feedback: {
      status: 'accessible',
      status_restriction: 'before',
      completion: { before: null, inbetween: null, after: null, after_all: null },
    },
    restrictioncriteria: {},
    singlerestrictionnode: [],
  },
};

const mountCard = (editorview = true) => mount(CustomNode, {
  props: { data, learningpath, editorview, zoomstep: 1 },
  global: {
    plugins: [store],
    stubs: { Handle: true, NodeInformation: true, UserInformation: true, MasterConditions: true },
    directives: { tooltip: {} },
  },
});

describe('node card controls carry an accessible name (#575 B2)', () => {
  it.each([
    ['Edit access criteria: Test Kurs 08'],
    ['Edit completion criteria: Test Kurs 08'],
    ['Edit node: Test Kurs 08'],
    ['Delete: Test Kurs 08'],
  ])('names the control %s', (label) => {
    const wrapper = mountCard();
    expect(wrapper.find(`[aria-label="${label}"]`).exists()).toBe(true);
  });

  it('leaves no icon-only control without a name', () => {
    const wrapper = mountCard();
    // Collected rather than asserted one by one, so a failure names the
    // offending markup instead of just saying "false".
    const unnamed = wrapper.findAll('button')
      .filter((button) => !(button.attributes('aria-label') || button.text().trim()))
      .map((button) => button.html().replace(/\s+/g, ' ').slice(0, 80));
    expect(unnamed).toEqual([]);
  });
});
