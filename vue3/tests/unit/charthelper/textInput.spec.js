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
  });

  const createWrapper = (customStore) => {
    const store = customStore || mockStore;
    return shallowMount(TextInputsComponent, {
      global: {
        directives: {
          autowidth: VueInputAutowidth,
        },
        provide: {
          store: store,
        },
      },
      props: {
        goal: goal,
      },
    });
  };

  it('renders input fields for editing when view is not equal to "teacher"', async () => {
    const store = mockStore;

    const wrapper = createWrapper(store);

    // Assert input fields are rendered
    expect(wrapper.find('#goalnameplaceholder').exists()).toBe(true);
    expect(wrapper.find('#goalsubjectplaceholder').exists()).toBe(true);
  });

  it('renders card elements for display when view is "teacher"', async () => {
    const store = { ...mockStore, state: { ...mockStore.state, view: 'teacher' } };
    const wrapper = createWrapper(store);

    // Assert card elements are rendered
    expect(wrapper.find('.card-header').text()).toContain('Goal Title');
    expect(wrapper.text()).toContain('No name provided.');
    expect(wrapper.text()).toContain('No description provided.');
  });

  it('updates goalname and goaldescription when mounted', async () => {
    const store = { ...mockStore, state: { ...mockStore.state, view: 'not-teacher' } };
    const wrapper = createWrapper(store);
    

    // Wait for Vue to update the data
    await wrapper.vm.$nextTick();

    // Assert goalname and goaldescription are updated
    expect(wrapper.vm.goalname).toBe('Test Goal');
    expect(wrapper.vm.goaldescription).toBe('Test Description');
  });

  it('emits "change-GoalName" when goalname changes', async () => {
    const wrapper = createWrapper();
  
    // Find the input element for goalname and simulate change
    wrapper.find('#goalnameplaceholder').setValue('New Goal Name');
  
    // Assert emit is called with correct value
    await wrapper.vm.$nextTick();
    expect(wrapper.emitted('change-GoalName')).toBeTruthy();
    expect(wrapper.emitted('change-GoalName')[0]).toEqual(['New Goal Name']);
  });

  it('emits "change-GoalName" when goalname changes', async () => {
    const wrapper = createWrapper();
  
    // Find the input element for goalname and simulate change
    wrapper.find('#goalsubjectplaceholder').setValue('New Goal Description');
  
    // Assert emit is called with correct value
    await wrapper.vm.$nextTick();
    expect(wrapper.emitted('change-GoalDescription')).toBeTruthy();
    expect(wrapper.emitted('change-GoalDescription')[0]).toEqual(['New Goal Description']);
  });
});
