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
 * specific_course.vue - "certain node completed" by a chosen node (Jest
 * issue, H4): nothing to choose, one, a change of the choice, a stored
 * choice, a stored choice that no longer exists.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount, flushPromises } from '@vue/test-utils';
import specificCourse from '../../../../../components/restriction/conditions/specific_course.vue';
import { makeStore } from '../../../fixtures/storeFactory';
import { makeLearningPath, makeTreeNode } from '../../../fixtures/learningPathFactory';

const node = (id, fullname) => makeTreeNode(id, { data: { fullname } });

async function chooser(nodes, current, stored) {
  const wrapper = mount(specificCourse, {
    props: { restriction: { node_id: 'condition_1', description: 'd', value: stored } },
    global: { plugins: [makeStore({ node: { node_id: current }, learningpath: makeLearningPath(nodes) })] },
  });
  await flushPromises();
  const options = () => wrapper.findAll('#courseSelect option').filter((o) => o.attributes('disabled') === undefined).map((o) => o.text());
  const last = () => {
    const events = wrapper.emitted('update:modelValue') || [];
    return events.length ? events[events.length - 1][0] : undefined;
  };
  return { wrapper, options, last };
}

describe('specific_course.vue', () => {
  it('only the node itself: nothing to choose', async () => {
    const c = await chooser([node('dndnode_1', 'Testkurs 01')], 'dndnode_1');
    expect(c.options()).toEqual([]);
    expect(c.last()).toBeUndefined();
  });

  it('one other node: offered; choosing it stores it', async () => {
    const c = await chooser([node('dndnode_1', 'Testkurs 01'), node('dndnode_2', 'Testkurs 02')], 'dndnode_2');
    expect(c.options()).toEqual(['Testkurs 01']);
    await c.wrapper.find('#courseSelect').setValue('dndnode_1');
    expect(c.last()).toEqual({ courseid: 'dndnode_1' });
  });

  it('a change of the choice is stored', async () => {
    const nodes = [node('dndnode_1', 'Testkurs 01'), node('dndnode_2', 'Testkurs 02'), node('dndnode_3', 'Testkurs 03')];
    const c = await chooser(nodes, 'dndnode_3');
    await c.wrapper.find('#courseSelect').setValue('dndnode_1');
    await c.wrapper.find('#courseSelect').setValue('dndnode_2');
    expect(c.last()).toEqual({ courseid: 'dndnode_2' });
  });

  it('a stored choice is selected again and emitted', async () => {
    const nodes = [node('dndnode_1', 'Testkurs 01'), node('dndnode_2', 'Testkurs 02')];
    const c = await chooser(nodes, 'dndnode_2', { courseid: 'dndnode_1' });
    expect(c.wrapper.find('#courseSelect').element.value).toBe('dndnode_1');
    expect(c.last()).toEqual({ courseid: 'dndnode_1' });
  });

  it('a stored choice whose node is gone: nothing valid is selected (as implemented, it is still emitted)', async () => {
    const nodes = [node('dndnode_1', 'Testkurs 01'), node('dndnode_2', 'Testkurs 02')];
    const c = await chooser(nodes, 'dndnode_2', { courseid: 'dndnode_9' });
    expect(c.options()).toEqual(['Testkurs 01']);
    expect(c.wrapper.find('#courseSelect').element.value).not.toBe('dndnode_1');
    expect(c.last()).toEqual({ courseid: 'dndnode_9' });
  });
});
