import ModuleNode from '../../../../components/nodes/ModuleNode.vue';
import darkenColor from '../../../../composables/nodesHelper/darkenColor';
import { mount } from '@vue/test-utils';
import { Handle, Position } from '@vue-flow/core';

jest.mock('../../../../composables/nodesHelper/darkenColor', () => jest.fn());


describe('ModuleNode.vue', () => {

  beforeEach(() => {
    darkenColor.mockClear();
  });

  it('renders correctly with given props', async () => {
    const wrapper = mount(ModuleNode, {
      props: {
        data: {
          color: '#FF5733',
          name: 'Test Node',
          opacity: 0.5,
          height: '100px',
          width: '200px',
        },
        zoomstep: 1,
      },
    });

    // Check the initial rendering and styles
    expect(wrapper.find('.custom-node').exists()).toBe(true);
    expect(wrapper.find('.custom-node').attributes('style')).toContain('background-color: rgb(16, 71, 3)');
    expect(wrapper.find('.module-name').attributes('style')).toContain('border: 5px solid #1047033');
  });

  it('computes backgroundColor and darkerColor correctly on mount', async () => {
    darkenColor.mockReturnValue('#1047033');

    const wrapper = mount(ModuleNode, {
      props: {
        data: {
          color: '#FF5733',
          opacity: 0.5,
        },
        zoomstep: 1,
      },
    });

    // Wait for the onMounted hook to complete
    await wrapper.vm.$nextTick();

    expect(darkenColor).toHaveBeenCalledWith('#FF5733');
    expect(wrapper.vm.backgroundColor).toBe('rgba(255, 87, 51, 0.5)');
    expect(wrapper.vm.darkerColor).toBe('#1047033');
  });

  it('does not render Handle components when zoomstep is not 0.2', async () => {
    const wrapper = mount(ModuleNode, {
      props: {
        data: {
          color: '#FF5733',
          opacity: 0.5,
          name: 'Test Node',
        },
        zoomstep: 1,
      },
    });

    expect(wrapper.findComponent(Handle).exists()).toBe(false);
  });

});