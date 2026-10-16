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
 * No test may exist in two spec files.
 *
 * Usage: npx playwright test --list --reporter=json > list.json
 *        node unique-tests.mjs list.json
 *
 * Why: a spec file that was renamed but whose old copy stayed in the
 * repository (a release unpacked over a checkout adds files, it never
 * deletes any) runs a SECOND, outdated version of the same tests. In the
 * fork's run of 2026100724 that was tests/zz-time-conditions.spec.ts and
 * tests/zz-time-feedback.spec.ts next to their successors time-*.spec.ts:
 * five red tests that said nothing about the product. The same title
 * (describe > test) in two files is the trace such a leftover leaves, so it
 * is reported here, before the suite starts, with the files to delete.
 *
 * Exit code 1 when a title appears in more than one file.
 * Dependency-free, like story-coverage.mjs.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { readFileSync } from 'node:fs';

const [listFile] = process.argv.slice(2);
if (!listFile) {
  console.error('Usage: node unique-tests.mjs <playwright --list json>');
  process.exit(2);
}

const report = JSON.parse(readFileSync(listFile, 'utf8'));

/** @type {Map<string, Set<string>>} title -> files */
const files = new Map();
let count = 0;

/**
 * Walk a suite. The top level suites are the files; their titles are not
 * part of a test's name.
 *
 * @param {object} suite
 * @param {string[]} path Titles of the describe blocks above.
 */
function walk(suite, path) {
  for (const spec of suite.specs || []) {
    const title = [...path, spec.title].join(' › ');
    if (!files.has(title)) {
      files.set(title, new Set());
    }
    files.get(title).add(spec.file);
    count++;
  }
  for (const child of suite.suites || []) {
    walk(child, [...path, child.title]);
  }
}

for (const file of report.suites || []) {
  walk(file, []);
}

const twice = [...files.entries()].filter(([, where]) => where.size > 1);
if (count === 0) {
  console.error('::error::The test list is empty - nothing to check.');
  process.exit(1);
}
if (twice.length) {
  console.error(`::error::${twice.length} test(s) exist in more than one spec file.`);
  for (const [title, where] of twice) {
    console.error(`  ${title}\n    in ${[...where].sort().join(', ')}`);
  }
  console.error('A renamed spec whose old copy is still in the repository? Delete the outdated file.');
  process.exit(1);
}
console.log(`${count} tests, every title in exactly one file.`);
