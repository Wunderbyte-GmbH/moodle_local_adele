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

// Criteria that need a manual selection: label => the data.value field that must be set.
// An empty selection leaks its placeholder / is unsatisfiable, so it must not be saved
// (#450/#456 specific_course; also modquiz/catquiz).
const ADELE_REQUIRED_VALUE = {
    specific_course: 'courseid',
    modquiz: 'quizid',
    catquiz: 'testid',
};

// Returns true if any node carries a criterion that needs a selection but has none.
function hasIncompleteCondition(json) {
    const nodes = json && json.tree && json.tree.nodes ? json.tree.nodes : [];
    return nodes.some(node =>
        ['restriction', 'completion'].some(type => {
            const conditions = node && node[type] && node[type].nodes ? node[type].nodes : [];
            return conditions.some(c => {
                const field = c && c.data ? ADELE_REQUIRED_VALUE[c.data.label] : undefined;
                if (!field) {
                    return false;
                }
                // "not selected" = null / undefined / empty string. A numeric 0 is a
                // legitimate selection (catquiz parent-scale mode).
                const v = c.data.value ? c.data.value[field] : undefined;
                return v === null || v === undefined || v === '';
            });
        })
    );
}

/**
 * Timed access criteria must carry their required inputs before they can be saved,
 * otherwise they are unsatisfiable and render "missing date"-style hints (#494):
 * - 'timed' (start/end time): at least one of start or end must be set, and a
 *   fully set window must end after it starts ('timed_order');
 * - 'timed_duration' (editing period): a positive amount (selectedDuration), a
 *   time unit (durationValue, 0 = days) and a start mode (selectedOption).
 *
 * @param {object} json The learning-path tree json.
 * @returns {(string|null)} The label of the first invalid timed criterion, or null.
 */
export function invalidTimedConditionLabel(json) {
    const nodes = json && json.tree && json.tree.nodes ? json.tree.nodes : [];
    // "not provided" = null / undefined / empty string; a numeric 0 is a real value
    // (e.g. the "days" time unit), so it must not count as empty.
    const isEmpty = v => v === null || v === undefined || v === '';
    for (const node of nodes) {
        const conditions = node && node.restriction && node.restriction.nodes ? node.restriction.nodes : [];
        for (const c of conditions) {
            const data = c && c.data ? c.data : {};
            const value = data.value || {};
            if (data.label === 'timed') {
                if (isEmpty(value.start) && isEmpty(value.end)) {
                    return 'timed';
                }
                // Both set: the window must be forward in time (#494 retest).
                if (!isEmpty(value.start) && !isEmpty(value.end)
                    && new Date(value.start) >= new Date(value.end)) {
                    return 'timed_order';
                }
            }
            if (data.label === 'timed_duration') {
                // Field semantics (#566): selectedDuration = the AMOUNT (must be
                // a positive number); durationValue = the UNIT select where
                // 0 = days is a legitimate choice; selectedOption = the start
                // mode where 0 = learning path is legitimate (and PHP silently
                // never schedules the boundary task without it).
                if (isEmpty(value.selectedDuration) || Number(value.selectedDuration) <= 0
                    || isEmpty(value.durationValue)
                    || isEmpty(value.selectedOption)) {
                    return 'timed_duration';
                }
            }
        }
    }
    return null;
}

/**
 * A "starting" node has no predecessor, so a parent_courses ("according to predecessor
 * nodes") restriction on it can never be satisfied and would lock it forever (#476).
 *
 * @param {object} json The learning-path tree json.
 * @returns {boolean} True if any first node carries a parent_courses restriction.
 */
export function hasParentRestrictionOnFirstNode(json) {
    const nodes = json && json.tree && json.tree.nodes ? json.tree.nodes : [];
    return nodes.some(node => {
        const isfirst = node && Array.isArray(node.parentCourse) && node.parentCourse.includes('starting_node');
        if (!isfirst) {
            return false;
        }
        const conditions = node.restriction && node.restriction.nodes ? node.restriction.nodes : [];
        return conditions.some(c => c && c.data && c.data.label === 'parent_courses');
    });
}

