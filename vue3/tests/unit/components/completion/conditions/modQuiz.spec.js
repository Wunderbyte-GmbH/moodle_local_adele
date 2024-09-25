import modQuiz from '../../../../../components/completion/conditions/modQuiz.vue';
import DropdownInput from '../../../../../components/nodes_items/DropdownInput.vue'
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex'
import { nextTick } from 'vue'


describe('modQuiz.vue', () => {
  let store

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          conditions_min_grad: 'Minimum garde to succeed',
        },
      },
      actions: {
        fetchModQuizzes: jest.fn().mockResolvedValue([
          { id: 1, name: 'Quiz 1' },
          { id: 2, name: 'Quiz 2' },
        ])
      }
    })
  })

  it('renders completion description when only one course_node_id is present', async() => {
    const wrapper = mount(modQuiz, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Complete this quiz' }
      }
    })

    // Check that the description is displayed
    await nextTick()
    const descriptionText = wrapper.find('.form-check').text()
    expect(descriptionText).toBe('Complete this quiz')
  })

  it('renders DropdownInput and handles quiz selection', async () => {
    const wrapper = mount(modQuiz, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Complete the quiz to pass the course.' }
      },
      components: {
        DropdownInput
      }
    })

    // Wait for the async data to be fetched and DOM to update
    await nextTick()

    const dropdownInput = wrapper.findComponent(DropdownInput)
    expect(dropdownInput.exists()).toBe(true)

    // Simulate selecting a quiz
    await dropdownInput.vm.$emit('update:value', { id: "1", name: 'Quiz 1' })
    expect(wrapper.vm.selectedQuiz).toBe("1")
    await dropdownInput.vm.$emit('update:value', null)
    expect(wrapper.vm.selectedQuiz).toBe(null)


  })

  it('renders the grade input when a quiz is selected', async () => {
    const wrapper = mount(modQuiz, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Complete the quiz to pass the course.' }
      }
    })

    // Simulate quiz selection
    wrapper.vm.selectedQuiz = '1'
    await nextTick()

    const gradeInput = wrapper.find('input#grade')
    expect(gradeInput.exists()).toBe(true)

    // Simulate entering a grade
    await gradeInput.setValue('80')

    expect(wrapper.vm.grade).toBe('80')
  })

  it('emits update:modelValue when grade or quiz selection changes', async () => {
    const wrapper = mount(modQuiz, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Complete the quiz to pass the course.' }
      }
    })

    // Simulate selecting a quiz
    wrapper.vm.selectedQuiz = '1'
    await nextTick()

    // Simulate entering a grade
    await wrapper.find('input#grade').setValue('75')

    // Check that the event has been emitted
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([{ quizid: "1", grade: '75' }])
  })

  it('completion values will be shown', async () => {
    const wrapper = mount(modQuiz, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: {
          description: 'Complete the quiz to pass the course.',
          value: {
            quizid: "1",
            grade: "10",
          }

        }
      }
    })
    await nextTick()
    await wrapper.vm.$nextTick();

    // Check if the selected quiz and grade are pre-populated from completion
    expect(wrapper.vm.selectedQuiz).toBe("1")
    expect(wrapper.vm.grade).toBe("10")

    // Check if the grade input field is populated with the correct value
    wrapper.vm.selectedQuiz = "1";  // Simulate setting this value
    await nextTick();
    const gradeInput = wrapper.find('input#grade')
    expect(gradeInput.exists()).toBe(true);
    expect(gradeInput.element.value).toBe('10')
  })

});