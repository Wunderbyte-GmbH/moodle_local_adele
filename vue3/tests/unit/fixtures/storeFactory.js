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
 * A Vuex store with the state the components read. Strings are returned as
 * their own keys unless a test passes texts, so an assertion on a label shows
 * WHICH string was used.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { createStore } from 'vuex';

/**
 * Strings that answer any key with the key itself.
 *
 * @param {object} texts Real texts for some keys.
 * @returns {object}
 */
export function keyStrings(texts = {}) {
  return new Proxy({ ...texts }, {
    get: (target, key) => (typeof key === 'string' && !(key in target) ? key : target[key]),
  });
}

/**
 * @param {object} state Overrides of the state.
 * @param {object} options { mutations, actions, getters }
 * @returns {object} The store.
 */
export function makeStore(state = {}, options = {}) {
  return createStore({
    state: {
      strings: keyStrings(),
      view: 'student',
      user: 101,
      wwwroot: 'https://example.test',
      availablecourses: [],
      feedbacksettings: { show_feedback: true, show_info: true },
      lpuserpathrelation: { image: '' },
      ...state,
      // Strings a test passes are real texts; every other key still answers
      // with its own name.
      strings: keyStrings(state.strings || {}),
    },
    mutations: options.mutations || {},
    actions: options.actions || {},
    getters: options.getters || {},
  });
}