/**
 * Read the learning-path-wide feedback/info toggles from a path json, defaulting to shown when
 * the settings are absent (existing paths) or the value is not explicitly false (#474).
 *
 * @param {Object} json The (parsed) learning-path json.
 * @returns {{show_feedback: boolean, show_info: boolean}}
 */
function readFeedbackSettings(json) {
    const settings = (json && json.settings) ? json.settings : {};
    return {
        show_feedback: settings.show_feedback !== false,
        show_info: settings.show_info !== false,
    };
}

/**
 * Compare the node states of two user path relations (#575 B4).
 *
 * Returns the nodes that became accessible or completed since the previous
 * fetch. Only the gains are reported: a recompute that changes nothing must
 * stay silent, and a node that went backwards is not news a learner needs
 * read out mid-task.
 *
 * @param {object} previous The relation held before the fetch, or null.
 * @param {object} current The relation just fetched.
 * @returns {{accessible: string[], completed: string[]}} Course names by gain.
 */
export function newlyReachedNodes(previous, current) {
    const statuses = (relation) => {
        const map = {};
        const nodes = relation && relation.json && relation.json.tree
            ? relation.json.tree.nodes || [] : [];
        nodes.forEach((node) => {
            const data = node.data || {};
            map[node.id] = {
                status: data.completion && data.completion.feedback
                    ? data.completion.feedback.status : null,
                name: data.fullname || '',
            };
        });
        return map;
    };
    const before = statuses(previous);
    const after = statuses(current);
    const gained = { accessible: [], completed: [] };
    Object.keys(after).forEach((id) => {
        // A node seen for the first time is not a change: on the first load
        // every node would be "new", and the learner would be read the whole
        // graph before they had done anything.
        if (!(id in before)) {
            return;
        }
        const was = before[id].status;
        const is = after[id].status;
        if (was === is) {
            return;
        }
        if (is === 'accessible' || is === 'completed') {
            gained[is].push(after[id].name);
        }
    });
    return gained;
}

