import { mount } from '@vue/test-utils'
import { createStore } from 'vuex'
import CompletionItem from '../../../../components/completion/CompletionItem.vue';
import course_completed from '../../../../components/completion/conditions/course_completed.vue'
import manual from '../../../../components/completion/conditions/manual_check.vue'
import catquiz from '../../../../components/completion/conditions/catQuiz.vue'
import modquiz from '../../../../components/completion/conditions/modQuiz.vue'



describe('CompletionItem.vue', () => {
  let store

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          course_completion_minimum_amount: 'Select the minimum amount of finished courses',
          course_completion_choose_number: 'Choose a number of courses',
        },
        node: {
          course_node_id: ['node_1']  // Mocking the course_node_id
        }
      },
      actions: {
        fetchModQuizzes: jest.fn().mockResolvedValue([
          { id: 1, name: 'Quiz 1' },
          { id: 2, name: 'Quiz 2' },
        ]),
        fetchCatquizTests: jest.fn().mockResolvedValue([])
      }
    });
  });

  const createWrapper = (completion) => {
    return mount(CompletionItem, {
      props: {
        completion
      },
      global: {
        components: { course_completed, manual, catquiz, modquiz },
        plugins: [store]
      }
    })
  }

  it('renders course_completed component when label is "course_completed"', async () => {
    const wrapper = createWrapper({
      label: 'course_completed',
      value: { course_node_id: 'Course completed value' }
    });

    await wrapper.vm.$nextTick();

    // Check that the correct dynamic component is rendered
    const component = wrapper.findComponent(course_completed);
    expect(component.exists()).toBe(true);

    // Check that the v-model is bound correctly
    expect(component.props('completion')).toEqual({
      label: 'course_completed',
      value: { course_node_id: 'Course completed value' }
    });
  });

  it('renders manual component when label is "manual"', async () => {
    const wrapper = createWrapper({
      label: 'manual',
      value: { course_node_id: 'Manual check value' }
    });

    await wrapper.vm.$nextTick();

    // Check that the correct dynamic component is rendered
    const component = wrapper.findComponent(manual);
    expect(component.exists()).toBe(true);

    // Check that the v-model is bound correctly
    expect(component.props('completion')).toEqual({
      label: 'manual',
      value: { course_node_id: 'Manual check value' }
    });
  });

  it('defaults to manual component when label is not recognized', async () => {
    const wrapper = createWrapper({
      label: 'unknown_label',
      value: { course_node_id: 'Unknown label value' }
    });

    await wrapper.vm.$nextTick();

    // Since label is unrecognized, it should default to the manual component
    const component = wrapper.findComponent(manual);
    expect(component.exists()).toBe(true);

    // Check that the v-model is bound correctly
    expect(component.props('completion')).toEqual({
      label: 'unknown_label',
      value: { course_node_id: 'Unknown label value' }
    });
  });

  it('renders modquiz component when label is "modquiz"', async () => {
    const wrapper = createWrapper({
      label: 'modquiz',
      value: { course_node_id: 'ModQuiz value' }
    });

    await wrapper.vm.$nextTick();

    // Check that the correct dynamic component is rendered
    const component = wrapper.findComponent(modquiz);
    expect(component.exists()).toBe(true);

    // Check that the v-model is bound correctly
    expect(component.props('completion')).toEqual({
      label: 'modquiz',
      value: { course_node_id: 'ModQuiz value' }
    });
  });

  it('renders catquiz component when label is "catquiz"', async () => {
    const wrapper = createWrapper({
      label: 'catquiz',
      value: { course_node_id: 'CatQuiz value' }
    });

    await wrapper.vm.$nextTick();

    // Check that the correct dynamic component is rendered
    const component = wrapper.findComponent(catquiz);
    expect(component.exists()).toBe(true);

    // Check that the v-model is bound correctly
    expect(component.props('completion')).toEqual({
      label: 'catquiz',
      value: { course_node_id: 'CatQuiz value' }
    });
  });
});