/**
 * @jest-environment jsdom
 */
const path = require('path');
const resolvedPath = require.resolve('@vue/test-utils');
console.log(resolvedPath);
import { createApp } from 'vue';
import NotFound from '../../../components/NotFound.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import { createRouter, createMemoryHistory } from 'vue-router';

describe('NotFound.vue', () => {
  let store;
  let router;

  beforeEach(async () => {
    store = createStore({
      state: {
        strings: {
          route_not_found_site_name: 'Page Not Found',
          route_not_found: 'The page you are looking for does not exist.',
          btnreload: 'Reload',
        },
        view: true,
      },
    });

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/overview',
          name: 'learningpaths-edit-overview',
          component: { template: '<div>Learning Path Overview</div>' }, // Mock component
        },
      ],
    });
    router.push('/overview');
    await router.isReady();
  });

  it('renders the correct text based on Vuex store', () => {
    const wrapper = mount(NotFound, {
      global: {
        plugins: [store, router],
      },
    });

    expect(wrapper.find('h2').text()).toBe(store.state.strings.route_not_found_site_name);
    expect(wrapper.find('h3').text()).toBe(store.state.strings.route_not_found);
    const button = wrapper.find('.btn.btn-primary');
    expect(button.text()).toBe(store.state.strings.btnreload);
  });

  it('redirects to the overview page on mount if router is undefined', async () => {
    router.push = jest.fn(); // Mock router.push
    const wrapper = mount(NotFound, {
      global: {
        plugins: [store, router],
      },
    });

    // Simulate the condition where router is undefined (mock this behavior)
    wrapper.vm.$router = undefined;

    // Trigger the onMounted lifecycle hook
    await wrapper.vm.$nextTick();

    // Check if the router.push was called
    expect(router.push).toHaveBeenCalledWith({ name: 'learningpaths-edit-overview' });
  });
});