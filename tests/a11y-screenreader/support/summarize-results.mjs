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
 * Turn the Playwright JSON report of a screen reader run into a Markdown table.
 *
 * Usage: node summarize-results.mjs <label> <path/to/results.json>
 *
 * One row per test with outcome, duration and the path of its video
 * relative to the artefact, so whoever opens the artefact can go straight to
 * the recording of the chain they care about - including the ones that
 * passed, which the plan treats as evidence rather than as noise.
 *
 * Dependency-free on purpose: it runs before and independently of the
 * suite's node_modules, and a summary that fails because a package is
 * missing would hide the result it exists to show. Exit code is always 0;
 * whether the job is red is decided by the run itself.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import fs from 'node:fs';
import path from 'node:path';

const [label = 'E2E', file] = process.argv.slice(2);

if (!file || !fs.existsSync(file)) {
  process.stdout.write(`### ${label}\n\n**No results.json** - the suite did not run. ` +
    `See \`run.log\` in the artefact.\n\n`);
  process.exit(0);
}

const report = JSON.parse(fs.readFileSync(file, 'utf8'));
const base = path.dirname(path.resolve(file));

const OUTCOME = {
  expected: 'passed',
  unexpected: 'FAILED',
  flaky: 'flaky',
  skipped: 'skipped',
};

/**
 * Collect every test of a suite tree, depth first, in report order.
 *
 * @param {object} suite A suite node of the JSON report.
 * @param {string[]} titles Titles of the enclosing describe blocks.
 * @param {object[]} rows Accumulator.
 * @returns {object[]} The accumulator.
 */
function collect(suite, titles, rows) {
  const here = suite.title && suite.title !== suite.file ? [...titles, suite.title] : titles;
  for (const spec of suite.specs ?? []) {
    for (const test of spec.tests ?? []) {
      const results = test.results ?? [];
      const last = results[results.length - 1] ?? {};
      const video = (last.attachments ?? []).find((a) => a.name === 'video' && a.path);
      rows.push({
        file: `${spec.file}:${spec.line}`,
        title: [...here, spec.title].join(' › '),
        status: OUTCOME[test.status] ?? test.status,
        duration: last.duration ?? 0,
        video: video ? path.relative(base, video.path).split(path.sep).join('/') : '',
      });
    }
  }
  for (const child of suite.suites ?? []) {
    collect(child, here, rows);
  }
  return rows;
}

/**
 * Escape what would break a Markdown table cell.
 *
 * @param {string} text Cell content.
 * @returns {string} Escaped content.
 */
const cell = (text) => String(text).replace(/\|/g, '\\|').replace(/\r?\n/g, ' ');

const rows = [];
for (const suite of report.suites ?? []) {
  collect(suite, [], rows);
}

const stats = report.stats ?? {};
const verdict = (stats.unexpected ?? 0) > 0 ? 'FAILED' : 'passed';

let out = `### ${cell(label)} - ${verdict}\n\n`;
out += `${rows.length} tests: ${stats.expected ?? 0} passed, ${stats.unexpected ?? 0} failed, ` +
  `${stats.flaky ?? 0} flaky, ${stats.skipped ?? 0} skipped - ` +
  `${((stats.duration ?? 0) / 1000).toFixed(1)} s\n\n`;
out += '| Result | Chain | File | Duration | Video |\n';
out += '|---|---|---|---:|---|\n';
for (const row of rows) {
  out += `| ${row.status} | ${cell(row.title)} | ${cell(row.file)} | ` +
    `${(row.duration / 1000).toFixed(1)} s | ${cell(row.video)} |\n`;
}
out += '\n';

process.stdout.write(out);
