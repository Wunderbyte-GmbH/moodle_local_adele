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
 * Progress Bar.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ProgressBar from '../../../../components/nodes_items/ProgressBar.vue';
import * as nodeColors from '../../../../config/nodeColors';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const buildStore = () =>
  createStore({
    state: {
      strings: {
        nodes_items_no_progress: 'No progress yet',
      },
    },
  });

const factory = (props) =>
  mount(ProgressBar, {
    global: { plugins: [buildStore()] },
    props,
  });

describe('ProgressBar.vue', () => {
  it('shows the percentage label when progress is greater than zero', () => {
    const wrapper = factory({ progress: 42, status: 'b' });
    const label = wrapper.find('.progress-label');
    expect(label.text()).toBe('42%');
  });

  it('shows the "no progress" string when progress is zero', () => {
    const wrapper = factory({ progress: 0, status: 'b' });
    const label = wrapper.find('.progress-label');
    expect(label.text()).toBe('No progress yet');
  });

  // aria-valuenow was written as "{{ props.progress }}" - mustache syntax does
  // not interpolate inside attributes, so assistive technology was handed
  // that literal text instead of a number.
  it('exposes the value to assistive technology as a number', () => {
    const wrapper = factory({ progress: 66.67, status: 'b' });
    const bar = wrapper.find('[role="progressbar"]');
    expect(bar.attributes('aria-valuenow')).toBe('66.67');
    expect(bar.attributes('aria-valuemin')).toBe('0');
    expect(bar.attributes('aria-valuemax')).toBe('100');
  });

  it('sets the bar width from the progress prop', () => {
    const wrapper = factory({ progress: 73, status: 'c' });
    const bar = wrapper.find('.progress-bar');
    expect(bar.attributes('style')).toContain('width: 73%');
  });

  it('maps each status to its configured progress-bar colour', () => {
    const cases = {
      '0': nodeColors.progressBarColorCase0,
      a1: nodeColors.progressBarColorCaseA1,
      a2: nodeColors.progressBarColorCaseA2,
      b: nodeColors.progressBarColorCaseB,
      c: nodeColors.progressBarColorCaseC,
      d: nodeColors.progressBarColorCaseD,
      e: nodeColors.progressBarColorCaseE,
      f: nodeColors.progressBarColorCaseF,
    };
    Object.entries(cases).forEach(([status, expected]) => {
      const wrapper = factory({ progress: 10, status });
      expect(wrapper.vm.progressStyle.backgroundColor).toBe(expected);
    });
  });

  it('falls back to the default colour for an unknown status', () => {
    const wrapper = factory({ progress: 10, status: 'not-a-status' });
    expect(wrapper.vm.progressStyle.backgroundColor).toBe(
      nodeColors.progressBarColorCaseDefault,
    );
  });
});
