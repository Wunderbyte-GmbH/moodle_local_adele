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
    const startDateDiv = wrapper.find('.text-left').text();
    expect(startDateDiv).toContain('Start Date:');

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
    const endDateDiv = wrapper.findAll('.text-left').at(1).text();
    expect(endDateDiv).toContain('End Date:');

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
});