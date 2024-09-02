import ExpandNodeInformation from '../../../../components/nodes_items/ExpandNodeInformation.vue';
import { mount } from '@vue/test-utils';
import { useStore } from 'vuex';

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

  it('renders correctly with given props', async () => {
    const courses = [
      {
        id: 1,
        description: 'This is a test course description.',
      },
    ];
    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });
    expect(wrapper.find('.icon-container').exists()).toBe(true);
    const informationDiv = wrapper.find('.information');
    expect(informationDiv.element.style.backgroundColor).toBe('rgb(240, 240, 240)');
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

  it('renders with a different background color if the store state changes', async () => {
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

    // Check if the new background color is applied
    const informationDiv = wrapper.find('.information');
    expect(informationDiv.element.style.backgroundColor).toBe('rgb(170, 170, 170)'); // rgb of #aaaaaa
  });

});