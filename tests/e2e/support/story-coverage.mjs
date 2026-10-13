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
 * Story coverage of an E2E run, checked by machine (E2E issue, section 3C).
 *
 * Usage: node story-coverage.mjs <results.json> <stories.json> <out-dir> [--filtered]
 *
 * Every test names the user stories of the test plan it covers with a tag,
 * e.g. { tag: ['@story:R3', '@story:R5'] }. stories.json lists the stories
 * this plugin's suite is responsible for. Written to <out-dir>:
 *   coverage.md    Story -> spec -> test -> status -> evidence -> commit
 *   coverage.json  the same, machine-readable
 *
 * Exit code 1 (the job turns red) when
 *   - a test was skipped, marked fixme, or flaky - a run that leaves out a
 *     story or needed a second attempt is no acceptance;
 *   - a test carries no story tag (nobody could tell what it proves);
 *   - and, on a FULL run only, a required story has no passed test.
 * A filtered run (--filtered, i.e. the workflow's grep input) is diagnostic:
 * missing stories are reported, not counted against it.
 *
 * Dependency-free, like summarize-results.mjs.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import fs from 'node:fs';
import path from 'node:path';

const [resultsFile, storiesFile, outDir = '.', ...flags] = process.argv.slice(2);
const filtered = flags.includes('--filtered');
const commit = process.env.GITHUB_SHA || process.env.ADELE_COMMIT || 'local';

const fail = (message) => {
  process.stdout.write(`story coverage: ${message}\n`);
  process.exit(1);
};
if (!resultsFile || !fs.existsSync(resultsFile)) {
  fail(`no results file (${resultsFile}) - the suite did not run`);
}
if (!storiesFile || !fs.existsSync(storiesFile)) {
  fail(`no story list (${storiesFile})`);
}

const report = JSON.parse(fs.readFileSync(resultsFile, 'utf8'));
const stories = JSON.parse(fs.readFileSync(storiesFile, 'utf8'));
const base = path.dirname(path.resolve(resultsFile));

/**
 * Every test of the report with its tags, status and evidence.
 *
 * @param {object} suite A suite node of the JSON report.
 * @param {string[]} titles Enclosing describe titles.
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
      const annotations = test.annotations ?? [];
      rows.push({
        spec: `${spec.file}:${spec.line}`,
        title: [...here, spec.title].join(' › '),
        // The JSON reporter drops the leading @ of a tag ('story:R3');
        // accept both spellings so the check does not depend on it.
        stories: (spec.tags ?? [])
          .map((tag) => tag.replace(/^@/, ''))
          .filter((tag) => tag.startsWith('story:'))
          .map((tag) => tag.slice('story:'.length)),
        // Playwright: expected | unexpected | flaky | skipped. fixme counts as skipped.
        status: annotations.some((a) => a.type === 'fixme') ? 'fixme' : test.status,
        evidence: video ? path.relative(base, video.path).split(path.sep).join('/') : '',
      });
    }
  }
  for (const child of suite.suites ?? []) {
    collect(child, here, rows);
  }
  return rows;
}

const tests = [];
for (const suite of report.suites ?? []) {
  collect(suite, [], tests);
}

const problems = [];
for (const test of tests) {
  if (test.status === 'skipped' || test.status === 'fixme') {
    problems.push(`not run (${test.status}): ${test.title} (${test.spec})`);
  } else if (test.status === 'flaky') {
    problems.push(`flaky - passed only on a later attempt: ${test.title} (${test.spec})`);
  } else if (test.status === 'unexpected') {
    problems.push(`failed: ${test.title} (${test.spec})`);
  }
  if (!test.stories.length) {
    problems.push(`no @story: tag - what does it prove? ${test.title} (${test.spec})`);
  }
}

const matrix = (stories.required ?? []).map((story) => {
  const covering = tests.filter((test) => test.stories.includes(story));
  const passed = covering.filter((test) => test.status === 'expected');
  return { story, covering, passed: passed.length > 0 };
});
const missing = matrix.filter((row) => !row.passed).map((row) => row.story);
if (missing.length && !filtered) {
  problems.push(`required stories without a passed test: ${missing.join(', ')}`);
}
const unknown = [...new Set(tests.flatMap((test) => test.stories))]
  .filter((story) => !(stories.required ?? []).includes(story) && !(stories.alsoCovered ?? []).includes(story));
if (unknown.length) {
  problems.push(`tags naming no story of this plugin's list: ${unknown.join(', ')}`);
}

const STATUS = { expected: 'passed', unexpected: 'FAILED', flaky: 'FLAKY', skipped: 'SKIPPED', fixme: 'FIXME' };
const cell = (text) => String(text).replace(/\|/g, '\\|').replace(/\r?\n/g, ' ');
let md = `### Story coverage - ${stories.plugin ?? ''} - ${problems.length ? 'NOT ACCEPTED' : 'complete'}`
  + `${filtered ? ' (filtered run, diagnostic)' : ''}\n\nCommit \`${commit}\`\n\n`;
md += '| Story | Result | Test | Spec | Evidence |\n|---|---|---|---|---|\n';
for (const row of matrix) {
  if (!row.covering.length) {
    md += `| ${row.story} | **missing** | | | |\n`;
  }
  for (const test of row.covering) {
    md += `| ${row.story} | ${STATUS[test.status] ?? test.status} | ${cell(test.title)} | ${cell(test.spec)} | ${cell(test.evidence)} |\n`;
  }
}
if (problems.length) {
  md += `\n**Why not accepted:**\n\n${problems.map((problem) => `- ${cell(problem)}`).join('\n')}\n`;
}
fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(path.join(outDir, 'coverage.md'), md + '\n');
fs.writeFileSync(path.join(outDir, 'coverage.json'), JSON.stringify({
  plugin: stories.plugin, commit, filtered, problems,
  matrix: matrix.map((row) => ({
    story: row.story,
    passed: row.passed,
    tests: row.covering.map((test) => ({ spec: test.spec, title: test.title, status: test.status, evidence: test.evidence })),
  })),
}, null, 2));
process.stdout.write(md);
process.exit(problems.length ? 1 : 0);
