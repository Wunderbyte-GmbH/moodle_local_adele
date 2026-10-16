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
 * manual output.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import manual_output from '../../../../../components/completion/conditions_output/manual_output.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex'


describe('manual_output.vue', () => {
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          conditions_finish_course: 'Finish Course'
        }
      }
    })
  });

  it('renders checkbox with correct label from Vuex store', () => {
    const wrapper = mount(manual_output, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: false,
        data: { node_id: 'test-node-id' }
      }
    })
    const checkbox = wrapper.find('input[type="checkbox"]')
    expect(checkbox.exists()).toBe(true)
    // Its own id - the release box of the same node has another (#598).
    expect(checkbox.element.id).toBe('test-node-id_manual_completion')
    expect(wrapper.find('label').attributes('for')).toBe('test-node-id_manual_completion')

    const label = wrapper.find('label')
    expect(label.text()).toBe('Finish Course')
  });

  it('reflects modelValue correctly', async () => {
    const wrapper = mount(manual_output, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: true,
        data: { node_id: 'test-node-id' }
      }
    })

    const checkbox = wrapper.find('input[type="checkbox"]')
    expect(checkbox.element.checked).toBe(true)
    await wrapper.setProps({ modelValue: false })
    expect(checkbox.element.checked).toBe(false)
  })

  it('emits update:modelValue event when checkbox is changed', async () => {
    const wrapper = mount(manual_output, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: false,
        data: { node_id: 'test-node-id' }
      }
    })
    const checkbox = wrapper.find('input[type="checkbox"]')
    await checkbox.setChecked(true)
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([true])
  })

});