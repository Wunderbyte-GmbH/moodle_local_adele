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
    expect(checkbox.element.id).toBe('test-node-id')

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