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
 * Shared helpers for the test fixtures: a deep merge for overrides.
 *
 * The factories in this folder hold SHAPES and explicit defaults only - no
 * logic of the product. A test states the variant it needs as an override;
 * everything it does not mention keeps the documented default.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const isPlainObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

/**
 * Merge overrides into a fresh copy of the defaults. Arrays and scalars are
 * replaced, plain objects merged; `undefined` in an override deletes the key
 * (to build legacy data with a field missing).
 *
 * @param {object} defaults
 * @param {object} overrides
 * @returns {object}
 */
export function merge(defaults, overrides = {}) {
  const result = JSON.parse(JSON.stringify(defaults));
  for (const [key, value] of Object.entries(overrides)) {
    if (value === undefined) {
      delete result[key];
    } else if (isPlainObject(value) && isPlainObject(result[key])) {
      result[key] = merge(result[key], value);
    } else {
      result[key] = JSON.parse(JSON.stringify(value));
    }
  }
  return result;
}
