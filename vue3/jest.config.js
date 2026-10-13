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
 * jest.config module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

module.exports = {
  coverageReporters: [
    'lcov',
    'text',
  ],
  preset: '@vue/cli-plugin-unit-jest',
  verbose: true,
  moduleFileExtensions: ['js', 'ts', 'json', 'vue'],
  testMatch: [
    '**/tests/unit/**/*.spec.[jt]s?(x)',
  ],
  transform: {
    '^.+\\.vue$': '@vue/vue3-jest',  // Vue 3 component transformer
    '^.+\\.js$': 'babel-jest',        // JavaScript transformer
    '^.+\\.ts$': 'ts-jest',           // TypeScript transformer
  },
  watchPathIgnorePatterns: [
    '<rootDir>/node_modules/',
  ],
  reporters: [
    'default',
    ['jest-html-reporter', {
      pageTitle: 'Test Report',
      outputPath: 'test-report.html',
      includeFailureMsg: true,
      includeSuiteFailure: true,
    }],
  ],
  // Istanbul (babel) instead of v8: with v8, two runs of the same code gave
  // different coverage (measured 2026-10-13: ProgressBar.vue 100 % and
  // 87.87 % statements, useStatusMessage.js 98.59 % and 67.6 % - Jest 27
  // merges the v8 data of one file loaded by several test files
  // inconsistently). A gate on a number that wanders is no gate. Istanbul
  // counts the compiled templates of .vue files as well, so its numbers are
  // lower; they are identical from run to run.
  coverageProvider: "babel",
  // Coverage gate (Jest issue, "Coverage-Gate"): the GLOBAL floor is the
  // measured coverage, rounded down - it may not sink, and it is raised with
  // every step. Jest measures it WITHOUT the files that have a gate of their
  // own. Baseline 2026-10-13 (part 1): statements 62.03 %, branches 45.7 %,
  // functions 48.85 %, lines 74.37 % (all files 63.48 / 47.22 / 49.45 /
  // 75.72). After part 2, with more files under gates of their own: 62.4 /
  // 47.26 / 50.15 / 76.23 (all files 65.71 / 50.15 / 52.84 / 78.9). The
  // files the systematic matrices cover carry gates of their own, at their
  // measured values. useStatusMessage.js: one `break` after a switch case
  // whose branches all return is unreachable; 100 % is not attainable
  // without changing the code.
  coverageThreshold: {
    global: { statements: 62, branches: 47, functions: 50, lines: 76 },
    './composables/useStatusMessage.js': { statements: 95, branches: 97, functions: 100, lines: 95 },
    './components/nodes_items/MasterConditions.vue': { statements: 100, branches: 100, functions: 100, lines: 100 },
    './components/nodes_items/ProgressBar.vue': { statements: 69, branches: 32, functions: 44, lines: 92 },
    './components/restriction/conditions/timed_dates.vue': { statements: 96, branches: 100, functions: 85, lines: 96 },
    './components/restriction/conditions/parent_node_completed.vue': { statements: 95, branches: 50, functions: 80, lines: 95 },
    './components/nodes/feedbackNode.vue': { statements: 84, branches: 79, functions: 50, lines: 86 },
    './components/restriction/conditions/parent_courses.vue': { statements: 100, branches: 100, functions: 100, lines: 100 },
    './components/restriction/conditions/timed_duration.vue': { statements: 97, branches: 100, functions: 91, lines: 97 },
    './components/restriction/conditions_output/manual_output.vue': { statements: 100, branches: 100, functions: 100, lines: 100 },
    './components/completion/conditions_output/manual_output.vue': { statements: 100, branches: 100, functions: 100, lines: 100 },
    './components/nodes_items/UserFeedbackBlock.vue': { statements: 100, branches: 90, functions: 100, lines: 100 },
    './components/charthelper/userSearch.vue': { statements: 87, branches: 74, functions: 81, lines: 91 },
    './components/nodes/feedbackNodeRestriction.vue': { statements: 87, branches: 70, functions: 62, lines: 90 },
  },
  testEnvironment: 'jsdom',           // Use jsdom for DOM simulation
  moduleNameMapper: {
    '^@vue/test-utils$': '<rootDir>/node_modules/@vue/test-utils',
  },
};