// Defining store for application
export function createAppStore() {
    return createStore({
        state() {
            return {
                view: 'defaultView',
                // False in a read-only teacher's view (mod_adele #39).
                canedit: true,
                user: null,
                userlist: null,
                learningPathID: 0,
                contextid: 0,
                strings: {},
                quizsetting: {},
                learningpaths: null,
                viewlearningpaths: null,
                learningpath: null,
                availablecourses: null,
                editingadding: false,
                viewing: false,
                editingrestriction: false,
                editingpretest: false,
                node: null,
                startnode: null,
                lpuserpathrelations: [],
                lpuserpathrelation: null,
                // The learning-path-user list row a teacher last opened, so the list can
                // scroll back to it when returning from a participant's edit view (#481).
                focususer: null,
                // Whether the user-list panel is collapsed, so the graph canvas can grow
                // into the freed space (#480).
                userlistcollapsed: false,
                // Learning-path-wide toggles: whether the feedback bubble and the info-symbol are
                // shown to students. Default true (existing paths carry no settings) (#474).
                feedbacksettings: { show_feedback: true, show_info: true },
                feedback: null,
                modules: null,
                version: 0,
                lpimages: 0,
                lastseen: null,
                nodecourse: 0,
                undoNodes: [],
                undoEdges: [],
                wwwroot: '',
                // Text of the accessibility live region (#575 B4). Written by
                // announce(), read by A11yLiveRegion.vue, never rendered visibly.
                announcement: '',
            };
        },
        getters: {
          learningPaths(state) {
            return state.learningpaths;
          }
        },
        mutations: {
            setlearningPathID(state, id) {
                state.learningPathID = id;
            },
            setFocusUser(state, id) {
                state.focususer = id;
            },
            setUserlistCollapsed(state, collapsed) {
                state.userlistcollapsed = collapsed;
            },
            setStrings(state, strings) {
                state.strings = strings;
            },
            setLearningpaths(state, ajaxdata) {
                state.learningpaths = ajaxdata.edit;
                state.viewlearningpaths = ajaxdata.view;
            },
            setLearningpath(state, ajaxdata) {
              if (typeof ajaxdata.json === 'string' && ajaxdata.json != '') {
                  ajaxdata.json = JSON.parse(ajaxdata.json);
              }
              state.learningpath = ajaxdata;
              state.feedbacksettings = readFeedbackSettings(ajaxdata.json);
            },
            setFeedbackSettings(state, json) {
              state.feedbacksettings = readFeedbackSettings(json);
            },
            setLpFeedbackSetting(state, payload) {
              if (!state.learningpath.json.settings) {
                state.learningpath.json.settings = { show_feedback: true, show_info: true };
              }
              state.learningpath.json.settings[payload.key] = payload.value;
              state.feedbacksettings = readFeedbackSettings(state.learningpath.json);
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
            // Node display fields live under node.data - that is what the renderers
            // read and what ControlsPath persists. Writing them top-level (and dropping
            // description/estimate_duration entirely) silently lost every node
            // description on save (GitHub #484).
            updatedNode(state, data) {
                state.node.fullname = data.fullname;
                state.node.description = data.description;
                state.node.estimate_duration = data.estimate_duration;
                state.node.selected_course_image = data.selected_course_image;
                state.node.selected_image = data.selected_image;
                state.learningpath.json.tree.nodes = state.learningpath.json.tree.nodes.map(element_node => {
                    if (element_node.id === data.node_id) {
                      return { ...element_node,
                        data: { ...element_node.data,
                          fullname: data.fullname,
                          description: data.description,
                          estimate_duration: data.estimate_duration,
                          selected_course_image: data.selected_course_image,
                          selected_image: data.selected_image,
                        },
                      };
                    }
                    return element_node;
                });
            },
            // Per-course texts of a stack belong into course_node_id_description of the
            // TREE node; the stack node's own name/description stay untouched (#484).
            updatedCourseNode(state, data) {
              if (!state.node.course_node_id_description) {
                state.node.course_node_id_description = {}
              }
              state.node.course_node_id_description[data.courseid] = {
                fullname: data.fullname,
                description: data.description,
              };
              state.learningpath.json.tree.nodes = state.learningpath.json.tree.nodes.map(element_node => {
                  if (element_node.id === state.node.node_id) {
                    return { ...element_node,
                      data: { ...element_node.data,
                        course_node_id_description: {
                          ...(element_node.data.course_node_id_description || {}),
                          [data.courseid]: {
                            fullname: data.fullname,
                            description: data.description,
                          },
                        },
                      },
                    };
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
            /**
             * Put one short sentence into the live region.
             *
             * A screen reader announces a live region when its text CHANGES.
             * Writing the same sentence twice would therefore stay silent -
             * and blanking it first does not help, because Vue batches both
             * writes into one render and the empty string never reaches the
             * DOM. So a repeated message alternates a trailing no-break
             * space: a different string, an identical announcement.
             */
            announce(state, message) {
                if (!message) {
                    state.announcement = '';
                    return;
                }
                const repeat = state.announcement.replace(/\u00a0$/, '') === message;
                state.announcement = repeat ? message + '\u00a0' : message;
            },
            setLpImages(state, data){
              state.lpimages = data;
            },
            setLastSeen(state, data) {
              state.lastseen = data;
            },
            setUndoNodes(state, tree) {
              state.undoNodes.push(tree.undoNodesSet);
              if (state.undoNodes.length > 3) {
                state.undoNodes.shift();
              }
              state.undoEdges.push(tree.undoEdgesSet);
              if (state.undoEdges.length > 3) {
                state.undoEdges.shift();
              }
            },
            unsetUndoNodes(state) {
              const lastElement = state.undoNodes[state.undoNodes.length - 1];
              state.undoNodes = state.undoNodes.slice(0, -1);
              if (state.learningpath.json.tree.nodes) {
                state.learningpath.json.tree.nodes = lastElement
              }
            },
            unsetUndoEdges(state) {
              const lastElement = state.undoEdges[state.undoEdges.length - 1];
              state.undoEdges = state.undoEdges.slice(0, -1);
              if (state.learningpath.json.tree.edges) {
                state.learningpath.json.tree.edges = lastElement
              }
            },
        },
        actions: {
            // Actions are asynchronous.
            setUndoNodes({ commit }, nodes) {
              return new Promise((resolve) => {
                commit('setUndoNodes', nodes);
                resolve();
              });
            },
            async loadLang(context) {
                const lang = document.documentElement.lang.replace(/-/g, '_');
                context.commit('setLang', lang);
            },
            async loadComponentStrings(context, stringsrev) {
                const lang = document.documentElement.lang.replace(/-/g, '_');
                // Versioned per plugin release: without the rev, browsers kept serving
                // stale cached strings after upgrades (labels rendered "undefined").
                const cacheKey = 'local_adele/strings/' + lang + '/' + (stringsrev || '0');
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
                let learningpath = null
                if (context.state.learningPathID == 0) {
                  learningpath = {
                    id: 0,
                    name: "",
                    description: "",
                    json: "",
                  }
                }
                else {
                  learningpath = await ajax('local_adele_get_learningpath',
                      {
                        userid: 0,
                        learningpathid: context.state.learningPathID,
                        contextid: context.state.contextid,
                      });
                  if (learningpath.json != '') {
                      learningpath.json = await JSON.parse(learningpath.json);
                  }
                }
                context.commit('setLearningpath', learningpath);
                return learningpath
            },
            async fetchUserPathRelations(context) {
                const lpUserPathRelations = await ajax('local_adele_get_user_path_relations',
                {
                  userid: context.state.user,
                  learningpathid: context.state.learningPathID,
                  contextid: context.state.contextid,
                });
                context.commit('setLpUserPathRelations', lpUserPathRelations);
            },
            async fetchUserPathRelation(context, route) {
                const lpUserPathRelation = await ajax('local_adele_get_user_path_relation',
                    {
                      learningpathid: route.learningpathId,
                      userpathid: route.userId,
                      contextid: context.state.contextid,
                    });
                const previousrelation = context.state.lpuserpathrelation;
                context.commit('setLpUserPathRelation', lpUserPathRelation);
                context.commit('setLastSeen', lpUserPathRelation.last_seen_by_owner);
                if (lpUserPathRelation.json != '') {
                  lpUserPathRelation.json = await JSON.parse(lpUserPathRelation.json);
                  context.commit('setFeedbackSettings', lpUserPathRelation.json);
                  // What changed for the learner, in one sentence (#575 B4).
                  const gained = newlyReachedNodes(previousrelation, lpUserPathRelation);
                  const sentences = [];
                  if (gained.accessible.length) {
                    sentences.push(context.state.strings.a11y_now_accessible + ' ' +
                      gained.accessible.join(', '));
                  }
                  if (gained.completed.length) {
                    sentences.push(context.state.strings.a11y_now_completed + ' ' +
                      gained.completed.join(', '));
                  }
                  if (sentences.length) {
                    context.commit('announce', sentences.join('. '));
                  }
                }
                return lpUserPathRelation
            },
            async saveUserPathRelation(context, params) {
                await ajax('local_adele_save_user_path_relation',
                    { userid: context.state.user,
                      learningpathid: context.state.learningPathID,
                      params: JSON.stringify(params),
                      contextid: context.state.contextid,
                    });
                context.dispatch('fetchUserPathRelation', params.route);
                context.dispatch('fetchUserPathRelations');
            },
            async updateUserPathRelation(context, params) {
                const response = await ajax('local_adele_update_user_path_relation',
                    {
                      lpuserpathid: params.lpuserpathid,
                      contextid: context.state.contextid,
                    });
                context.commit('setLastSeen', response.last_seen);
            },
            async fetchLearningpaths(context) {
                const learningpaths = await ajax('local_adele_get_learningpaths',
                {
                  userid: context.state.user,
                  learningpathid: context.state.learningPathID,
                  contextid: context.state.contextid,
                });
                context.commit('setLearningpaths', learningpaths);
            },
            async fetchAvailablecourses(context) {
                const availablecourses = await ajax('local_adele_get_availablecourses',
                {
                  userid: context.state.user,
                  learningpathid: context.state.learningPathID,
                  contextid: context.state.contextid,
                });
                context.commit('setAvailablecourses', availablecourses);
            },
            async saveLearningpath(context, payload) {
                // Frontend guard (#450): refuse an incomplete "specific node completed"
                // criterion with a friendly message and without a round-trip. Throwing
                // aborts the caller's post-save navigate/success steps.
                if (hasIncompleteCondition(payload.json)) {
                    Notification.alert(
                        context.state.strings.restriction_incomplete_title,
                        context.state.strings.criterion_incomplete_modal
                    );
                    throw new Error('local_adele/incomplete-criterion');
                }
                // Frontend guard (#476): a first node has no predecessor, so a
                // parent_courses restriction on it can never unlock. Refuse the save.
                if (hasParentRestrictionOnFirstNode(payload.json)) {
                    Notification.alert(
                        context.state.strings.restriction_incomplete_title,
                        context.state.strings.first_node_parent_restriction_modal
                    );
                    throw new Error('local_adele/first-node-parent-restriction');
                }
                // Frontend guard (#494): a timed access criterion saved without its
                // required inputs is unsatisfiable ("missing date" hints). Refuse the save.
                const invalidtimed = invalidTimedConditionLabel(payload.json);
                if (invalidtimed) {
                    const timedmessages = {
                        timed: context.state.strings.timed_incomplete_modal,
                        timed_order: context.state.strings.timed_order_modal,
                        timed_duration: context.state.strings.timed_duration_incomplete_modal,
                    };
                    Notification.alert(
                        context.state.strings.restriction_incomplete_title,
                        timedmessages[invalidtimed]
                    );
                    throw new Error('local_adele/incomplete-timed-criterion');
                }
                let result;
                try {
                    result = await ajax('local_adele_save_learningpath',
                    { userid: context.state.user,
                      learningpathid: context.state.learningPathID,
                      name: payload.name,
                      description: payload.description,
                      image: payload.image,
                      json: JSON.stringify(payload.json),
                      contextid: context.state.contextid,
                    }, true);
                } catch (error) {
                    // Silent backend backstop: if the server guard ever rejects (e.g. a
                    // bypassed frontend, or a duplicate name #492), show its message in
                    // the same clean modal - never the raw exception dialog with a stack.
                    Notification.alert(context.state.strings.error_save_title, error.message);
                    // The modal takes the focus, but its text is not a status
                    // message; announce the reason as well (#575 B4).
                    context.commit('announce', error.message);
                    throw error;
                }
                context.commit('announce', context.state.strings.title_save);
                context.dispatch('fetchLearningpaths');
                return result.learningpath.id;
            },
            async deleteLearningpath(context, payload) {
                const result = await ajax('local_adele_delete_learningpath',
                {
                  userid: context.state.user,
                  learningpathid: payload.learningpathid,
                  contextid: context.state.contextid,
                });
                context.dispatch('fetchLearningpaths');
                return result.result;
            },
            async duplicateLearningpath(context, payload) {
                const result = await ajax('local_adele_duplicate_learningpath',
                {
                  userid: context.state.user,
                  learningpathid: payload.learningpathid,
                  contextid: context.state.contextid,
                });
                context.dispatch('fetchLearningpaths');
                return result.result;
            },
            async fetchCompletions(context) {
                const result = await ajax('local_adele_get_completions',
                {
                  contextid: context.state.contextid,
                });
                return result;
            },
            async fetchRestrictions(context) {
                const result = await ajax('local_adele_get_restrictions',
                {
                  contextid: context.state.contextid,
                });
                return result;
            },
            async fetchCatquizTests(context) {
                const result = await ajax('local_adele_get_catquiz_tests',
                {
                  contextid: context.state.contextid,
                  availablecourses: context.state.availablecourses,
                });
                return result;
            },
            async fetchCatquizScales(context, payload) {
                const result = await ajax('local_adele_get_catquiz_scales',
                {
                  userid: context.state.user,
                  learningpathid: context.state.learningPathID,
                  testid: payload.testid,
                  contextid: context.state.contextid,
                });
                return result;
            },
            async fetchCatquizParentScales(context) {
                const result = await ajax('local_adele_get_catquiz_parent_scales',
                {
                  contextid: context.state.contextid,
                });
                return result;
            },
            async fetchCatquizParentScale(context, payload) {
                const result = await ajax('local_adele_get_catquiz_parent_scale',
                {
                  userid: context.state.user,
                  learningpathid: context.state.learningPathID,
                  sacleid: payload.scaleid,
                  contextid: context.state.contextid,
                });
                return result;
            },
            async fetchModQuizzes(context) {
                const result = await ajax('local_adele_get_mod_quizzes',
                {
                  contextid: context.state.contextid,
                  availablecourses: context.state.availablecourses,
                });
                return result;
            },
            async fetchImagePaths(context) {
              const result = await ajax('local_adele_get_image_paths', {
                contextid: context.state.contextid
              });
              context.commit('setLpImages', result);
              return result;
            },
            async uploadNewLpImage(context, image) {
              try {
                const result = await ajax('local_adele_upload_lp_image', {
                  contextid: context.state.contextid,
                  learningpathid: context.state.learningPathID,
                  image: image,
                });
                return result;
              } catch (error) {
                console.error('Error in uploadNewLpImage action:', error);
                throw error;
              }
            },
            async getFoundUsers(context, query) {
              const result = await ajax('local_adele_search_users', {
                query: query,
              });
              return result;
            },
            async getLpEditUsers(context, lpid) {
              const result = await ajax('local_adele_get_lp_edit_users', {
                contextid: context.state.contextid,
                lpid: lpid,
              });
              return result;
            },
            // Both return the request (#599): the caller changes its list
            // only once the server agreed. A refusal is reported by ajax()
            // and reaches the caller as a rejection.
            createLpEditUsers(context, params) {
              return ajax('local_adele_create_lp_edit_users', {
                contextid: context.state.contextid,
                lpid: params.lpid,
                userid: params.userid,
              });
            },
            removeLpEditUsers(context, params) {
              return ajax('local_adele_remove_lp_edit_users', {
                contextid: context.state.contextid,
                lpid: params.lpid,
                userid: params.userid,
              });
            },
            // Transfer the ownership of a learning path (Adele Manager only, #488).
            async setLpOwner(context, params) {
              return await ajax('local_adele_set_lp_owner', {
                contextid: context.state.contextid,
                lpid: params.lpid,
                userid: params.userid,
              });
            },
            updateLearningPathVisibility(context, params) {
              ajax('local_adele_update_lp_visiblity', {
                contextid: context.state.contextid,
                lpid: params.lpid,
                visibility: params.visibility,
              });
            },
            setNodeAnimations(context, params){
              ajax('local_adele_update_lp_animations', {
                contextid: context.state.contextid,
                learningpathid: context.state.learningPathID,
                userid: context.state.user,
                nodeid: params.nodeid,
                animations: JSON.stringify(params.animations),
              });
            }
        }
    });
}

/**
 * Single ajax call to Moodle.
 */
export async function ajax(method, args, silent = false) {
    const request = {
        methodname: method,
        args: Object.assign( args ),
    };

    try {
        return await moodleAjax.call([request])[0];
    } catch (e) {
        // Callers that render their own clean message (e.g. saveLearningpath's
        // duplicate-name guard #492) pass silent=true to suppress the raw Moodle
        // exception/stack-trace dialog and avoid a double popup.
        if (!silent) {
            Notification.exception(e);
        }
        throw e;
    }
}