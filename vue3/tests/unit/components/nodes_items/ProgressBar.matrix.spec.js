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
 * ProgressBar.vue - boundaries and every status with 0, a middle value and
 * 100 % (Jest issue, area F1/F2): text, width, the value assistive technology
 * gets, and the colour (the colour only in addition, never alone).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ProgressBar from '../../../../components/nodes_items/ProgressBar.vue';
import * as nodeColors from '../../../../config/nodeColors';
import { mount } from '@vue/test-utils';
import { makeStore } from '../../fixtures/storeFactory';

const NO_PROGRESS = 'No progress';

const render = (progress, status) => {
  const wrapper = mount(ProgressBar, {
    props: { progress, status },
    global: { plugins: [makeStore({ strings: { nodes_items_no_progress: NO_PROGRESS } })] },
  });
  const bar = wrapper.find('[role="progressbar"]');
  return {
    label: wrapper.find('.progress-label').text(),
    width: (bar.attributes('style') || '').match(/width:\s*([^;]+);?/)?.[1]?.trim(),
    aria: bar.attributes('aria-valuenow'),
    colour: bar.element.style.backgroundColor,
  };
};

/** The colour as the browser normalises it. */
const css = (colour) => {
  const probe = document.createElement('div');
  probe.style.backgroundColor = colour;
  return probe.style.backgroundColor;
};

describe('ProgressBar.vue - boundaries', () => {
  it.each([
    [0, NO_PROGRESS, '0%', '0'],
    [1, '1%', '1%', '1'],
    [25, '25%', '25%', '25'],
    [33.33, '33.33%', '33.33%', '33.33'],
    [50, '50%', '50%', '50'],
    [66.67, '66.67%', '66.67%', '66.67'],
    [75, '75%', '75%', '75'],
    [99, '99%', '99%', '99'],
    [100, '100%', '100%', '100'],
  ])('progress %s: label "%s", width %s, aria-valuenow %s', (progress, label, width, aria) => {
    const shown = render(progress, 'b');
    expect(shown.label).toBe(label);
    expect(shown.width).toBe(width);
    expect(shown.aria).toBe(aria);
  });
});

describe('ProgressBar.vue - every status with 0, a middle value and 100 %', () => {
  const statuses = {
    '0': nodeColors.progressBarColorCase0,
    a1: nodeColors.progressBarColorCaseA1,
    a2: nodeColors.progressBarColorCaseA2,
    b: nodeColors.progressBarColorCaseB,
    c: nodeColors.progressBarColorCaseC,
    d: nodeColors.progressBarColorCaseD,
    e: nodeColors.progressBarColorCaseE,
    f: nodeColors.progressBarColorCaseF,
  };
  const rows = [];
  for (const [status, colour] of Object.entries(statuses)) {
    for (const progress of [0, 40, 100]) {
      rows.push([status, progress, colour]);
    }
  }
  it.each(rows)('status %s, progress %s: value shown and the colour of its status', (status, progress, colour) => {
    const shown = render(progress, status);
    expect(shown.aria).toBe(String(progress));
    expect(shown.width).toBe(`${progress}%`);
    expect(shown.label).toBe(progress > 0 ? `${progress}%` : NO_PROGRESS);
    expect(shown.colour).toBe(css(colour));
  });

  it('an unknown or missing status gets the default colour', () => {
    expect(render(40, 'zz').colour).toBe(css(nodeColors.progressBarColorCaseDefault));
    expect(render(40, undefined).colour).toBe(css(nodeColors.progressBarColorCaseDefault));
  });
});

describe('ProgressBar.vue - values outside 0..100, as implemented', () => {
  // The component passes the number through unchanged; there is no
  // specified behaviour for such values yet (clamp, show, refuse - Jest
  // issue F1). These rows PIN today's behaviour so a change is visible; they
  // do not declare it right.
  it.each([
    [-5, NO_PROGRESS, '-5%', '-5'],
    [120, '120%', '120%', '120'],
  ])('progress %s: label "%s", width %s, aria-valuenow %s', (progress, label, width, aria) => {
    const shown = render(progress, 'b');
    expect(shown.label).toBe(label);
    expect(shown.width).toBe(width);
    expect(shown.aria).toBe(aria);
  });
});
