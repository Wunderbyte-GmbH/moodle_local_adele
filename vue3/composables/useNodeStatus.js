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
 * Composable for the business state of a course node.
 *
 * One derivation for three consumers: the icon a sighted user sees, the
 * accessible name an assistive technology reads (#575 B1), and the
 * data-status attribute a browser test reads (#574). Deriving the state a
 * second time somewhere else is exactly how attribute and display drift
 * apart, after which a green test confirms a fiction.
 *
 * The value is the status the backend already computes in
 * relation_update::getnodestatus() and stores in the node's feedback. It is
 * passed through unchanged and never renamed - a second vocabulary in the
 * frontend would have to be kept in step with the backend forever.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { computed, unref } from 'vue';

/**
 * The states relation_update::getnodestatus() can return.
 *
 * Anything else - a missing feedback object while the path is still loading,
 * or the error string that function returns when it hits its loop limit -
 * becomes UNKNOWN_NODE_STATUS rather than being passed on: a test must not
 * read 'error: loop limit exceeded' as a business state.
 */
export const NODE_STATUSES = ['accessible', 'completed', 'not_accessible', 'closed'];

/** Fallback for anything not in NODE_STATUSES. */
export const UNKNOWN_NODE_STATUS = 'unknown';

/**
 * The business state of one node, plus its localised text.
 *
 * @param {object|import('vue').Ref} data The node's data object (reactive or plain).
 * @param {object|import('vue').Ref} strings The loaded language strings (store.state.strings).
 * @returns {{status: object, statusLabel: object, accessibleName: object}} Computed refs.
 */
export function useNodeStatus(data, strings) {
  const status = computed(() => {
    const value = unref(data)?.completion?.feedback?.status;
    return NODE_STATUSES.includes(value) ? value : UNKNOWN_NODE_STATUS;
  });

  const statusLabel = computed(() => {
    const loaded = unref(strings) || {};
    // Falls back to the raw state rather than to an empty string: a node
    // named "Test Kurs 08 - " would be worse than one naming a state the
    // language pack has not caught up with yet.
    return loaded['node_status_' + status.value] || status.value;
  });

  const accessibleName = computed(() => {
    const name = unref(data)?.fullname;
    return name ? name + ' - ' + statusLabel.value : statusLabel.value;
  });

  return { status, statusLabel, accessibleName };
}
