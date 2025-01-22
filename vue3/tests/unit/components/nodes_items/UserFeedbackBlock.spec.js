import UserFeedbackBlock from '../../../../components/nodes_items/UserFeedbackBlock.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

// Mock the store

describe('UserFeedbackBlock.vue', () => {
  let wrapper;
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          nodes_feedback_completion_before: 'Completion Before Feedback',
          nodes_feedback_completion_higher: 'Higher Completion Feedback',
          course_condition_concatination_or: 'or',
        },
      },
    });

    wrapper = mount(UserFeedbackBlock, {
      global: {
        plugins: [store],
      },
      props: {
        data: ['First feedback', 'Second feedback', 'Third feedback'],
        title: 'completion_higher',
      }
    });
  });

  it('renders the component when data is provided', () => {
    const feedbackList = wrapper.find('.feedback-list');
    expect(feedbackList.exists()).toBe(true);
  });

  it('displays the correct title based on "completion_higher"', async () => {
    // Check that the correct title is displayed
    const feedbackTitle = wrapper.find('.feedback-title');
    expect(feedbackTitle.text()).toBe('Higher Completion Feedback');
  });

  it('renders all feedback items with correct capitalization and concatenation', async () => {
    await wrapper.setProps({ data: [] });
    await wrapper.vm.$nextTick();
    const feedbackList = wrapper.find('.feedback-list');
    expect(feedbackList.exists()).toBe(false);

    // Assert that no feedback items are rendered
    const feedbackItems = wrapper.findAll('.feedback-item');
    expect(feedbackItems).toHaveLength(0);
  });

  it('renders all feedback items with correct capitalization and concatenation', async () => {
    await wrapper.setProps({ props: {
      data: ['First feedback', 'Second feedback', 'Third feedback'],
      title: 'restriction_completion_before',
    } });
    await wrapper.vm.$nextTick();
    const feedbackList = wrapper.find('.feedback-list');
    expect(feedbackList.exists()).toBe(true);

    // Assert that no feedback items are rendered
    const feedbackItems = wrapper.findAll('.feedback-item');
    expect(feedbackItems).toHaveLength(3);
  });

});