import Vue from 'vue';
import Vuex from 'vuex';
import moodleAjax from 'core/ajax';
import moodleStorage from 'core/localstorage';
import Notification from 'core/notification';
import $ from 'jquery';

Vue.use(Vuex);

export const store = new Vuex.Store({
    state: {
        learningGoalID: 0,
        contextID: 0,
        strings: {},
        handlers: null,
        learninggoals: null,
        learningpaths: null,
        learningpath: null,
        availablecourses: null,
        learninggoal: null,
    },
    //strict: process.env.NODE_ENV !== 'production',
    mutations: {
        // Mutations are synchroneous.
        setLearningGoalID(state, id) {
            state.learningGoalID = id;
        },
        setContextID(state, id) {
            state.contextID = id;
        },
        setStrings(state, strings) {
            state.strings = strings;
        },
        setHandlers(state, ajaxdata) {
            state.handlers = ajaxdata;
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
        // Actions are asynchroneous.
        /**
         * Determines the current language.
         *
         * @param context
         *
         * @returns {Promise<void>}
         */
        async loadLang(context) {
            const lang = $('html').attr('lang').replace(/-/g, '_');
            context.commit('setLang', lang);
        },
        /**
         * Fetches the i18n data for the current language.
         *
         * @param context
         * @returns {Promise<void>}
         */
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
        /**
         * Fetches a learning goal.
         *
         * @param context
         *
         * @returns {Promise<void>}
         */
        async fetchLearninggoal(context) {
            const learninggoal = await ajax('local_adele_get_learninggoal',
                { userid: 0, learninggoalid: store.state.learningGoalID });
            console.log(learninggoal)
            context.commit('setLearninggoal', learninggoal);
        },
        /**
         * Fetches a learning goal.
         *
         * @param context
         *
         * @returns {Promise<void>}
         */
        async fetchLearningpath(context) {
            const learningpath = await ajax('local_adele_get_learningpath',
                { userid: 0, learninggoalid: store.state.learningGoalID });
            context.commit('setLearninggoal', learningpath);
        },
        /**
         * Fetches all of a user's learning goal.
         *
         * @param context
         *
         * @returns {Promise<void>}
         */
        async fetchLearninggoals(context) {
            const learninggoals = await ajax('local_adele_get_learninggoals');
            context.commit('setLearninggoals', learninggoals);
        },

        /**
         * Fetches all of a user's learning goal.
         *
         * @param context
         *
         * @returns {Promise<void>}
         */
        async fetchLearningpaths(context) {
            const learninggpaths = await ajax('local_adele_get_learningpaths');
            context.commit('setLearningpaths', learninggpaths);
        },















        /**
         * Fetches all of a user's learning goal.
         *
         * @param context
         *
         * @returns {Promise<void>}
         */
        async fetchAvailablecourses(context) {
            const availablecourses = await ajax('local_adele_get_availablecourses');
            context.commit('setAvailablecourses', availablecourses);
        },
        /**
         * Fetches the differentiator handlers.
         *
         * @param context
         *
         * @returns {Promise<void>}
         */
        async getHandlers(context) {
            const handlers = await ajax('local_adele_get_handlers');
            context.commit('setHandlers', handlers);
        },
        /**
         * Saves a learning goal.
         *
         * @param context
         * @param payload
         *
         * @returns {Promise<void>}
         */
        async saveLearninggoal(context, payload) {
            const result = await ajax('local_adele_save_learninggoal', payload);
            context.dispatch('fetchLearninggoals');
            return result.result;
        },

        /**
         * Saves a learning goal.
         *
         * @param context
         * @param payload
         *
         * @returns {Promise<void>}
         */
        async saveLearningpath(context, payload) {
            console.log(payload);
            const result = await ajax('local_adele_save_learningpath',
            { name: payload.name, description: payload.description });
            //context.dispatch('fetchLearninggoals');
            return result.result;
        },
        /**
         * Deletes a learning goal.
         *
         * @param context
         * @param payload
         *
         * @returns {Promise<void>}
         */
        async deleteLearningpath(context, payload) {
            const result = await ajax('local_adele_delete_learningpath', payload);
            context.dispatch('fetchLearningpaths');
            return result.result;
        },
        /**
         * Duplicates a learning goal.
         *
         * @param context
         * @param payload
         *
         * @returns {Promise<void>}
         */
        async duplicateLearningpath(context, payload) {
            const result = await ajax('local_adele_duplicate_learningpath', payload);
            context.dispatch('fetchLearningpaths');
            return result.result;
        },
    }
});

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