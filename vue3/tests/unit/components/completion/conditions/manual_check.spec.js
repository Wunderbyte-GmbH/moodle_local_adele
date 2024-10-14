import manual_check from '../../../../../components/completion/conditions/manual_check.vue';
import { mount } from '@vue/test-utils';


describe('manual_check.vue', () => {

  it('renders checkbox with correct label from Vuex store', () => {
    const wrapper = mount(manual_check, {
      props: {
        modelValue: {},
        completion: { description: 'Manaul description' }
      }
    })
    const descriptionText = wrapper.find('.form-check').text()
    expect(descriptionText).toBe('Manaul description')
  });
});