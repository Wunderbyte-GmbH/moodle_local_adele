# Test fixtures

The data the browser suites run against: Moodle course backups, the accounts
that belong to them, and the ADELE learning paths built on top.

## Where they come from

Taken from `github.com/ralferlebach/adele-test`, commit
`4927a19ca0fd0b47e9ddeac889af1f8bc60abd91` (2026-03-16), and kept **here** on
purpose. A test that clones its own data at run time is not reproducible: the
same suite passes today and fails tomorrow because someone edited a learning
path in a repository nobody was watching, and the failure then looks like a
regression in this plugin. A copy that only moves when somebody deliberately
updates it is what makes a red run mean something.

To refresh them, copy the files in again and note the new commit above, in
its own commit, so a behaviour change and a fixture change are never mixed.

## What is in here

| Path | Content |
|---|---|
| `courses/*.mbz` | 23 Moodle course backups. The file name carries the course id of the site they were exported from and the shortname: `sicherung-moodle2-course-<id>-<shortname>-<date>.mbz`. |
| `courses/…-course-2-user-….mbz` | The account backup: `student01`–`student10`, `teacher01` and others. |
| `learningpaths/local_adele_learning_paths.json` | Four learning paths: `Linear A1`, `Linear A2`, `Verzweigt, gestapelt B1`, `Äquivalenzumformung (neu)`. Their nodes carry positions, course names and conditions - unlike a generated fixture, which is why the graph actually renders. |

The learning paths reference the course ids of the **source** site (3 to 14).
A restore assigns new ids, so `seed_fixtures.php` rewrites every
`course_node_id` using the shortname in the backup file name. Never edit the
ids in the JSON to match a local site: the next site would need different
ones again.

## Loading them

```bash
ADELE_SEED_I_KNOW=1 php tests/playwright/seed_fixtures.php
```

Restores the courses, imports the paths, puts `student01` on each of them and
prints `export KEY='value'` lines for the suites. Repeat runs do not duplicate
anything: courses are matched by shortname, paths by name. `--fixtures=<dir>`
points the script at a different set, e.g. a checkout of the upstream
repository when comparing against it.

The size is about 540 kB. `tests/playwright/` carries `export-ignore`, so none
of this reaches a plugin release archive.
