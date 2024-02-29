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
export function createAppStore() {
    return createStore({
        state() {
            return {
                view: 'defaultView',
                user: null,
                userlist: null,
                learningPathID: 0,
                contextID: 0,
                strings: {},
                learningpaths: null,
                learningpath: null,
                availablecourses: null,
                editingadding: false,
                editingrestriction: false,
                editingpretest: false,
                node: null,
                startnode: null,
                lpuserpathrelations: [],
                lpuserpathrelation: null,
                feedback: null,
            };
        },
        mutations: {
            // Mutations are synchronous.
            setlearningPathID(state, id) {
                state.learningPathID = id;
            },
            setStrings(state, strings) {
                state.strings = strings;
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
                state.learningpath.json.tree.nodes = state.learningpath.json.tree.nodes.map(element_node => {
                    if (element_node.id === data.node_id) {
                      return { ...element_node, fullname: data.fullname };
                    }
                    return element_node;
                });
            },
            setLpUserPathRelations(state, data){
                state.lpuserpathrelations = data;
            },
            setLpUserPathRelation(state, data){
                state.lpuserpathrelation = data;
            },
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
                    { userid: 0, learningpathid: context.state.learningPathID });
                if (learningpath.json != '') {
                    learningpath.json = JSON.parse(learningpath.json); 
                }
                context.commit('setLearningpath', learningpath);
            },
            async fetchUserPathRelations(context) {
                const lpUserPathRelations = await ajax('local_adele_get_user_path_relations',
                { userid: context.state.user, learningpathid: context.state.learningPathID });
                context.commit('setLpUserPathRelations', lpUserPathRelations);
            },
            async fetchUserPathRelation(context, route) {
                const lpUserPathRelation = await ajax('local_adele_get_user_path_relation',
                    { learningpathid: route.learningpathId, userpathid: route.userId});
                context.commit('setLpUserPathRelation', lpUserPathRelation);
            },
            async saveUserPathRelation(context, params) {
                await ajax('local_adele_save_user_path_relation',
                    { userid: context.state.user,
                      learningpathid: context.state.learningPathID,
                      params: JSON.stringify(params)});
                context.dispatch('fetchUserPathRelation', params.route);
                context.dispatch('fetchUserPathRelations');
            },
            async fetchLearningpaths(context) {
                const learningpaths = await ajax('local_adele_get_learningpaths',
                { userid: context.state.user, learningpathid: context.state.learningPathID });
                context.commit('setLearningpaths', learningpaths);
            },
            async fetchAvailablecourses(context) {
                const availablecourses = await ajax('local_adele_get_availablecourses',
                { userid: context.state.user, learningpathid: context.state.learningPathID });
                context.commit('setAvailablecourses', availablecourses);
            },
            async saveLearningpath(context, payload) {
                const result = await ajax('local_adele_save_learningpath',
                { userid: context.state.user, 
                  learningpathid: context.state.learningPathID, 
                  name: payload.name, 
                  description: payload.description, 
                  json: payload.json });                
                context.dispatch('fetchLearningpaths');
                context.commit('setLearningpath', result.learningpath);
                context.commit('setlearningPathID', result.learningpath.id);
                return result.learningpath.id;
            },
            async updateLearningpath(context, payload) {
              await ajax('local_adele_update_learningpath',
              {
                id: payload.id, 
                json: payload.json 
              });
          },
            async deleteLearningpath(context, payload) {
                const result = await ajax('local_adele_delete_learningpath', 
                {userid: context.state.user, learningpathid: payload.learningpathid});
                context.dispatch('fetchLearningpaths');
                return result.result;
            },
            async duplicateLearningpath(context, payload) {
                const result = await ajax('local_adele_duplicate_learningpath', 
                {userid: context.state.user, learningpathid: payload.learningpathid});
                context.dispatch('fetchLearningpaths');
                return result.result;
            },
            async fetchCompletions(context) {
                const result = await ajax('local_adele_get_completions',
                { userid: context.state.user, learningpathid: context.state.learningPathID });
                return result;
            },
            async fetchRestrictions(context) {
                const result = await ajax('local_adele_get_restrictions',
                { userid: context.state.user, learningpathid: context.state.learningPathID });
                return result;
            },
            async fetchCatquizTests(context) {
                const result = await ajax('local_adele_get_catquiz_tests',
                { userid: context.state.user, learningpathid: context.state.learningPathID });
                return result;
            },
            async fetchCatquizScales(context, payload) {
                const result = await ajax('local_adele_get_catquiz_scales', 
                { userid: context.state.user, learningpathid: context.state.learningPathID, testid: payload.testid});
                return result;
            },
            async fetchCatquizParentScales(context) {
                const result = await ajax('local_adele_get_catquiz_parent_scales',
                { userid: context.state.user, learningpathid: context.state.learningPathID });
                return result;
            },
            async fetchCatquizParentScale(context, payload) {
                const result = await ajax('local_adele_get_catquiz_parent_scale',
                { userid: context.state.user, learningpathid: context.state.learningPathID, sacleid: payload.scaleid });
                return result;
            },
            async fetchModQuizzes(context) {
                const result = await ajax('local_adele_get_mod_quizzes',
                { userid: context.state.user, learningpathid: context.state.learningPathID });
                return result;
            },
            async fetchImagePaths() {
              const result = await ajax('local_adele_get_image_paths', {});
              return result;
          },
        }
    });
}

/**
 * Single ajax call to Moodle.
 */
export async function ajax(method, args) {
    const request = {
        methodname: method,
        args: Object.assign( args ),
    };

    try {
        return await moodleAjax.call([request])[0];
    } catch (e) {
        Notification.exception(e);
        throw e;
    }
}