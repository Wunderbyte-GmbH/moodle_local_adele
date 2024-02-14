import { shallowMount } from '@vue/test-utils'
import DateInfo from '../../components/nodes_items/DateInfo.vue'


describe('DateInfo.vue', () => {
  it('renders start and end dates when provided as props', () => {
    const date = {
      start: '2022-01-22T10:30:00',
      end: '2022-01-28T18:45:00',
    };

    const wrapper = shallowMount(DateInfo, {
      props: { date },
    });

    // Check if start date is rendered
    expect(wrapper.find('.text-left').text()).toContain('Start Date:');

    const startDateValue = wrapper.find('.text-right').text();
    const formattedStartDate = new Date(date.start).toLocaleString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      hour12: false,
    });
    expect(startDateValue).toContain(formattedStartDate);

    // Check if end date is rendered
    expect(wrapper.findAll('.text-left').at(1).text()).toContain('End Date:');

    const endDateValue = wrapper.findAll('.text-right').at(1).text();
    const formattedEndDate = new Date(date.end).toLocaleString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      hour12: false,
    });
    expect(endDateValue).toContain(formattedEndDate);
  });

  it('renders only start date when end date is not provided', () => {
    // Test data with only start date
    const date = {
      start: '2022-01-22T10:30:00',
      end: null,
    };

    // Mount the component with test data
    const wrapper = shallowMount(DateInfo, {
      props: { date },
    });

    // Check if start date is rendered
    expect(wrapper.find('.text-left').text()).toContain('Start Date:');
    const formattedStartDate = new Date(date.start).toLocaleString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      hour12: false,
    });
    expect(wrapper.find('.text-right').text()).toContain(formattedStartDate);

    // Check if end date is not rendered
    expect(wrapper.find('.text-left').text()).not.toContain('End Date:');
  });

  it('renders only end date when start date is not provided', () => {
    // Test data with only end date
    const date = {
      start: null,
      end: '2022-01-28T18:45:00',
    };

    // Mount the component with test data
    const wrapper = shallowMount(DateInfo, {
      props: { date },
    });

    // Check if end date is rendered
    expect(wrapper.find('.text-left').text()).toContain('End Date:');
    const formattedStartDate = new Date(date.end).toLocaleString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      hour12: false,
    });
    expect(wrapper.find('.text-right').text()).toContain(formattedStartDate);

    // Check if start date is not rendered
    expect(wrapper.find('.text-left').text()).not.toContain('Start Date:');
  });
});