import { createStore } from 'vuex';
import moodleAjax from 'core/ajax';
import moodleStorage from 'core/localstorage';
import Notification from 'core/notification';
import $ from 'jquery';

const store = createStore({
    state() {
        return {
            learningGoalID: 0,
            contextID: 0,
            strings: {},
            learninggoals: null,
            learningpaths: null,
            learningpath: null,
            availablecourses: null,
            learninggoal: null,
            editingadding: false,
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