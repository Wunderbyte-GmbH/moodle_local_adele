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
 * UserList.vue - progress rows and views as a matrix (Jest issue, F3, G3):
 * 0 %, part, 100 %, an alternative route whose total differs from the raw
 * node count - each in the teacher view and in the learner view with "all
 * results" and "only own results".
 *
 * Jest checks the display of what the server delivered; the server-side
 * computation and the privacy boundary are PHPUnit's and Playwright's (P).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import UserList from '../../../../components/user_view/UserList.vue';
import tooltipDirective from '../../../../directives/tooltip';
import { makeStore } from '../../fixtures/storeFactory';
import { makeRelation } from '../../fixtures/relationFactory';

const TOOLTIP = '{$a->completed} of {$a->total}';

const CASES = {
  none: makeRelation({ id: 11, firstname: 'Null', progress: { progress: 0, completed_nodes: 0, route_completed: 0, route_total: 3, total_nodes: 3 } }),
  part: makeRelation({ id: 12, firstname: 'Teil', progress: { progress: 33.33, completed_nodes: 1, route_completed: 1, route_total: 3, total_nodes: 3 } }),
  full: makeRelation({ id: 13, firstname: 'Voll', progress: { progress: 100, completed_nodes: 3, route_completed: 3, route_total: 3, total_nodes: 3 } }),
  // Diamond: 4 nodes, the best route has 3; the learner did T01, T02, T04.
  route: makeRelation({ id: 14, firstname: 'Route', progress: { progress: 100, completed_nodes: 3, route_completed: 3, route_total: 3, total_nodes: 4 } }),
};

/**
 * Mount the list.
 *
 * @param {object} state view, user, userlist
 */
function list(state) {
  return mount(UserList, {
    global: {
      plugins: [makeStore({
        strings: { user_view_nodes_tooltip: TOOLTIP },
        learningPathID: 7,
        lpuserpathrelations: Object.values(CASES),
        lpuserpathrelation: { user_id: null, image: '' },
        userlistcollapsed: false,
        ...state,
      }, { mutations: { setUserlistCollapsed: () => undefined } })],
      directives: { tooltip: tooltipDirective },
      stubs: {
        'router-link': { template: '<a><slot /></a>' },
        'transition-group': { template: '<tbody><slot /></tbody>' },
        ProgressBar: { props: ['progress'], template: '<span class="pb">{{ progress }}</span>' },
      },
    },
  });
}

/** The cells of one person's row: [progress, x/y, tooltip]. */
const row = (wrapper, firstname) => {
  const tr = wrapper.findAll('tbody tr').find((r) => r.text().includes(firstname));
  if (!tr) {
    return null;
  }
  const cells = tr.findAll('td');
  const nodes = cells[cells.length - 2];
  return [tr.find('.pb').text(), nodes.text(), nodes.attributes('data-adele-tooltip') ?? nodes.attributes('title') ?? ''];
};

describe('UserList - progress per case (teacher view)', () => {
  it.each([
    ['none', '0', '0/3'],
    ['part', '33.33', '1/3'],
    ['full', '100', '3/3'],
    ['route', '100', '3/3'],
  ])('%s: progress %s, best route %s', (key, progress, route) => {
    const shown = row(list({ view: 'teacher' }), CASES[key].firstname);
    expect(shown[0]).toBe(progress);
    expect(shown[1]).toBe(route);
  });
});

describe('UserList - who sees whom', () => {
  it.each([
    ['teacher', 1, 4, true],
    ['student, all results', 1, 4, false],
    ['student, only own results', 2, 1, false],
  ])('%s (userlist %s): %s rows, ID column %s', (label, userlist, count, idcolumn) => {
    const view = label.startsWith('teacher') ? 'teacher' : 'student';
    const wrapper = list({ view, userlist, user: 12 });
    expect(wrapper.findAll('tbody tr')).toHaveLength(count);
    expect(wrapper.find('thead th[title="user_view_id"]').exists()).toBe(idcolumn);
    if (count === 1) {
      expect(row(wrapper, 'Teil')).not.toBeNull();
    }
  });
});
