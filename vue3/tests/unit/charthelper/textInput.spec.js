import { shallowMount } from '@vue/test-utils';
import TextInputsComponent from '../../../components/charthelper/textInputs.vue';
import VueInputAutowidth from 'vue-input-autowidth';
import mockStore from '../../mocks/mockStore';

describe('GoalEditor.vue', () => {
  let goal;

  beforeAll(() => {
    goal = {
      name: 'Test Goal',
      description: 'Test Description',
    };
  })

  it('renders input fields for editing when view is not equal to "teacher"', async () => {
    let store = mockStore;

    const wrapper = shallowMount(TextInputsComponent, {
      global: {
        directives: {
          autowidth: VueInputAutowidth, // Provide the autowidth directive
        },
        provide: {
          store: store,
        },
      },
      props: {
        goal: goal,
      },
    });

    // Assert input fields are rendered
    expect(wrapper.find('#goalnameplaceholder').exists()).toBe(true);
    expect(wrapper.find('#goalsubjectplaceholder').exists()).toBe(true);
  });

  it('renders card elements for display when view is "teacher"', async () => {
    const store = { ...mockStore, state: { ...mockStore.state, view: 'teacher' } };

    const wrapper = shallowMount(TextInputsComponent, {
      global: {
        directives: {
          autowidth: VueInputAutowidth, // Provide the autowidth directive
        },
        provide: {
          store: store,
        },
      },
      props: {
        goal: goal,
      },
    });
    // Assert input fields are rendered
    expect(wrapper.find('.card-header').text()).toContain('Goal Title');
    expect(wrapper.text()).toContain('No name provided.');
    await expect(wrapper.text()).toContain('No description provided.');
    expect(wrapper.text()).toContain('Test Goal');
    expect(wrapper.text()).toContain('Test Description');
  });

  it('updates goalname and goaldescription when mounted', async () => {
    const store = { ...mockStore, state: { ...mockStore.state, view: 'not-teacher' } };
    const wrapper = shallowMount(TextInputsComponent, {
      global: {
        directives: {
          autowidth: VueInputAutowidth, // Provide the autowidth directive
        },
        provide: {
          store: store,
        },
      },
      props: {
        goal: goal,
      },
    });

    // Assert goalname and goaldescription are updated
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.goalname).toBe('Test Goal');
    expect(wrapper.vm.goaldescription).toBe('Test Description');
  });
});