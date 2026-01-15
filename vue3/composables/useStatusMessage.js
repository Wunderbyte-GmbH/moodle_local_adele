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
 * Composable for computing node status message
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { computed } from 'vue';

export function useStatusMessage(data) {
  const statusMessage = computed(() => {
    const feedback = data.value?.completion?.feedback || {};
    const completion = feedback.completion || {};

    // Check for "Status 0" condition
    if (
      completion.after == null &&
      completion.after_all == null &&
      completion.before == null &&
      completion.inbetween == null
    ) {
      return '0';
    }

    // Switch case to determine other statuses
    switch (feedback.status_restriction) {
      case 'before':
        if ((!completion.after || completion.after.length === 0) && (completion.before?.length > 0 || completion.inbetween?.length > 0)) {
          return 'a1';
        }
        break;
      case 'inbetween':
        if ((!completion.after || completion.after.length === 0) && (completion.before?.length > 0 || completion.inbetween?.length > 0)) {
          return 'a2';
        }
        if (completion.after && completion.after.length > 0) {
          return completion.after_all && Object.keys(completion.after_all).length > 0 ? 'b' : 'c';
        }
        break;
      case 'after':
        if (completion.after && completion.after.length > 0) {
          return completion.after_all && completion.after_all.length === 0 ? 'e' : 'd';
        }
        if (!completion.after || completion.after.length === 0) {
          return 'f';
        }
        break;
      default:
        return '';
    }
  });

  return { statusMessage };
}
