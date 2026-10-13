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
 * useNodeStatus.spec module.
 *
 * Covers the single derivation behind the node's data-status attribute
 * (#574), its accessible name (#575 B1) and the icon a sighted user sees.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref } from 'vue';
import { useNodeStatus, NODE_STATUSES, UNKNOWN_NODE_STATUS } from '../../../composables/useNodeStatus';

const strings = {
  node_status_accessible: 'accessible',
  node_status_closed: 'locked',
  node_status_completed: 'completed',
  node_status_not_accessible: 'not accessible yet',
  node_status_unknown: 'state unknown',
};

const nodeWith = (status) => ({
  node_id: 'node-1',
  fullname: 'Test Kurs 08',
  completion: { feedback: { status } },
});

describe('useNodeStatus', () => {
  it.each(NODE_STATUSES)('passes the backend status %s through unchanged', (status) => {
    const { status: value } = useNodeStatus(ref(nodeWith(status)), ref(strings));
    expect(value.value).toBe(status);
  });

  it('names the node with its course name and its localised state', () => {
    const { accessibleName } = useNodeStatus(ref(nodeWith('accessible')), ref(strings));
    expect(accessibleName.value).toBe('Test Kurs 08 - accessible');
  });

  it('follows a state that changes at runtime', () => {
    const data = ref(nodeWith('not_accessible'));
    const { status, accessibleName } = useNodeStatus(data, ref(strings));
    expect(status.value).toBe('not_accessible');

    data.value = nodeWith('accessible');
    expect(status.value).toBe('accessible');
    expect(accessibleName.value).toBe('Test Kurs 08 - accessible');
  });

  it('reports an unknown state instead of the backend error string', () => {
    // relation_update::getnodestatus() returns this when it hits its loop
    // limit. A test must not read it as a business state.
    const { status } = useNodeStatus(ref(nodeWith('error: loop limit exceeded')), ref(strings));
    expect(status.value).toBe(UNKNOWN_NODE_STATUS);
  });

  it('reports an unknown state while the feedback is not loaded yet', () => {
    const { status } = useNodeStatus(ref({ node_id: 'node-1', fullname: 'Test Kurs 08' }), ref(strings));
    expect(status.value).toBe(UNKNOWN_NODE_STATUS);
  });

  it('falls back to the raw state when the language pack lacks the string', () => {
    const { accessibleName } = useNodeStatus(ref(nodeWith('accessible')), ref({}));
    expect(accessibleName.value).toBe('Test Kurs 08 - accessible');
  });
});
