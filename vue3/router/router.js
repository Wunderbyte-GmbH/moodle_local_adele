// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Import needed libraries
import { createRouter, createWebHashHistory } from 'vue-router';
import notFound from '../components/NotFound.vue';
import learningpathsEdit from '../components/LearningpathsEdit.vue';
import userPath from '../components/user_view/UserPath.vue';
import { useStore } from 'vuex'

// All available routes
const routes = [
    {
        path: '/learningpaths',
        redirect: {
            name: 'learningpaths-edit-overview'
        }
    },
    {
        path: '/',
        redirect: {
            name: 'learningpaths-edit-overview'
        }
    },
    {
        path: '/section-:id(\\d+)',
        redirect: {
            name: 'learningpaths-edit-overview'
        }
    },
    {
        path: '/learningpaths/edit',
        component: learningpathsEdit,
        name: 'learningpaths-edit-overview',
        children: [
            {
                path: '/learningpaths/edit/:learningpathId(\\d+)',
                component: learningpathsEdit,
                name: 'learningpath-edit',
            }, {
                path: '/learningpaths/edit/new',
                component: learningpathsEdit,
                name: 'learningpath-new'
            }, {
                path: '/learningpaths/view/:learningpathId(\\d+)',
                component: learningpathsEdit,
                name: 'learningpath-view'
            },
        ],
    }, {
            path: '/learningpaths/edit/:learningpathId(\\d+)/:userId(\\d+)',
            component: userPath,
            name: 'userDetails'
    },
    {
        path: '/learningpaths/:catchAll(.*)',
        component: notFound
    },
];
const currenturl = window.location.pathname;
const base = currenturl;

// Creating router
const router = createRouter({
    history: createWebHashHistory(),
    routes,
    base
});

router.beforeEach((to, from, next) => {
    const store = useStore()
    // Find a translation for the title.
    if (to.meta && to.meta.title && store.state.strings[to.meta.title]) {
        document.title = store.state.strings[to.meta.title];
    }
    next();
});

export default router