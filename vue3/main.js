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
import { createApp } from 'vue';
import VueInputAutowidth from 'vue-input-autowidth';
import { createAppStore } from './store';
import Notifications from '@kyvg/vue3-notification'
import router from './router/router'
import tooltipDirective from './directives/tooltip';


// Enables the Composition API
window.__VUE_OPTIONS_API__ = true;
// Disable devtools in production
window.__VUE_PROD_DEVTOOLS__ = false;

function init() {
    // We need to overwrite the variable for lazy loading.
    /* eslint-disable no-undef */
    __webpack_public_path__ = M.cfg.wwwroot + '/local/adele/amd/build/';
    /* eslint-enable no-undef */

    const localAdeleAppElements = document.getElementsByName('local-adele-app');
    localAdeleAppElements.forEach((localAdeleAppElement) => {
        if (!localAdeleAppElement.__vue_app__) {
            const app = createApp({});
            app.use(VueInputAutowidth);
            app.use(Notifications);
            const store = createAppStore();
            store.dispatch('loadComponentStrings');
            app.use(store);
            app.use(router);
            const viewAttributeValue = localAdeleAppElement.getAttribute('view');
            store.state.view = viewAttributeValue;
            const pathAttributeValue = localAdeleAppElement.getAttribute('learningpath');
            store.state.learningPathID = pathAttributeValue;
            const userAttributeValue = localAdeleAppElement.getAttribute('user');
            store.state.user = userAttributeValue;
            const userListAttributeValue = localAdeleAppElement.getAttribute('userlist');
            store.state.userlist = userListAttributeValue;
            const contextIdValue = localAdeleAppElement.getAttribute('contextid');
            store.state.contextid = contextIdValue;
            const quizSettingValue = localAdeleAppElement.getAttribute('quizsetting');
            store.state.quizsetting = quizSettingValue;
            store.state.wwwroot = localAdeleAppElement.getAttribute('wwwroot');
            const editablepathsValue = localAdeleAppElement.getAttribute('editablepaths');
            store.state.editablepaths = JSON.parse(editablepathsValue);
            store.state.version = canUseNewFaIconsnewVersion(localAdeleAppElement.getAttribute('version'));
            app.directive('tooltip', tooltipDirective);
            app.mount(localAdeleAppElement);
        }
    });
}

function canUseNewFaIconsnewVersion(usedVersion){
  const oldVersion = '2023042400'
  return usedVersion >= oldVersion ? true : false;
}

export { init };