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
 * Issue #591: the information card (i symbol) and the feedback area (speech
 * bubble) of a node open without a mouse.
 *
 * Both triggers were <div>s with a click handler inside a focusable
 * container: the container took the focus, no key opened anything, and a
 * screen reader was told neither a name nor a state. Now each trigger is a
 * <button> with an accessible name, aria-expanded and aria-controls; Escape
 * closes. (Enter and Space activate a button natively - that part is proven
 * in the browser, tests/e2e/tests/feedback-texts.spec.ts.)
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import NodeInformation from '../../../../components/nodes_items/NodeInformation.vue';
import UserInformation from '../../../../components/nodes_items/UserInformation.vue';
import ExpandNodeInformation from '../../../../components/nodes_items/ExpandNodeInformation.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const strings = {
  a11y_node_information: 'Access and completion criteria',
  a11y_node_feedback: 'Feedback on your progress',
  completion_restriction_feedback: 'Restrictions',
  completion_completion_feedback: 'Completions',
  completion_nothing_defined_feedback: 'Nothing defined',
  DARK_RED: '#8b0000',
  LIGHT_GRAY: '#eeeeee',
};

const data = (fullname) => ({
  node_id: 'dndnode_7',
  fullname,
  animations: { seencompletion: true },
  completion: {
    restrictioncriteria: {},
    feedback: {
      status: 'accessible',
      restriction: { before: null, information: undefined },
      completion: { before: null, inbetween: null, information: undefined },
    },
  },
});

const mountIt = (component, fullname = 'Testkurs 01') =>
  mount(component, {
    global: { plugins: [createStore({ state: { strings, view: 'student' } })] },
    props: { data: data(fullname), parentnode: {}, mobile: false },
  });

describe.each([
  ['information card', NodeInformation, '.information', '.additional-card',
    'Access and completion criteria', 'adele-node-information-dndnode_7'],
  ['feedback area', UserInformation, '.toggle-button', '.selectable',
    'Feedback on your progress', 'adele-node-feedback-dndnode_7'],
])('#591 the %s of a node', (name, component, trigger, panel, label, id) => {
  it('is opened by a real button with a name that says which node', () => {
    const button = mountIt(component).find(trigger);
    expect(button.element.tagName).toBe('BUTTON');
    expect(button.attributes('type')).toBe('button');
    expect(button.attributes('aria-label')).toBe(label + ': Testkurs 01');
  });

  it('names itself without a node name when the node has none', () => {
    expect(mountIt(component, '').find(trigger).attributes('aria-label')).toBe(label);
  });

  it('tells its state and what it controls, and follows the click', async () => {
    const wrapper = mountIt(component);
    const button = wrapper.find(trigger);
    expect(button.attributes('aria-expanded')).toBe('false');
    expect(button.attributes('aria-controls')).toBe(id);
    expect(wrapper.find(panel).exists()).toBe(false);

    await button.trigger('click');
    expect(button.attributes('aria-expanded')).toBe('true');
    expect(wrapper.find(panel).attributes('id')).toBe(id);

    await button.trigger('click');
    expect(button.attributes('aria-expanded')).toBe('false');
    expect(wrapper.find(panel).exists()).toBe(false);
  });

  it('is closed by Escape, and Escape on a closed one does nothing', async () => {
    const wrapper = mountIt(component);
    const button = wrapper.find(trigger);
    await button.trigger('keydown', { key: 'Escape' });
    expect(wrapper.find(panel).exists()).toBe(false);

    await button.trigger('click');
    expect(wrapper.find(panel).exists()).toBe(true);
    await button.trigger('keydown', { key: 'Escape' });
    expect(wrapper.find(panel).exists()).toBe(false);
    expect(button.attributes('aria-expanded')).toBe('false');
  });

  it('is the only tab stop: the container is not focusable itself', () => {
    const wrapper = mountIt(component);
    expect(wrapper.element.getAttribute('tabindex')).toBeNull();
  });

  it('hides the decorative icon from assistive technology', () => {
    expect(mountIt(component).find(trigger + ' i').attributes('aria-hidden')).toBe('true');
  });
});

// The same i symbol on the course cards inside a stack ("Lernpaket").
describe('#591 the information card of a course in a stack', () => {
  const mountCourse = () =>
    mount(ExpandNodeInformation, {
      global: { plugins: [createStore({ state: { strings, view: 'student' } })] },
      props: { courses: { 0: { fullname: 'Testkurs 02', description: 'About this course' }, id: 42 }, data: {} },
    });

  it('is opened by a named button that tells its state', async () => {
    const wrapper = mountCourse();
    const button = wrapper.find('.information');
    expect(button.element.tagName).toBe('BUTTON');
    expect(button.attributes('aria-label')).toBe('Access and completion criteria: Testkurs 02');
    expect(button.attributes('aria-expanded')).toBe('false');
    expect(button.attributes('aria-controls')).toBe('adele-course-information-42');

    await button.trigger('click');
    expect(button.attributes('aria-expanded')).toBe('true');
    expect(wrapper.find('.additional-card').attributes('id')).toBe('adele-course-information-42');
  });

  it('is closed by Escape', async () => {
    const wrapper = mountCourse();
    const button = wrapper.find('.information');
    await button.trigger('click');
    await button.trigger('keydown', { key: 'Escape' });
    expect(wrapper.find('.additional-card').exists()).toBe(false);
  });

  it('opens once per click - the button does not toggle a second time', async () => {
    const wrapper = mountCourse();
    await wrapper.find('.information').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(true);
  });
});
