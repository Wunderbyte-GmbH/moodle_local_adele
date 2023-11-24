import { createApp } from 'vue';
import VueInputAutowidth from 'vue-input-autowidth';
import { store } from './store';
import Notifications from '@kyvg/vue3-notification'
import router from './router/router'

window.__VUE_OPTIONS_API__ = true; // Enables the Composition API
window.__VUE_PROD_DEVTOOLS__ = false; // Disable devtools in production

function init() {
    // We need to overwrite the variable for lazy loading.
    __webpack_public_path__ = M.cfg.wwwroot + '/local/adele/amd/build/';
    const app = createApp({});
    
    app.use(VueInputAutowidth);
    app.use(Notifications);
    store.dispatch('loadComponentStrings');

    app.use(store);
    app.use(router);
    app.mount('#local-adele-app');
}

export { init };