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
 * Known defects of the frontend, found by the Jest matrices.
 *
 * A test for intended behaviour that the code does not have yet runs only
 * with ADELE_RUN_KNOWN_DEFECTS=1; otherwise it is SKIPPED - visible as such
 * in every run, with the defect's draft named in the title. Never a silent
 * green: the moment the defect is fixed, the test is switched to `it`.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global it */
export const knownDefect = process.env.ADELE_RUN_KNOWN_DEFECTS ? it : it.skip;
