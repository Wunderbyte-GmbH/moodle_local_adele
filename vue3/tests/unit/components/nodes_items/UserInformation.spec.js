import UserInformation from '../../../../components/nodes_items/UserInformation.vue';
import UserFeedbackBlock from '../../../../components/nodes_items/UserFeedbackBlock.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

// Mock the store

describe('UserInformation.vue', () => {
  let wrapper;
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          node_access_closed: 'Access closed',
          node_access_nothing_defined: 'Nothing defined',
          course_description_before_condition_course_completed: 'Before condition description',
          node_access_completed: 'Node status completed',
          node_access_not_accessible: 'Node status not accessible',
          course_description_inbetween_condition_course_completed: 'Inbetween condition description',
          course_description_after_condition_course_completed: 'After condition description',
        },
      },
    });

    wrapper = mount(UserInformation, {
      global: {
        plugins: [store],
        components: {
          UserFeedbackBlock
        }
      },
      props: {
        data: {
          node_id: '123',
          completion: {
            feedback: {
              status: 'closed',
              restriction: {
                before: { message: 'Some restriction data' }, // Wrap the string in an object
              },
              completion: {
                before: { message: 'Completion before data' }, // Wrap the string in an object
                inbetween: { message: 'Completion in-between data' }, // Wrap the string in an object
                after: { message: 'Completion after data' }, // Wrap the string in an object
                higher: { message: 'Completion higher data' }, // Wrap the string in an object
              }
            }
          }
        },
        mobile: false
      }
    });
  });

  it('renders the feedback toggle button correctly', () => {
    const toggleButton = wrapper.find('.toggle-button');
    expect(toggleButton.exists()).toBe(true);
  });

  it('toggles feedback area when toggle button is clicked', async () => {
    const toggleButton = wrapper.find('.toggle-button');

    expect(wrapper.vm.showFeedbackarea).toBe(false);
    await toggleButton.trigger('click');
    expect(wrapper.vm.showFeedbackarea).toBe(true);

    await toggleButton.trigger('click');
    expect(wrapper.vm.showFeedbackarea).toBe(false);
  });

  it('renders the feedback area with correct styling when shown', async () => {
    const toggleButton = wrapper.find('.toggle-button');
    await toggleButton.trigger('click');

    const feedbackArea = wrapper.find('.selectable');
    expect(feedbackArea.exists()).toBe(true);
    expect(feedbackArea.attributes('style')).toContain('position: absolute');
  });

  it('displays the correct status text when feedback is closed', async () => {
    const toggleButton = wrapper.find('.toggle-button');
    await toggleButton.trigger('click');

    const statusText = wrapper.find('.status-text');
    expect(statusText.text()).toContain('Access closed');
  });

  it('shows the "Nothing defined" message if no completion data exists', async () => {
    await wrapper.setProps({ data: { node_id: '123', completion: null } }); // Simulate no completion data
    const toggleButton = wrapper.find('.toggle-button');
    await toggleButton.trigger('click');

    const nothingDefinedText = wrapper.text();
    expect(nothingDefinedText).toContain('Nothing defined');
  });

  it('shows the not_accessible status', async () => {
    await wrapper.setProps({
      data: {
        node_id: '123',
        completion: {
          feedback: {
            status: 'not_accessible', // Updated feedback status
            restriction: {
              before: { message: 'Some restriction data' },
            },
            completion: {
              before: { message: 'Completion before data' },
              inbetween: { message: 'Completion in-between data' },
              after: { message: 'Completion after data' },
              higher: { message: 'Completion higher data' },
            }
          }
        }
      }
    });
    await wrapper.vm.$nextTick();
    const toggleButton = wrapper.find('.toggle-button');
    await toggleButton.trigger('click');

    const statusText = wrapper.find('.status-text');
    expect(statusText.text()).toContain('Node status not accessible');

    // Verify that the completion feedback blocks are rendered
    const completionBeforeBlock = wrapper.findComponent(UserFeedbackBlock);
    expect(completionBeforeBlock.exists()).toBe(true);
    expect(completionBeforeBlock.props('data')).toEqual({ message: 'Some restriction data' });
  });

  it('shows the completed status', async () => {
    await wrapper.setProps({
      data: {
        node_id: '123',
        completion: {
          feedback: {
            status: 'completed', // Updated feedback status
            restriction: {
              before: { message: 'Some restriction data' },
            },
            completion: {
              before: { message: 'Completion before data' },
              inbetween: { message: 'Completion in-between data' },
              after: { message: 'Completion after data' },
              higher: { message: 'Completion higher data' },
            }
          }
        }
      }
    });
    await wrapper.vm.$nextTick();

    const toggleButton = wrapper.find('.toggle-button');
    await toggleButton.trigger('click');

    const statusText = wrapper.find('.status-text');
    expect(statusText.text()).toContain('Node status completed');

    // Verify that the completion feedback blocks are rendered
    const completionBeforeBlock = wrapper.findComponent(UserFeedbackBlock);
    expect(completionBeforeBlock.exists()).toBe(true);
    expect(completionBeforeBlock.props('data')).toEqual({ message: 'Completion after data' });
  });
});