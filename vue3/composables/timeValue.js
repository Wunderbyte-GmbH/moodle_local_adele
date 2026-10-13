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
 * Time values of the "timed" access condition (issue #581).
 *
 * The window is stored as Unix timestamps in SECONDS. The editor still offers
 * an <input type="datetime-local">, which works in the browser's own time
 * zone; the conversion happens here, at the edge, so a stored value always
 * means one instant - whoever entered it, wherever the server stands.
 *
 * Values stored before #581 are wall-clock strings ("2026-12-01T10:00"). They
 * are still understood until the upgrade has rewritten them.
 *
 * @module     local_adele/timeValue
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const LEGACY = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/;

/**
 * A stored value as Unix seconds.
 *
 * @param {*} value Number, numeric string, legacy wall-clock string or empty.
 * @returns {number|null}
 */
export function toEpochSeconds(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  if (typeof value === 'number') {
    return Number.isFinite(value) ? Math.trunc(value) : null;
  }
  if (typeof value !== 'string') {
    return null;
  }
  if (/^-?\d+$/.test(value)) {
    return parseInt(value, 10);
  }
  if (LEGACY.test(value)) {
    // A datetime-local string is local time by definition; Date parses this
    // exact form as local time.
    const ms = new Date(value).getTime();
    return Number.isNaN(ms) ? null : Math.trunc(ms / 1000);
  }
  return null;
}

/**
 * A stored value in the form an <input type="datetime-local"> expects, in
 * the browser's time zone.
 *
 * @param {*} value
 * @returns {string} "YYYY-MM-DDTHH:mm", or "" when there is no value.
 */
export function toDatetimeLocal(value) {
  const seconds = toEpochSeconds(value);
  if (seconds === null) {
    return '';
  }
  const d = new Date(seconds * 1000);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
    + `T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/**
 * A stored value as a Date, or null.
 *
 * @param {*} value
 * @returns {Date|null}
 */
export function toDate(value) {
  const seconds = toEpochSeconds(value);
  return seconds === null ? null : new Date(seconds * 1000);
}
