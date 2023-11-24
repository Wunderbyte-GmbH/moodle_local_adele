import { createRouter, createWebHashHistory } from 'vue-router';
import notFound from '../components/not-found';
import learninggoalsEdit from '../components/learninggoals-edit';

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

export default router