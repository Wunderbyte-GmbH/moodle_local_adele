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
 * Manual access and manual completion as the teacher sets them on a
 * learner's node (Jest issue, area C): each false -> true -> false, both
 * together in all four combinations, and that the two never move each other.
 *
 * Jest checks the Vue reaction only; whether access or completion follows
 * is PHPUnit's and Playwright's (chains C3, L3, O1).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import { defineComponent, h, reactive } from 'vue';
import RestrictionOutPutItem from '../../../../components/restriction/RestrictionOutPutItem.vue';
import CompletionOutPutItem from '../../../../components/completion/CompletionOutPutItem.vue';
import { makeStore } from '../../fixtures/storeFactory';
import { makeNode } from '../../fixtures/nodeFactory';
import { knownDefect } from '../../fixtures/knownDefect';

const STRINGS = { restriction_access_manually: 'Unlock node manually', conditions_finish_course: 'Finish node manually' };

/**
 * Both controls of one node, as the teacher view renders them.
 *
 * @param {object} flags manualrestrictionvalue, manualcompletionvalue
 */
function both(flags) {
  const data = reactive(makeNode({ node_id: 'dndnode_2', manualrestriction: true, manualcompletion: true, ...flags }));
  const Host = defineComponent({
    render: () => h('div', [h(RestrictionOutPutItem, { data }), h(CompletionOutPutItem, { data })]),
  });
  const wrapper = mount(Host, { global: { plugins: [makeStore({ strings: STRINGS })] } });
  const boxes = wrapper.findAll('input[type="checkbox"]');
  return { wrapper, data, access: boxes[0], completion: boxes[1] };
}

describe('manual access and completion - each on its own', () => {
  it.each([
    ['access', 'manualrestrictionvalue', 'access'],
    ['completion', 'manualcompletionvalue', 'completion'],
  ])('%s: false -> true -> false, the node data follows', async (_label, field, which) => {
    const c = both({ manualrestrictionvalue: false, manualcompletionvalue: false });
    const box = c[which];
    expect(box.element.checked).toBe(false);
    await box.setValue(true);
    expect(c.data[field]).toBe(true);
    await box.setValue(false);
    expect(c.data[field]).toBe(false);
  });

  it('the labels come from the store', () => {
    const c = both({});
    const labels = c.wrapper.findAll('label').map((label) => label.text());
    expect(labels).toEqual(['Unlock node manually', 'Finish node manually']);
  });
});

describe('manual access and completion - the four combinations', () => {
  it.each([
    [false, false],
    [true, false],
    [false, true],
    [true, true],
  ])('access=%s completion=%s: rendered as given; toggling one leaves the other', async (access, completion) => {
    const c = both({ manualrestrictionvalue: access, manualcompletionvalue: completion });
    expect(c.access.element.checked).toBe(access);
    expect(c.completion.element.checked).toBe(completion);
    await c.access.setValue(!access);
    expect(c.data.manualrestrictionvalue).toBe(!access);
    expect(c.data.manualcompletionvalue).toBe(completion);
    await c.completion.setValue(!completion);
    expect(c.data.manualcompletionvalue).toBe(!completion);
    expect(c.data.manualrestrictionvalue).toBe(!access);
  });
});

describe('manual access and completion - labels lead to their own checkbox', () => {
  knownDefect('[known defect, draft local_adele-issue-manual-checkboxes-share-id] each label points to a checkbox of its own', () => {
    // Both controls use the node id as the checkbox id, so the two labels
    // point to the SAME id - a click on "Finish node manually" toggles the
    // first checkbox with that id: the manual ACCESS.
    const c = both({});
    const ids = c.wrapper.findAll('input[type="checkbox"]').map((box) => box.attributes('id'));
    expect(new Set(ids).size).toBe(2);
    const targets = c.wrapper.findAll('label').map((label) => label.attributes('for'));
    expect(targets[0]).not.toBe(targets[1]);
  });
});
