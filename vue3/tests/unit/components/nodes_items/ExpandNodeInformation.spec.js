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
 * Expand Node Information.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ExpandNodeInformation from '../../../../components/nodes_items/ExpandNodeInformation.vue';
import { mount } from '@vue/test-utils';
import { useStore } from 'vuex';
import * as nodeColors from '../../../../config/nodeColors';

/**
 * The style attribute reports colours as rgb(); compare against the palette
 * rather than against a hex literal, so a contrast correction (#575 B6) does
 * not break a test that is about WHICH colour is chosen, not which value it
 * has.
 *
 * @param {string} hex A colour from config/nodeColors.
 * @returns {string} The same colour as the style attribute reports it.
 */
const asRgb = (hex) => {
  const value = hex.replace('#', '');
  const part = (at) => parseInt(value.slice(at, at + 2), 16);
  return `rgb(${part(0)}, ${part(2)}, ${part(4)})`;
};

// Mock the store
jest.mock('vuex', () => ({
  useStore: jest.fn(),
}));

describe('ExpandNodeInformation.vue', () => {
  let storeMock;

  beforeEach(() => {
    storeMock = {
      state: {
        strings: {
          LIGHT_GRAY: '#f0f0f0',
          nodes_no_description: 'No description available',
        },
      },
    };
    useStore.mockReturnValue(storeMock);
  })

  it('colours the info badge by course completion status', async () => {
    const courses = [{ id: 1, description: 'This is a test course description.' }];

    // Not completed -> the "not finished" status colour.
    const notDone = mount(ExpandNodeInformation, { props: { courses } });
    expect(notDone.find('.icon-container').exists()).toBe(true);
    expect(notDone.find('.information').element.style.backgroundColor)
      .toBe(asRgb(nodeColors.courseNodeNotFinishedColor));

    // Completed -> the "finished" status colour.
    const done = mount(ExpandNodeInformation, {
      props: {
        courses,
        data: { course_id: 1, completion: { completioncriteria: { course_completed: { completed: { 1: true } } } } },
      },
    });
    expect(done.find('.information').element.style.backgroundColor)
      .toBe(asRgb(nodeColors.courseNodeFinishedColor));
  });

  it('toggles the additional card visibility on click', async () => {
    const courses = [
      {
        id: 1,
        description: 'This is a test course description.',
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    // Initially, the card should not be visible
    expect(wrapper.find('.additional-card').exists()).toBe(false);
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(true);
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(false);
  });

  it('renders the course description if available', async () => {
    const courses = [
      {
        id: 1,
        description: 'This is a test course description.',
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });
    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.list-group-text').element.innerHTML).toContain(courses[0].description);
  });

  it('renders the course summary if description is not available', async () => {
    const courses = [
      {
        id: 1,
        summary: 'This is a test course summary.',
      },
    ];
    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });
    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.list-group-text').element.innerHTML).toContain(courses[0].summary);
  });

  it('renders a fallback text if neither description nor summary is available', async () => {
    const courses = [
      {
        id: 1,
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });
    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.list-group-text').text()).toBe(storeMock.state.strings.nodes_no_description);
  });

  it('falls back to the stack description when the course has none (#484)', async () => {
    const wrapper = mount(ExpandNodeInformation, {
      props: {
        courses: [{ id: 1 }],
        data: { course_id: 1, description: 'STACK-DESC' },
      },
    });
    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.list-group-text').element.innerHTML).toContain('STACK-DESC');
  });

  it('renders the first course description if multiple courses are provided', async () => {
    const courses = [
      { id: 1, description: 'First course description.' },
      { id: 2, description: 'Second course description.' },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.list-group-text').element.innerHTML).toContain(courses[0].description);
  });

  it('renders the fallback text if courses array is empty', async () => {
    const courses = [];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.list-group-text').text()).toBe(storeMock.state.strings.nodes_no_description);
  });

  it('handles null or undefined description/summary gracefully', async () => {
    const courses = [
      { id: 1, description: null },
      { id: 2, summary: undefined },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.list-group-text').text()).toBe(storeMock.state.strings.nodes_no_description);
  });

  it('correctly toggles card visibility using toggleCard method', async () => {
    const courses = [{ id: 1, description: 'Test description.' }];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    expect(wrapper.vm.showCard).toBe(false);
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.vm.showCard).toBe(true);
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.vm.showCard).toBe(false);
  });

  it('renders additional-card correctly based on showCard', async () => {
    const courses = [
      {
        id: 1,
        description: 'This is a test course description.',
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    // Initially, the additional card should not be visible
    expect(wrapper.find('.additional-card').exists()).toBe(false);

    // Trigger click to show the card
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(true);

    // Trigger click again to hide the card
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(false);
  });

  it('renders the node description defined in the editor, even without a course description (#484)', async () => {
    const wrapper = mount(ExpandNodeInformation, {
      props: {
        courses: [{ id: 1 }], // no course description or summary
        data: { description: 'Node description from the learning-path editor' },
      },
    });

    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    const text = wrapper.find('.list-group-text').text();
    expect(text).toContain('Node description from the learning-path editor');
    // Must NOT fall back to the "no course description" hint when a node description exists.
    expect(text).not.toBe(storeMock.state.strings.nodes_no_description);
  });

  // Retest feedback on #484 (2026-07-29): this dialog belongs to ONE course card,
  // so when the course carries its OWN editor text it must win over the (stack)
  // node's description - the earlier fix had the priority inverted, which made
  // every course inside a stack show the stack's description.
  it('prioritises the course\'s own description over the node description (#484)', async () => {
    const wrapper = mount(ExpandNodeInformation, {
      props: {
        courses: [{ id: 1, description: 'Course level description' }],
        data: { description: 'Node level description' },
      },
    });

    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    const text = wrapper.find('.list-group-text').text();
    expect(text).toContain('Course level description');
    expect(text).not.toContain('Node level description');
  });

  it('uses the store LIGHT_GRAY for the popup card background', async () => {
    storeMock.state.strings.LIGHT_GRAY = '#aaaaaa';

    const courses = [
      {
        id: 1,
        description: 'This is a test course description.',
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    // LIGHT_GRAY drives the popup card (.additional-card), not the status badge; reveal it first.
    await wrapper.find('.icon-container').trigger('click');
    const card = wrapper.find('.additional-card');
    expect(card.element.style.backgroundColor).toBe('rgb(170, 170, 170)'); // #aaaaaa
  });

});