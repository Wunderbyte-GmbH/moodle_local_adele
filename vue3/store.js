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
 * Validate if the string does excist.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Import needed libraries
import { createStore } from 'vuex';
import moodleAjax from 'core/ajax';
import moodleStorage from 'core/localstorage';
import Notification from 'core/notification';
import $ from 'jquery';

// Defining store for application
const store = createStore({
    state() {
        return {
            learningGoalID: 0,
            contextID: 0,
            strings: {},
            learninggoals: null,
            learningpaths: null,
            learningpath: null,
            // availablecourses: null,
            availablecourses: [ 
                { "id": 17, "fullname": "BANALY", "shortname": "BANALY", "category": "5", "tags": "first" }, 
                { "id": 927, "fullname": "adaptive", "shortname": "adaptive", "category": "6", "tags": "zwei" } 
            ],
            // learninggoal: null,
            learninggoal: [ 
                { "id": 0, "name": "", "description": "", "json": "" } 
            ],
            editingadding: false,
            node: null,
            startnode: null,
            editingpretest: false,
            lpuserpathrelations: []
        };
    },
    mutations: {
        // Mutations are synchronous.
        setLearningGoalID(state, id) {
            state.learningGoalID = id;
        },
        setContextID(state, id) {
            state.contextID = id;
        },
        setStrings(state, strings) {
            state.strings = strings;
        },
        setLearninggoals(state, ajaxdata) {
            state.learninggoals = ajaxdata;
        },
        setLearningpaths(state, ajaxdata) {
            state.learningpaths = ajaxdata;
        },
        setLearningpath(state, ajaxdata) {
            state.learningpath = ajaxdata;
        },
        setAvailablecourses(state, ajaxdata) {
            state.availablecourses = ajaxdata;
        },
        setLearninggoal(state, ajaxdata) {
            state.learninggoal = ajaxdata;
        },
        setNode(state, data) {
            state.node = data;
        },
        setstartNode(state, data) {
            state.startnode = data.startnode;
        },
        updatedNode(state, data) {
            //set node name
            state.node.fullname = data.fullname;
            //save learning path
            state.learninggoal[0].json.tree.nodes = state.learninggoal[0].json.tree.nodes.map(element_node => {
                if (element_node.id === data.node_id) {
                  return { ...element_node, fullname: data.fullname };
                }
                return element_node;
            });
        },
        setLpUserPathRelations(state, data){
            console.log(data)
            state.lpuserpathrelations = data;
        }
    },
    actions: {
        // Actions are asynchronous.
        async loadLang(context) {
            const lang = $('html').attr('lang').replace(/-/g, '_');
            context.commit('setLang', lang);
        },
        async loadComponentStrings(context) {
            const lang = $('html').attr('lang').replace(/-/g, '_');
            const cacheKey = 'local_adele/strings/' + lang;
            const cachedStrings = moodleStorage.get(cacheKey);
            if (cachedStrings) {
                context.commit('setStrings', JSON.parse(cachedStrings));
            } else {
                const request = {
                    methodname: 'core_get_component_strings',
                    args: {
                        'component': 'local_adele',
                        lang,
                    },
                };
                const loadedStrings = await moodleAjax.call([request])[0];
                let strings = {};
                loadedStrings.forEach((s) => {
                    strings[s.stringid] = s.string;
                });
                context.commit('setStrings', strings);
                moodleStorage.set(cacheKey, JSON.stringify(strings));
            }
        },
        async fetchLearningpath(context) {
            const learningpath = await ajax('local_adele_get_learningpath',
                { userid: 0, learninggoalid: context.state.learningGoalID });

            if (learningpath[0].json != '') {
                learningpath[0].json = JSON.parse(learningpath[0].json); 
            }
            context.commit('setLearninggoal', learningpath);
        },

        async fetchUserPathRelation(context, learningpathid) {
            const lpUserPathRelations = await ajax('local_adele_get_user_path_relations',
                { learningpathid: learningpathid});
            context.commit('setLpUserPathRelations', lpUserPathRelations);
        },
        async fetchLearningpaths(context) {
            const learningpaths = await ajax('local_adele_get_learningpaths');
            context.commit('setLearningpaths', learningpaths);
        },
        async fetchAvailablecourses(context) {
            const availablecourses = await ajax('local_adele_get_availablecourses');
            context.commit('setAvailablecourses', availablecourses);
        },
        async saveLearningpath(context, payload) {
            const result = await ajax('local_adele_save_learningpath',
            { name: payload.name, description: payload.description, json: payload.json });
            context.dispatch('fetchLearningpaths');
            return result.result;
        },
        async deleteLearningpath(context, payload) {
            const result = await ajax('local_adele_delete_learningpath', payload);
            context.dispatch('fetchLearningpaths');
            return result.result;
        },
        async duplicateLearningpath(context, payload) {
            const result = await ajax('local_adele_duplicate_learningpath', payload);
            context.dispatch('fetchLearningpaths');
            return result.result;
        },
        async fetchCompletions() {
            const result = await ajax('local_adele_get_completions');
            return result;
        },
    }
});

export { store };

/**
 * Single ajax call to Moodle.
 */
export async function ajax(method, args) {
    const request = {
        methodname: method,
        args: Object.assign({
            userid: 0,
            learninggoalid: store.state.learningGoalID,
        }, args),
    };

    try {
        return await moodleAjax.call([request])[0];
    } catch (e) {
        Notification.exception(e);
        throw e;
    }
}