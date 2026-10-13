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
 * The feedback object a node carries for the learner (completion.feedback),
 * as relation_update delivers it.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { merge } from './merge';

/** Nothing configured, nothing reached: every bucket null. */
export const EMPTY_BUCKETS = { before: null, inbetween: null, after: null, after_all: null };

/**
 * @param {object} overrides e.g. { status_restriction: 'after', completion: { after: ['x'] } }
 * @returns {object}
 */
export function makeFeedback(overrides = {}) {
  return merge({
    status: 'not_accessible',
    status_restriction: 'before',
    status_completion: 'before',
    completion: { ...EMPTY_BUCKETS },
    restriction: { before: null, before_active: null, before_valid: null, inbetween: null, after: null },
  }, overrides);
}
