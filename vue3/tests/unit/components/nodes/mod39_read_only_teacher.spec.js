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
 * The read-only teacher's view (mod_adele #39): a teacher without editing
 * rights sees a learner's node and none of the controls that change it.
 *
 * @package    local_adele
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CustomNodeEdit from '../../../../components/nodes/CustomNodeEdit.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const mountNode = (state) => mount(CustomNodeEdit, {
  global: {
    plugins: [createStore({
      state: {
        strings: {},
        view: 'teacher',
        wwwroot: 'https://example.test',
        availablecourses: [],
        lpuserpathrelation: { image: '' },
        feedbacksettings: { show_feedback: true, show_info: true },
        ...state,
      },
    })],
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
  props: {
    data: {
      node_id: 'node-1',
      fullname: 'My node',
      course_node_id: [10],
      progress: 50,
      manualrestriction: true,
      manualcompletion: true,
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
    },
    learningpath: { json: { tree: { nodes: [{ id: 'node-1', parentCourse: ['starting_node'], restriction: { nodes: [] } }] } } },
    zoomstep: 1,
  },
});

const controls = ['master-conditions-stub', 'restriction-out-put-item-stub', 'completion-out-put-item-stub'];

describe('CustomNodeEdit.vue in the teacher\'s view (mod_adele #39)', () => {
  it('offers the controls that change a learner\'s node to a teacher who may edit', async () => {
    const wrapper = mountNode({ canedit: true });
    await wrapper.vm.$nextTick();
    controls.forEach((control) => expect(wrapper.find(control).exists()).toBe(true));
  });

  it('offers them when the page says nothing about editing (every view before #39)', async () => {
    const wrapper = mountNode({});
    await wrapper.vm.$nextTick();
    controls.forEach((control) => expect(wrapper.find(control).exists()).toBe(true));
  });

  it('offers none of them in the read-only view', async () => {
    const wrapper = mountNode({ canedit: false });
    await wrapper.vm.$nextTick();
    controls.forEach((control) => expect(wrapper.find(control).exists()).toBe(false));
  });
});
