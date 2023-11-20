import { createApp } from 'vue';
import { createRouter, createWebHashHistory } from 'vue-router';
import notFound from './components/not-found';
import learninggoalsEdit from './components/learninggoals-edit';
import VueInputAutowidth from 'vue-input-autowidth';
import { store } from './store';

window.__VUE_OPTIONS_API__ = true; // Enables the Composition API
window.__VUE_PROD_DEVTOOLS__ = false; // Disable devtools in production

function init() {
    // We need to overwrite the variable for lazy loading.
    __webpack_public_path__ = M.cfg.wwwroot + '/local/adele/amd/build/';
    const app = createApp({});
    
    app.use(VueInputAutowidth);

    store.dispatch('loadComponentStrings');

    app.use(store);
    
    // You have to use child routes if you use the same component. Otherwise, the component's beforeRouteUpdate
    // will not be called.
    const routes = [
        {
            path: '/',
            redirect: {
                name: 'learninggoals-edit-overview'
            }
        }, {
            path: '/learninggoals/edit',
            component: learninggoalsEdit,
            name: 'learninggoals-edit-overview',
            children: [
                {
                    path: '/learninggoals/edit/:learninggoalId(\\d+)',
                    component: learninggoalsEdit,
                    name: 'learninggoal-edit'
                }, {
                    path: '/learninggoals/edit/new',
                    component: learninggoalsEdit,
                    name: 'learninggoal-new'
                 },
            ],
        }, {
            path: '/:catchAll(.*)',
            component: notFound
        },
    ];

    const currenturl = window.location.pathname;
    const base = currenturl;

    const router = createRouter({
        history: createWebHashHistory(),
        routes,
        base
    });

    router.beforeEach((to, from, next) => {
        // Find a translation for the title.
        if (to.meta && to.meta.title && store.state.strings[to.meta.title]) {
            document.title = store.state.strings[to.meta.title];
        }
        next();
    });

    app.use(router);
    app.mount('#local-adele-app');
}

export { init };