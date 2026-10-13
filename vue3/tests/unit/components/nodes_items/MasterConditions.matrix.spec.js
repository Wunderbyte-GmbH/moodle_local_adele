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
 * MasterConditions.vue - the truth table of the two master checkboxes
 * (Jest issue, area B1): every combination, both directions, legacy data,
 * and that the two flags never move each other.
 *
 * Jest checks what the component does with the data it is given. Whether a
 * master flag opens a course is Playwright's (chain O).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import MasterConditions from '../../../../components/nodes_items/MasterConditions.vue';
import { mount } from '@vue/test-utils';
import { makeStore } from '../../fixtures/storeFactory';

const LABELS = {
  course_master_conditions: 'Master conditions',
  course_master_condition_restriction: 'Master access',
  course_master_condition_completion: 'Master completion',
};

/**
 * Mount with the given node data, the panel opened.
 *
 * @param {object} data
 * @returns {Promise<object>} wrapper, data (the very object passed in), boxes
 */
async function open(data) {
  const wrapper = mount(MasterConditions, {
    props: { data },
    global: { plugins: [makeStore({ strings: LABELS })] },
  });
  await wrapper.find('button').trigger('click');
  return {
    wrapper,
    access: () => wrapper.find(`#${data.node_id}_master_restriction`),
    completion: () => wrapper.find(`#${data.node_id}_master_completion`),
  };
}

const nodeWith = (master) => ({ node_id: 'dndnode_2', completion: master === 'missing' ? {} : { master } });

describe('MasterConditions.vue - the four combinations', () => {
  it.each([
    [false, false],
    [true, false],
    [false, true],
    [true, true],
  ])('access=%s completion=%s: rendered as given', async (restriction, completion) => {
    const data = nodeWith({ restriction, completion });
    const { access, completion: done } = await open(data);
    expect(access().element.checked).toBe(restriction);
    expect(done().element.checked).toBe(completion);
  });
});

describe('MasterConditions.vue - false -> true -> false, and independence', () => {
  it.each([
    ['access', 'restriction', 'completion'],
    ['completion', 'completion', 'restriction'],
  ])('%s: ticked and unticked by the person; the data follows; the other flag never moves', async (_name, flag, other) => {
    const data = nodeWith({ restriction: false, completion: false });
    const boxes = await open(data);
    const box = flag === 'restriction' ? boxes.access : boxes.completion;
    const otherbox = other === 'restriction' ? boxes.access : boxes.completion;

    await box().setValue(true);
    expect(data.completion.master[flag]).toBe(true);
    expect(data.completion.master[other]).toBe(false);
    expect(otherbox().element.checked).toBe(false);

    await box().setValue(false);
    expect(data.completion.master[flag]).toBe(false);
    expect(data.completion.master[other]).toBe(false);
  });

  it('with the other flag already set, toggling one keeps the other set', async () => {
    const data = nodeWith({ restriction: true, completion: false });
    const { completion } = await open(data);
    await completion().setValue(true);
    await completion().setValue(false);
    expect(data.completion.master).toEqual({ restriction: true, completion: false });
  });
});

describe('MasterConditions.vue - default and legacy data', () => {
  it.each([
    ['completion.master missing', 'missing'],
    ['completion.master null', null],
    ['completion.master.completion missing', { restriction: false }],
    ['completion.master.restriction missing', { completion: false }],
    ['completion.master empty', {}],
  ])('%s: both flags become false', async (_label, master) => {
    const data = nodeWith(master);
    const { access, completion } = await open(data);
    expect(data.completion.master).toEqual({ completion: false, restriction: false });
    expect(access().element.checked).toBe(false);
    expect(completion().element.checked).toBe(false);
  });

  it('a flag that is set survives the defaults', async () => {
    const data = nodeWith({ completion: true });
    await open(data);
    expect(data.completion.master).toEqual({ completion: true, restriction: false });
  });
});

describe('MasterConditions.vue - labels', () => {
  it('both checkboxes are reachable by their label, with the texts from the store', async () => {
    const { wrapper } = await open(nodeWith({ restriction: false, completion: false }));
    expect(wrapper.find('button').text()).toBe('Master conditions');
    expect(wrapper.find('label[for="dndnode_2_master_restriction"]').text()).toBe('Master access');
    expect(wrapper.find('label[for="dndnode_2_master_completion"]').text()).toBe('Master completion');
  });
});
