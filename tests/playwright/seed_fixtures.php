<?php
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
 * Load the ADELE test fixtures into a throwaway site.
 *
 *     php seed_fixtures.php [--fixtures=<dir>] [--student=student01]
 *
 * The fixtures ship with this repository, in tests/playwright/fixtures:
 * course backups, the accounts that go with them, and the learning paths
 * built on top. They are what the project already uses for manual testing,
 * so a browser test runs the same paths a person does - real graphs with
 * positions, names and conditions, rather than the minimal node a generated
 * seed can produce.
 *
 * They are a COPY, not a checkout (see fixtures/README.md for the source and
 * the commit): a suite that fetches its data at run time is not reproducible,
 * and a fixture edited elsewhere would surface here as a regression in this
 * plugin. --fixtures points at a different set when that is wanted.
 *
 * Two things have to be reconciled on the way in:
 *
 * 1. Course ids. The learning paths reference the course ids of the site the
 *    fixtures were exported from. A restore assigns new ones, so every
 *    course_node_id is rewritten via the shortname, which the backup file
 *    name carries (sicherung-moodle2-course-<old id>-<shortname>-...).
 * 2. Repeat runs. Courses are matched by shortname and learning paths by
 *    name, so running this twice does not produce a second copy of anything.
 *
 * Prints "export KEY='value'" lines for the browser suites, like seed.php.
 *
 * DESTRUCTIVE: restores courses and writes learning paths. Throwaway sites
 * only; refuses to run unless ADELE_SEED_I_KNOW=1 is set.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

[$options] = cli_get_params([
    'fixtures' => __DIR__ . '/fixtures',
    'category' => 1,
], []);

if (getenv('ADELE_SEED_I_KNOW') !== '1') {
    cli_error(
        "Refusing to run: this script restores courses and writes learning paths.\n" .
        "Set ADELE_SEED_I_KNOW=1 to confirm this is a throwaway site."
    );
}
if (!is_dir($options['fixtures'] . '/courses')) {
    cli_error('No course backups in ' . $options['fixtures'] . ' - see tests/playwright/fixtures/README.md');
}

// No mail from a fixture run. Enrolling somebody manually sends a welcome
// message, and on a site with a bare "noreply" address Moodle prints
// "Error: ... Invalid address" to STDOUT - straight into the env file this
// script produces, where the next shell that sources it fails with a syntax
// error far away from the cause.
$CFG->noemailever = true;

$fixtures = rtrim($options['fixtures'], '/');
\core\cron::setup_user(get_admin());

// Same two site adjustments seed.php makes, so this script can be used on its
// own: a known admin credential, and no Secure flag on the session cookie -
// a browser drops that cookie over plain http and every login then fails with
// a message naming the wrong cause.
$adminpassword = getenv('ADELE_ADMIN_PASSWORD') ?: 'Playwright!23';
if (strpos($CFG->wwwroot, 'https://') !== 0) {
    set_config('cookiesecure', 0);
}
$admin = get_admin();
$DB->set_field('user', 'password', hash_internal_user_password($adminpassword), ['id' => $admin->id]);

// No user tours on a test site. Core's tours ("Find your way around") open
// for any account that has not dismissed them yet and lay a backdrop over
// the page - whether a click lands first or the tour does is a race the
// browser suite cannot win reliably. They belong to Moodle, not to ADELE.
$DB->set_field('tool_usertours_tours', 'enabled', 0);
\cache::make('tool_usertours', 'tourdata')->purge();

// The fixture accounts come from the backup with unknown passwords.
$fixturepassword = getenv('ADELE_FIXTURE_PASSWORD') ?: 'Playwright!23';

/**
 * Restore one course backup, unless a course with that shortname is there.
 *
 * @param string $file Absolute path of the .mbz file.
 * @param string $shortname The shortname the backup carries.
 * @param int $categoryid Category to restore into.
 * @param bool $restored Set to true when the course was restored by this call.
 * @return int The course id.
 */
function local_adele_restore_fixture_course(string $file, string $shortname, int $categoryid, bool &$restored = false): int {
    global $DB;

    // Case-insensitive: the file name spells the shortname in lower case
    // ("course-2-user"), the backup itself carries the site's spelling
    // ("User"). Comparing them as-is restores the same course twice.
    $select = $DB->sql_equal('shortname', ':shortname', false);
    $existing = $DB->get_field_select('course', 'id', $select, ['shortname' => $shortname]);
    if ($existing) {
        $restored = false;
        return (int) $existing;
    }
    $restored = true;

    $tempdir = \restore_controller::get_tempdir_name(SITEID, get_admin()->id);
    $target = make_backup_temp_directory($tempdir);
    get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($file, $target);

    $courseid = \restore_dbops::create_new_course('', '', $categoryid);
    $controller = new \restore_controller(
        $tempdir,
        $courseid,
        \backup::INTERACTIVE_NO,
        \backup::MODE_GENERAL,
        get_admin()->id,
        \backup::TARGET_NEW_COURSE
    );
    $controller->execute_precheck();
    $controller->execute_plan();
    $controller->destroy();

    return (int) $courseid;
}

// Restore the courses.
// The file name is the only place the fixtures state which course id the
// learning paths mean: sicherung-moodle2-course-<old id>-<shortname>-<date>.
$coursemap = [];
$shortnames = [];
$freshcourses = [];
foreach (glob($fixtures . '/courses/*.mbz') as $file) {
    if (!preg_match('/course-(\d+)-([^-]+)-/', basename($file), $matches)) {
        cli_problem('Skipping unreadable fixture name: ' . basename($file));
        continue;
    }
    [, $oldid, $shortname] = $matches;
    $restored = false;
    $courseid = local_adele_restore_fixture_course($file, $shortname, (int) $options['category'], $restored);
    if ($restored) {
        $freshcourses[] = $courseid;
    }
    $coursemap[(string) $oldid] = (string) $courseid;
    $shortnames[(string) $oldid] = strtoupper($shortname);
}

// Import the learning paths.
$pathsfile = $fixtures . '/learningpaths/local_adele_learning_paths.json';
if (!is_readable($pathsfile)) {
    cli_error('No learning paths in the fixtures: ' . $pathsfile);
}

$imported = [];
$pathmap = [];
// Fixture id -> site path id, as this seed created them last time.
$fixturemapfile = $CFG->dataroot . '/local_adele_e2e_fixturemap.json';
$fixturemap = is_readable($fixturemapfile) ? (json_decode(file_get_contents($fixturemapfile), true) ?: []) : [];
foreach (json_decode(file_get_contents($pathsfile), true) as $fixture) {
    $tree = json_decode($fixture['json'], true);
    $missing = [];
    // NOT "$tree['tree']['nodes'] ?? []" here: ?? yields an expression, so
    // PHP iterates a temporary copy and every by-reference change is thrown
    // away - silently, leaving the original course ids in place.
    if (!isset($tree['tree']['nodes'])) {
        $tree['tree']['nodes'] = [];
    }
    foreach ($tree['tree']['nodes'] as &$node) {
        if (empty($node['data']['course_node_id'])) {
            continue;
        }
        foreach ($node['data']['course_node_id'] as &$courseid) {
            $key = (string) $courseid;
            if (!isset($coursemap[$key])) {
                // Leave the id alone and say so: a node pointing at a course
                // that was never restored is a broken fixture, not something
                // to paper over silently.
                $missing[] = $key;
                continue;
            }
            $courseid = $coursemap[$key];
        }
        unset($courseid);
    }
    unset($node);

    if ($missing) {
        cli_problem(sprintf(
            'Learning path "%s": no restored course for id(s) %s - node(s) left untouched.',
            $fixture['name'],
            implode(', ', array_unique($missing))
        ));
    }

    $record = (object) [
        'name' => $fixture['name'],
        'description' => $fixture['description'],
        'image' => $fixture['image'] ?? '',
        'json' => json_encode($tree),
        'visibility' => $fixture['visibility'] ?? 1,
        'createdby' => get_admin()->id,
        'timecreated' => time(),
        'timemodified' => time(),
    ];

    // Which site path IS this fixture? Chains rename fixture paths and rename
    // them back at the end; a chain that dies in between leaves a path with a
    // suffixed name behind. Matching by name alone then created a duplicate
    // next to it, and the leftover - with whatever owner and editors the chain
    // gave it - poisoned every later run. So, in this order: the id this seed
    // gave the fixture last time; the exact name; a name the chains derive
    // from it ("<name> <suffix>"). Whichever is found is reset, name included.
    $existing = false;
    if (isset($fixture['id'], $fixturemap[(int) $fixture['id']])) {
        $existing = $DB->get_field('local_adele_learning_paths', 'id', ['id' => $fixturemap[(int) $fixture['id']]]);
    }
    if (!$existing) {
        $existing = $DB->get_field('local_adele_learning_paths', 'id', ['name' => $fixture['name']]);
    }
    if (!$existing) {
        $renamed = $DB->get_records_select(
            'local_adele_learning_paths',
            $DB->sql_like('name', '?'),
            [$DB->sql_like_escape($fixture['name']) . ' %'],
            'id ASC',
            'id',
            0,
            1
        );
        $existing = $renamed ? (int) reset($renamed)->id : false;
    }
    if ($existing) {
        $record->id = $existing;
        $DB->update_record('local_adele_learning_paths', $record);
        $pathid = (int) $existing;
    } else {
        $pathid = (int) $DB->insert_record('local_adele_learning_paths', $record);
    }
    $imported[$fixture['name']] = $pathid;
    if (isset($fixture['id'])) {
        $pathmap[(int) $fixture['id']] = $pathid;
        $fixturemap[(int) $fixture['id']] = $pathid;
    }
}
// Remembered for the next run, in the data directory: test bookkeeping, not
// plugin configuration.
file_put_contents($fixturemapfile, json_encode($fixturemap));

// Some fixture courses (l01, l02) arrive with ADELE activities already in
// them, pointing at the learning path ids of the SOURCE site. On a fresh site
// those ids happen to match the imported paths; on any other they point at a
// different path or at none. They are rewritten like the course ids - but
// only in courses restored by THIS run: in a course restored earlier they
// have been rewritten already, and mapping them a second time would move
// them onto whichever path now carries the old number.
foreach ($freshcourses as $courseid) {
    foreach ($DB->get_records('adele', ['course' => $courseid]) as $activity) {
        $old = (int) $activity->learningpathid;
        if (isset($pathmap[$old]) && $pathmap[$old] !== $old) {
            $DB->set_field('adele', 'learningpathid', $pathmap[$old], ['id' => $activity->id]);
        } else if (!isset($pathmap[$old])) {
            cli_problem(sprintf(
                'Activity "%s" in course %d refers to learning path %d, which is not in the fixtures.',
                $activity->name,
                $courseid,
                $old
            ));
        }
    }
}

// One learner per path, plus one on none of them.
//
// Not one learner on all four: entitlement is the union over the paths a
// person is on, so a learner subscribed everywhere is enrolled into T02
// through a second path while the first path still shows T02 as locked.
// Every assertion about "locked means no enrolment" would then be wrong -
// and it would look like a defect in enrol_adele. One learner per path keeps
// each chain readable, and the spare account is the negative control every
// chain needs (plan section 2 G).
$learners = $DB->get_records_select(
    'user',
    $DB->sql_like('username', ':pattern') . ' AND deleted = 0',
    ['pattern' => 'student%'],
    'username ASC'
);
$learners = array_values($learners);
if (!$learners) {
    cli_problem('No student accounts - the user course backup was probably not restored.');
}
foreach ($learners as $learner) {
    // The backup carries the accounts, not usable passwords.
    $DB->set_field('user', 'password', hash_internal_user_password($fixturepassword), ['id' => $learner->id]);
}

$subscribed = [];
$assigned = [];
$position = 0;
foreach ($imported as $name => $pathid) {
    $student = $learners[$position] ?? null;
    $position++;
    if (!$student) {
        cli_problem('Not enough student accounts for path "' . $name . '".');
        continue;
    }
    $assigned[$name] = $student->username;

    // Through the plugin's own subscription API, not by writing the
        // relation table: the API is what triggers the recompute that gives
        // every node its feedback status. Without it the learner view shows
        // an unknown state everywhere, which looks like a bug in the very
        // feature a test is meant to prove.
    $path = $DB->get_record('local_adele_learning_paths', ['id' => $pathid]);
    // A relation carries its own copy of the tree, taken when it was created.
    // On a repeat run the path may have changed, so the old relation is
    // dropped and rebuilt rather than left pointing at the previous import's
    // course ids.
    $DB->delete_records('local_adele_path_user', [
        'learning_path_id' => $pathid,
        'user_id' => $student->id,
    ]);
    // Nobody else among the fixture learners may still be on this path from
    // an earlier run. Found on the test site: the reference learner of
    // Linear A1 was also on Linear A2 and on "Verzweigt gestapelt B1", with
    // an active ADELE enrolment in T01 through Linear A2 - so access to T01
    // could not end when T01 left Linear A1, and the withdrawal chain V2 was
    // red for a reason outside the chain (session 006, part 56). Paths
    // derived later in this script subscribe their own learners again.
    foreach ($learners as $other) {
        if ((int) $other->id === (int) $student->id) {
            continue;
        }
        if ($DB->record_exists('local_adele_path_user', ['learning_path_id' => $pathid, 'user_id' => $other->id])) {
            $DB->delete_records('local_adele_path_user', ['learning_path_id' => $pathid, 'user_id' => $other->id]);
            if (class_exists('\\enrol_adele\\local\\reconciler')) {
                \enrol_adele\local\reconciler::purge_user($pathid, $other->id);
            }
        }
    }
    // So are the enrolments an earlier run produced: left in place, they
    // would let a chain see "access" that this run's reconciliation never
    // granted - the seed would anticipate the very effect under test
    // (E2E issue, section 3B; shown by a mutation that suppressed the
    // enrolment and still left the access chain green).
    if (class_exists('\\enrol_adele\\local\\reconciler')) {
        \enrol_adele\local\reconciler::purge_user($pathid, $student->id);
    }
    \local_adele\enrollment::subscribe_user_to_learning_path($path, (object) [
        'relateduserid' => $student->id,
        'userid' => get_admin()->id,
    ]);
    $subscribed[$name] = $DB->get_field(
        'local_adele_path_user',
        'id',
        ['learning_path_id' => $pathid, 'user_id' => $student->id]
    );
}

// The control: a learner on no path at all. Every chain that proves an effect
// has to show the same effect NOT reaching this person.
$control = $learners[$position] ?? null;
$position++;

// A plain Moodle course with a DELIBERATELY limited membership, for the
// chains that embed a learning path into a course (plan section 16).
//
// Not one of the fixture courses: the account backup enrols everybody into
// "User", and a host course that already contains every learner leaves no
// outsider - so the negative control such a chain needs would not exist.
// Two members and one outsider is the smallest set that can show both.
$hostmembers = array_slice($learners, $position, 2);
$hostoutsider = $learners[$position + 2] ?? null;

$hostcourse = $DB->get_record('course', ['shortname' => 'E2EHOST']);
if (!$hostcourse) {
    $hostcourse = create_course((object) [
        'shortname' => 'E2EHOST',
        'fullname' => 'E2E host course',
        'category' => (int) $options['category'],
        'summary' => 'Host course for the embedding chains. Created by seed_fixtures.php.',
        'summaryformat' => FORMAT_HTML,
    ]);
}
// The two ADELE system roles, for the chains about who may edit what.
//
// The roles themselves come from local_adele's own db/install.php - the
// fixture assigns them rather than inventing its own, so a chain that passes
// says something about the roles the plugin ships.
$systemcontext = context_system::instance();
$roleusers = [];
foreach (
    [
    ['adelemanager', 'fx_adele_manager', 'Fixture', 'Manager'],
    ['adeleassistant', 'fx_adele_assistant', 'Fixture', 'Assistant'],
    ] as [$roleshortname, $username, $firstname, $lastname]
) {
    $person = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$person) {
        $person = (object) [
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $username . '@example.invalid',
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ];
        $person->id = user_create_user($person, false, false);
        $person = $DB->get_record('user', ['id' => $person->id]);
    }
    $DB->set_field('user', 'password', hash_internal_user_password($fixturepassword), ['id' => $person->id]);
    $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshortname]);
    if ($roleid) {
        role_assign((int) $roleid, (int) $person->id, $systemcontext->id);
    } else {
        cli_problem('Role ' . $roleshortname . ' does not exist - is local_adele installed?');
    }
    $roleusers[$roleshortname] = $person;
}

/**
 * The access criterion of a node with exactly ONE condition, wired the way
 * the editor wires it: the condition starts the chain (parentCondition
 * "starting_condition") and points at its feedback node, which points back.
 *
 * Built from the fixture's own nodes, so the structure is the editor's
 * rather than an imitation.
 *
 * @param array $first A first condition of some fixture node (wiring template).
 * @param array $feedback The feedback node belonging to it.
 * @param array $data The data of the condition to install (label, value, texts).
 * @return array The restriction: nodes and edges.
 */
function local_adele_fixture_single_condition(array $first, array $feedback, array $data): array {
    $condition = $first;
    $condition['id'] = 'condition_1';
    $condition['data'] = $data;
    $condition['parentCondition'] = ['starting_condition'];
    $condition['childCondition'] = ['condition_1_feedback'];
    $feedback['id'] = 'condition_1_feedback';
    $feedback['data']['childCondition'] = 'condition_1';
    return [
        'nodes' => [$condition, $feedback],
        'edges' => [[
            'id' => 'condition_1-condition_1_feedback',
            'source' => 'condition_1',
            'sourceHandle' => 'target_and',
            'target' => 'condition_1_feedback',
            'targetHandle' => 'source_feedback',
        ]],
    ];
}

/**
 * The access criterion of a node with TWO conditions, joined the way the
 * restriction editor joins them (RestrictionFlow.vue):
 *   and: the second condition hangs below the first (source_and ->
 *        target_and) - one chain, one feedback node, both must hold;
 *   or:  the second condition starts a chain of its own with its own
 *        feedback node, linked to the first by source_or -> target_or -
 *        either chain suffices.
 *
 * @param array $first A first condition of some fixture node (wiring template).
 * @param array $feedback The feedback node belonging to it.
 * @param array $data1 Data of the first condition.
 * @param array $data2 Data of the second condition.
 * @param string $relation "and" or "or".
 * @return array The restriction: nodes and edges.
 */
function local_adele_fixture_two_conditions(array $first, array $feedback, array $data1, array $data2, string $relation): array {
    $restriction = local_adele_fixture_single_condition($first, $feedback, $data1);
    $second = $first;
    $second['id'] = 'condition_2';
    $second['data'] = $data2;
    $edgebase = $restriction['edges'][0];
    if ($relation === 'and') {
        $second['parentCondition'] = ['condition_1'];
        $second['childCondition'] = [];
        $restriction['nodes'][0]['childCondition'] = ['condition_1_feedback', 'condition_2'];
        $restriction['nodes'][] = $second;
        $restriction['edges'][] = array_merge($edgebase, [
            'id' => 'condition_1-condition_2',
            'source' => 'condition_1',
            'sourceHandle' => 'source_and',
            'target' => 'condition_2',
            'targetHandle' => 'target_and',
        ]);
        return $restriction;
    }
    $second['parentCondition'] = ['starting_condition'];
    $second['childCondition'] = ['condition_2_feedback'];
    $secondfeedback = $feedback;
    $secondfeedback['id'] = 'condition_2_feedback';
    $secondfeedback['data']['childCondition'] = 'condition_2';
    $restriction['nodes'][] = $second;
    $restriction['nodes'][] = $secondfeedback;
    $restriction['edges'][] = array_merge($edgebase, [
        'id' => 'condition_2-condition_2_feedback',
        'source' => 'condition_2',
        'target' => 'condition_2_feedback',
    ]);
    $restriction['edges'][] = array_merge($edgebase, [
        'id' => 'condition_1-condition_2',
        'source' => 'condition_1',
        'sourceHandle' => 'source_or',
        'target' => 'condition_2',
        'targetHandle' => 'target_or',
    ]);
    return $restriction;
}

/**
 * Give the conditions of a fixture path the texts the EDITOR would give them.
 *
 * The fixture paths are derived from one authored path by swapping
 * conditions. The texts of a condition and of its feedback node are stored in
 * the path, written when the author placed the condition - so a derived path
 * kept texts about conditions it no longer had ("... und {start_date} bis
 * {end_date}" on a node without a time condition), and a chain about feedback
 * texts would have judged the fixture instead of the product.
 *
 * What the editor does, done here:
 *  - a condition gets the defaults of its class (get_description() - the very
 *    data the editor loads its palette from), in the language of the author
 *    (this script's: English);
 *  - a feedback node with "use default" ticked gets the texts of its
 *    condition chain, joined by " and " (feedbackNode.vue and
 *    feedbackNodeRestriction.vue, renderFeedback()).
 *
 * @param array $json The path definition.
 * @return array The path definition with editor-default texts.
 */
function local_adele_fixture_editor_texts(array $json): array {
    $and = get_string('course_condition_concatination_and', 'local_adele');
    foreach ($json['tree']['nodes'] as $n => $treenode) {
        foreach (['restriction' => ['before'], 'completion' => ['before', 'after', 'inbetween']] as $type => $actions) {
            $graph = $treenode[$type]['nodes'] ?? null;
            if (!is_array($graph) || !$graph) {
                continue;
            }
            $byid = [];
            foreach ($graph as $g => $node) {
                if (($node['type'] ?? '') !== 'feedback' && !empty($node['data']['label'])) {
                    $class = '\\local_adele\\course_' . $type . '\\conditions\\' . $node['data']['label'];
                    if (class_exists($class)) {
                        $defaults = (new $class())->get_description();
                        unset($defaults['id'], $defaults['label']);
                        $graph[$g]['data'] = array_merge($node['data'], $defaults);
                    }
                }
                $byid[$node['id']] = $g;
            }
            foreach ($graph as $g => $node) {
                if (($node['type'] ?? '') !== 'feedback') {
                    continue;
                }
                // The chain this feedback node speaks for.
                $chain = [];
                $next = $node['data']['childCondition'] ?? null;
                while ($next !== null && isset($byid[$next]) && !in_array($next, $chain, true)) {
                    $chain[] = $next;
                    $following = null;
                    foreach ((array) ($graph[$byid[$next]]['childCondition'] ?? []) as $child) {
                        if (strpos((string) $child, 'feedback') === false) {
                            $following = $child;
                        }
                    }
                    $next = $following;
                }
                $information = [];
                $texts = array_fill_keys($actions, []);
                foreach ($chain as $position => $conditionid) {
                    $data = $graph[$byid[$conditionid]]['data'];
                    $visible = !empty($data['visibility']);
                    // The first condition's information is always part of
                    // it; the following ones only while visible - as the
                    // completion editor has it. (The restriction editor adds
                    // them regardless; every fixture condition is visible.)
                    if ($position === 0 || $visible) {
                        $information[] = (string) ($data['information'] ?? '');
                    }
                    if ($visible) {
                        foreach ($actions as $action) {
                            $texts[$action][] = (string) ($data['description_' . $action] ?? '');
                        }
                    }
                }
                $graph[$g]['data']['information'] = implode($and, $information);
                foreach ($actions as $action) {
                    if (!empty($node['data']['feedback_' . $action . '_checkmark'])) {
                        $graph[$g]['data']['feedback_' . $action] = implode($and, $texts[$action]);
                    }
                }
            }
            $json['tree']['nodes'][$n][$type]['nodes'] = $graph;
        }
    }
    return $json;
}

/**
 * A second person for a fixture path - the negative control of the plan
 * (section 2 G): on the same path, through the same host course, but
 * completing nothing. Starts clean like the path's own learner: no relation
 * to the path, no ADELE enrolments from it, no completions in its courses.
 *
 * @param string $username The account (created if missing).
 * @param string $lastname Its last name.
 * @param string $password The fixture password.
 * @param int $pathid The learning path.
 * @return stdClass The person.
 */
function local_adele_fixture_negative_person(string $username, string $lastname, string $password, int $pathid): stdClass {
    global $DB;
    $person = local_adele_fixture_person($username, $lastname, $password);
    $json = json_decode((string) $DB->get_field('local_adele_learning_paths', 'json', ['id' => $pathid]), true);
    $courseids = [];
    foreach ($json['tree']['nodes'] ?? [] as $node) {
        foreach ((array) ($node['data']['course_node_id'] ?? []) as $courseid) {
            $courseids[] = (int) $courseid;
        }
    }
    foreach (array_unique($courseids) as $courseid) {
        $cmids = $DB->get_fieldset_select('course_modules', 'id', 'course = ?', [$courseid]);
        if ($cmids) {
            [$insql, $inparams] = $DB->get_in_or_equal($cmids);
            $DB->delete_records_select(
                'course_modules_completion',
                "userid = ? AND coursemoduleid {$insql}",
                array_merge([$person->id], $inparams)
            );
        }
        $DB->delete_records('course_completion_crit_compl', ['userid' => $person->id, 'course' => $courseid]);
        $DB->delete_records('course_completions', ['userid' => $person->id, 'course' => $courseid]);
    }
    $DB->delete_records('local_adele_path_user', ['learning_path_id' => $pathid, 'user_id' => $person->id]);
    if (class_exists('\\enrol_adele\\local\\reconciler')) {
        \enrol_adele\local\reconciler::purge_user($pathid, $person->id);
    }
    return $person;
}

/**
 * A host course of its own for one fixture path: one mod_adele activity
 * (source "this course"), the learner as its only member. Activities from an
 * earlier run are removed first.
 *
 * @param string $shortname Short name of the host course.
 * @param string $activityname Name of the activity.
 * @param int $pathid The learning path.
 * @param stdClass $learner The learner.
 * @param int $category Course category.
 * @param int $userlist "Participant results": 1 all participants, 2 only own.
 * @param bool $completionrule Whether the activity completes when the learning path is finished.
 * @param stdClass[] $others Further members, e.g. the negative control of a chain: on the
 *                           same path through the same course, but doing nothing.
 * @return stdClass The host course.
 */
function local_adele_fixture_host_course(
    string $shortname,
    string $activityname,
    int $pathid,
    stdClass $learner,
    int $category,
    int $userlist = 1,
    bool $completionrule = false,
    array $others = []
): stdClass {
    global $DB;
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        $course = create_course((object) [
            'shortname' => $shortname,
            'fullname' => 'E2E host course ' . $shortname,
            'category' => $category,
            'summary' => 'Shows one fixture learning path to its learner. Created by seed_fixtures.php.',
            'summaryformat' => FORMAT_HTML,
        ]);
    }
    foreach ($DB->get_records('adele', ['course' => $course->id]) as $activity) {
        $cm = get_coursemodule_from_instance('adele', $activity->id, $course->id);
        if ($cm) {
            course_delete_module($cm->id);
        }
    }
    $manual = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
    if ($manual && $instance && $roleid) {
        $manual->enrol_user($instance, $learner->id, $roleid);
        foreach ($others as $other) {
            $manual->enrol_user($instance, $other->id, $roleid);
        }
    }
    local_adele_fixture_embed($course, $activityname, $pathid, $userlist, $completionrule);
    return $course;
}

/**
 * Embed a path into a course: one mod_adele activity, the way the form
 * creates it.
 *
 * @param stdClass $course The embedding course.
 * @param string $activityname The activity's name.
 * @param int $pathid The path.
 * @param int $userlist "Participant results": 1 all participants, 2 only own.
 * @param bool $completionrule Whether the activity completes when the learning path is finished.
 * @return int The course module id.
 */
function local_adele_fixture_embed(
    stdClass $course,
    string $activityname,
    int $pathid,
    int $userlist = 1,
    bool $completionrule = false
): int {
    global $DB;
    if ($completionrule && empty($course->enablecompletion)) {
        // Completion tracking is a course setting; the rule needs it.
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
        $course->enablecompletion = 1;
        rebuild_course_cache($course->id, true);
    }
    $module = create_module((object) [
        'modulename' => 'adele',
        'course' => $course->id,
        'section' => 0,
        'visible' => 1,
        'name' => $activityname,
        // Modules with a description want it the way the form sends it.
        'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
        'learningpathid' => $pathid,
        'participantslist' => '1',
        'hostenrolmentmode' => 'visible',
        'userlist' => $userlist,
        'view' => 1,
        'completion' => $completionrule ? COMPLETION_TRACKING_AUTOMATIC : COMPLETION_TRACKING_NONE,
        'completionview' => 0,
        'completionlearningpathfinished' => $completionrule ? 1 : 0,
    ]);
    return (int) $module->coursemodule;
}

/**
 * A fixture person, created once and given the fixture password.
 *
 * @param string $username Login.
 * @param string $lastname Last name; the first name is "Fixture".
 * @param string $password The fixture password.
 * @return stdClass The user record.
 */
function local_adele_fixture_person(string $username, string $lastname, string $password): stdClass {
    global $DB, $CFG;
    $person = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$person) {
        $person = (object) [
            'username' => $username,
            'firstname' => 'Fixture',
            'lastname' => $lastname,
            'email' => $username . '@example.invalid',
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ];
        $person->id = user_create_user($person, false, false);
        $person = $DB->get_record('user', ['id' => $person->id]);
    }
    $DB->set_field('user', 'password', hash_internal_user_password($password), ['id' => $person->id]);
    return $person;
}

/**
 * Create or refresh a fixture path derived from another one, with a new
 * access criterion on its entry node, and subscribe one dedicated learner.
 *
 * @param string $name Name of the path.
 * @param array $base Decoded JSON of the path to derive from.
 * @param array $restriction The entry node's new access criterion.
 * @param string $username The learner's account (created if missing).
 * @param string $lastname The learner's last name.
 * @param string $password The fixture password.
 * @return array [path id, learner record, relation timecreated]
 */
function local_adele_fixture_derived_path(
    string $name,
    array $base,
    array $restriction,
    string $username,
    string $lastname,
    string $password
): array {
    foreach ($base['tree']['nodes'] as $n => $node) {
        if ($node['id'] === 'dndnode_1') {
            $base['tree']['nodes'][$n]['restriction'] = $restriction;
        }
    }
    return local_adele_fixture_store_path($name, $base, $username, $lastname, $password);
}

/**
 * Create or refresh a fixture path from a complete JSON structure and
 * subscribe one dedicated learner, with a clean slate: no previous
 * subscription, no ADELE enrolments, no course completions in the path's
 * courses (a completion is permanent in Moodle and would leak from one run
 * into the next).
 *
 * @param string $name Name of the path.
 * @param array $json The decoded path JSON.
 * @param string $username The learner's account (created if missing).
 * @param string $lastname The learner's last name.
 * @param string $password The fixture password.
 * @return array [path id, learner record, relation timecreated]
 */
function local_adele_fixture_store_path(
    string $name,
    array $json,
    string $username,
    string $lastname,
    string $password
): array {
    global $DB, $CFG;
    // Remembered by name, so that a later chain can put a learner of its own
    // on the same definition (chain F does).
    $GLOBALS['local_adele_fixture_definitions'][$name] = $json;
    $base = local_adele_fixture_editor_texts($json);
    $record = (object) [
        'name' => $name,
        'description' => 'E2E fixture derived from Linear A1: ' . $name . '.',
        'json' => json_encode($base),
        'timemodified' => time(),
        'createdby' => get_admin()->id,
        'visibility' => 1,
    ];
    $existing = $DB->get_record('local_adele_learning_paths', ['name' => $name]);
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('local_adele_learning_paths', $record);
        $pathid = (int) $existing->id;
    } else {
        $record->timecreated = time();
        $pathid = (int) $DB->insert_record('local_adele_learning_paths', $record);
    }
    $learner = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$learner) {
        $learner = (object) [
            'username' => $username,
            'firstname' => 'Fixture',
            'lastname' => $lastname,
            'email' => $username . '@example.invalid',
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ];
        $learner->id = user_create_user($learner, false, false);
        $learner = $DB->get_record('user', ['id' => $learner->id]);
    }
    $DB->set_field('user', 'password', hash_internal_user_password($password), ['id' => $learner->id]);
    // Forget every completion in the path's courses: activity, criteria, course.
    // And in every other fixture course (T01, T02, ...): a chain may extend
    // the path by a course (Z1 adds T04), and that completion would otherwise
    // be waiting for the next run, completed before anybody did anything.
    $courseids = $DB->get_fieldset_select('course', 'id', $DB->sql_like('shortname', ':t'), ['t' => 'T__']);
    foreach ($base['tree']['nodes'] as $node) {
        foreach ((array) ($node['data']['course_node_id'] ?? []) as $courseid) {
            $courseids[] = (int) $courseid;
        }
    }
    foreach (array_unique($courseids) as $courseid) {
        $cmids = $DB->get_fieldset_select('course_modules', 'id', 'course = ?', [$courseid]);
        if ($cmids) {
            [$insql, $inparams] = $DB->get_in_or_equal($cmids);
            $DB->delete_records_select(
                'course_modules_completion',
                "userid = ? AND coursemoduleid {$insql}",
                array_merge([$learner->id], $inparams)
            );
        }
        $DB->delete_records('course_completion_crit_compl', ['userid' => $learner->id, 'course' => $courseid]);
        $DB->delete_records('course_completions', ['userid' => $learner->id, 'course' => $courseid]);
    }
    \cache::make('core', 'completion')->purge();
    \cache::make('core', 'coursecompletion')->purge();
    $DB->delete_records('local_adele_path_user', ['learning_path_id' => $pathid, 'user_id' => $learner->id]);
    if (class_exists('\\enrol_adele\\local\\reconciler')) {
        \enrol_adele\local\reconciler::purge_user($pathid, $learner->id);
    }
    \local_adele\enrollment::subscribe_user_to_learning_path(
        $DB->get_record('local_adele_learning_paths', ['id' => $pathid]),
        (object) ['relateduserid' => $learner->id, 'userid' => get_admin()->id]
    );
    $created = (int) $DB->get_field(
        'local_adele_path_user',
        'timecreated',
        ['learning_path_id' => $pathid, 'user_id' => $learner->id]
    );
    return [$pathid, $learner, $created];
}

// A learning path whose ENTRY node is open only within a fixed window - for
// the chains about time (plan section 9). Derived from the imported
// "Linear A1": same courses and structure, but the entry node's access
// criterion is replaced by a single timed condition, 2030-01-15 10:00 to
// 12:00 UTC. The chains move the server's clock there (libfaketime); with the
// real clock the window lies in the future and the course stays closed.
// Boundaries are timestamps and half-open (#581).
/** @var int Start of the fixture window: 2030-01-15 10:00:00 UTC. */
const LOCAL_ADELE_FIXTURE_WINDOW_START = 1894701600;
/** @var int End of the fixture window: 2030-01-15 12:00:00 UTC. */
const LOCAL_ADELE_FIXTURE_WINDOW_END = 1894708800;
/** @var int Start of the second window (T4/T5): 2031-01-15 10:00:00 UTC. */
const LOCAL_ADELE_FIXTURE_WINDOW2_START = 1926237600;
/** @var int End of the second window (T4/T5): 2031-01-15 12:00:00 UTC. */
const LOCAL_ADELE_FIXTURE_WINDOW2_END = 1926244800;
$timepath = null;
$timelearner = null;
$durationpath = null;
$durationlearner = null;
$durationcreated = 0;
$c1paths = [];
$c3path = null;
$timecombined = [];
$routepaths = [];
$runningpath = null;
$linear4path = null;
$manualcompletionpath = null;
$mastercompletionpath = null;
$masteraccesspath = null;
$masteraccesstimepath = null;
$masterbothpath = null;
$privacypath = null;
$privacysecond = null;
$privacythird = null;
$finalpaths = [];
if (!empty($imported['Linear A1'])) {
    $base = json_decode($DB->get_field('local_adele_learning_paths', 'json', ['id' => $imported['Linear A1']]), true);
    $timedtemplate = null;
    $firsttemplate = null;
    $feedbacktemplate = null;
    foreach ($base['tree']['nodes'] as $node) {
        foreach ($node['restriction']['nodes'] ?? [] as $condition) {
            if (($condition['data']['label'] ?? '') === 'timed' && !$timedtemplate) {
                $timedtemplate = $condition;
            }
            if (($condition['parentCondition'][0] ?? '') === 'starting_condition' && !$firsttemplate) {
                $firsttemplate = $condition;
            }
            if (($condition['id'] ?? '') === 'condition_1_feedback' && !$feedbacktemplate) {
                $feedbacktemplate = $condition;
            }
        }
    }
    if ($timedtemplate && $firsttemplate && $feedbacktemplate) {
        // T1/T2: a fixed window.
        $data = $timedtemplate['data'];
        $data['value'] = ['start' => LOCAL_ADELE_FIXTURE_WINDOW_START, 'end' => LOCAL_ADELE_FIXTURE_WINDOW_END];
        [$timepath, $timelearner] = local_adele_fixture_derived_path(
            'E2E Zeitfenster',
            $base,
            local_adele_fixture_single_condition($firsttemplate, $feedbacktemplate, $data),
            'fx_time_learner',
            'Time',
            $fixturepassword
        );

        // T3: a relative window - one day from the learner's assignment to
        // the path (selectedOption "0"; "1" would count from the first
        // enrolment into the node).
        $data = $timedtemplate['data'];
        $data['label'] = 'timed_duration';
        $data['name'] = get_string('course_name_condition_timed_duration', 'local_adele');
        $data['description'] = get_string('course_description_condition_timed_duration', 'local_adele');
        $data['description_before'] = get_string('course_restricition_before_condition_timed_duration', 'local_adele');
        $data['information'] = get_string('course_information_condition_timed_duration', 'local_adele');
        $data['value'] = ['selectedOption' => '0', 'durationValue' => '0', 'selectedDuration' => 1];
        [$durationpath, $durationlearner, $durationcreated] = local_adele_fixture_derived_path(
            'E2E Zeitdauer',
            $base,
            local_adele_fixture_single_condition($firsttemplate, $feedbacktemplate, $data),
            'fx_duration_learner',
            'Duration',
            $fixturepassword
        );

        // C1: access by predecessors. Every course's completion is the
        // forum "Announcements" with manual completion - a learner can
        // really complete a course with "Mark as done".
        $parentcriterion = function (array $ids, int $min) use ($firsttemplate, $feedbacktemplate): array {
            $data = $firsttemplate['data'];
            $data['value'] = ['courses_id' => $ids, 'min_courses' => $min];
            return local_adele_fixture_single_condition($firsttemplate, $feedbacktemplate, $data);
        };
        $edgetemplate = $base['tree']['edges'][0];
        unset($edgetemplate['sourceNode'], $edgetemplate['targetNode']);
        $edge = function (string $from, string $to) use ($edgetemplate): array {
            return array_merge($edgetemplate, ['id' => $to . $from, 'source' => $from, 'target' => $to]);
        };

        // 1 of 1: the linear path, each node after its single predecessor,
        // without the reference path's extra (expired) time condition.
        $single = $base;
        foreach ($single['tree']['nodes'] as $n => $node) {
            if ($node['id'] === 'dndnode_2') {
                $single['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_1'], 1);
            } else if ($node['id'] === 'dndnode_3') {
                $single['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_2'], 1);
            }
        }
        $c1paths['single'] = local_adele_fixture_store_path(
            'E2E Vorgänger 1 von 1',
            $single,
            'fx_c1_single',
            'Predecessor single',
            $fixturepassword
        );

        // 1 of 2 and 2 of 2: T01 and T02 both start the path, T03 follows
        // BOTH. Only the number of predecessors to complete differs.
        foreach (['any' => 1, 'all' => 2] as $variant => $min) {
            $fan = $base;
            foreach ($fan['tree']['nodes'] as $n => $node) {
                if ($node['id'] === 'dndnode_1') {
                    $fan['tree']['nodes'][$n]['childCourse'] = ['dndnode_3'];
                } else if ($node['id'] === 'dndnode_2') {
                    $fan['tree']['nodes'][$n]['parentCourse'] = ['starting_node'];
                    $fan['tree']['nodes'][$n]['childCourse'] = ['dndnode_3'];
                    $fan['tree']['nodes'][$n]['restriction'] = ['nodes' => [], 'edges' => []];
                    $fan['tree']['nodes'][$n]['position'] = ['x' => 600, 'y' => 0];
                } else if ($node['id'] === 'dndnode_3') {
                    $fan['tree']['nodes'][$n]['parentCourse'] = ['dndnode_1', 'dndnode_2'];
                    $fan['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_1', 'dndnode_2'], $min);
                    $fan['tree']['nodes'][$n]['position'] = ['x' => 300, 'y' => 600];
                }
            }
            $fan['tree']['edges'] = [$edge('dndnode_1', 'dndnode_3'), $edge('dndnode_2', 'dndnode_3')];
            $c1paths[$variant] = local_adele_fixture_store_path(
                $min === 1 ? 'E2E Vorgänger 1 von 2' : 'E2E Vorgänger 2 von 2',
                $fan,
                'fx_c1_' . $variant,
                'Predecessor ' . $variant,
                $fixturepassword
            );
        }

        // C3: manual release. Node 2's only access condition is "manual":
        // a teacher unlocks it for this learner in the learner's path view.
        $data = $firsttemplate['data'];
        $data['label'] = 'manual';
        $data['name'] = get_string('course_name_condition_restriction_manual', 'local_adele');
        $data['description'] = get_string('course_description_condition_restriction_manual', 'local_adele');
        $data['description_before'] = get_string('course_restricition_before_condition_manual', 'local_adele');
        $data['information'] = get_string('course_information_condition_manual', 'local_adele');
        $data['value'] = null;
        $manualpath = $base;
        foreach ($manualpath['tree']['nodes'] as $n => $node) {
            if ($node['id'] === 'dndnode_2') {
                $manualpath['tree']['nodes'][$n]['restriction'] =
                    local_adele_fixture_single_condition($firsttemplate, $feedbacktemplate, $data);
            } else if ($node['id'] === 'dndnode_3') {
                $manualpath['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_2'], 1);
            }
        }
        $c3path = local_adele_fixture_store_path(
            'E2E Manuelle Freigabe',
            $manualpath,
            'fx_c3_manual',
            'Manual release',
            $fixturepassword
        );

        // A: alternative routes - a diamond. T01 opens T02 and T03; T04
        // needs either of them (1 of 2, the editor's default). Each variant
        // of the plan has a learner of its own: only T02, only T03, both,
        // neither.
        $courset04 = $DB->get_record('course', ['shortname' => 'T04']);
        $diamond = $base;
        $nodes = [];
        foreach ($diamond['tree']['nodes'] as $node) {
            $nodes[$node['id']] = $node;
        }
        if ($courset04 && isset($nodes['dndnode_1'], $nodes['dndnode_2'], $nodes['dndnode_3'])) {
            $fourth = $nodes['dndnode_3'];
            $fourth['id'] = 'dndnode_4';
            $fourth['data']['node_id'] = 'dndnode_4';
            $fourth['data']['course_node_id'] = [(string) $courset04->id];
            $fourth['data']['fullname'] = $courset04->fullname;
            $fourth['data']['shortname'] = $courset04->shortname;
            $fourth['parentCourse'] = ['dndnode_2', 'dndnode_3'];
            $fourth['childCourse'] = [];
            $fourth['restriction'] = $parentcriterion(['dndnode_2', 'dndnode_3'], 1);
            $fourth['position'] = ['x' => 300, 'y' => 1200];
            $nodes['dndnode_1']['childCourse'] = ['dndnode_2', 'dndnode_3'];
            $nodes['dndnode_2']['childCourse'] = ['dndnode_4'];
            $nodes['dndnode_2']['restriction'] = $parentcriterion(['dndnode_1'], 1);
            $nodes['dndnode_2']['position'] = ['x' => 0, 'y' => 600];
            $nodes['dndnode_3']['parentCourse'] = ['dndnode_1'];
            $nodes['dndnode_3']['childCourse'] = ['dndnode_4'];
            $nodes['dndnode_3']['restriction'] = $parentcriterion(['dndnode_1'], 1);
            $nodes['dndnode_3']['position'] = ['x' => 600, 'y' => 600];
            $nodes['dndnode_4'] = $fourth;
            $diamond['tree']['nodes'] = array_values($nodes);
            $diamond['tree']['edges'] = [
                $edge('dndnode_1', 'dndnode_2'),
                $edge('dndnode_1', 'dndnode_3'),
                $edge('dndnode_2', 'dndnode_4'),
                $edge('dndnode_3', 'dndnode_4'),
            ];
            foreach (['t02', 't03', 'both', 'none'] as $variant) {
                $routepaths[$variant] = local_adele_fixture_store_path(
                    'E2E Alternative Routen',
                    $diamond,
                    'fx_a_' . $variant,
                    'Route ' . $variant,
                    $fixturepassword
                );
            }
        }

        // L1: a linear path with FOUR required steps, T01 -> T02 -> T03 ->
        // T04, each completing on its course. The reference path's three
        // nodes plus a fourth on T04; no time conditions.
        $courset04l = $DB->get_record('course', ['shortname' => 'T04']);
        $linear4 = $base;
        $byid = [];
        foreach ($linear4['tree']['nodes'] as $node) {
            $byid[$node['id']] = $node;
        }
        if ($courset04l && isset($byid['dndnode_3'])) {
            $fourth = $byid['dndnode_3'];
            $fourth['id'] = 'dndnode_4';
            $fourth['data']['node_id'] = 'dndnode_4';
            $fourth['data']['course_node_id'] = [(string) $courset04l->id];
            $fourth['data']['fullname'] = $courset04l->fullname;
            $fourth['data']['shortname'] = $courset04l->shortname;
            $fourth['parentCourse'] = ['dndnode_3'];
            $fourth['childCourse'] = [];
            $fourth['restriction'] = $parentcriterion(['dndnode_3'], 1);
            $fourth['position'] = ['x' => 0, 'y' => 1800];
            $byid['dndnode_2']['restriction'] = $parentcriterion(['dndnode_1'], 1);
            $byid['dndnode_3']['restriction'] = $parentcriterion(['dndnode_2'], 1);
            $byid['dndnode_3']['childCourse'] = ['dndnode_4'];
            $byid['dndnode_4'] = $fourth;
            $linear4['tree']['nodes'] = array_values($byid);
            $linear4['tree']['edges'] = [
                $edge('dndnode_1', 'dndnode_2'),
                $edge('dndnode_2', 'dndnode_3'),
                $edge('dndnode_3', 'dndnode_4'),
            ];
            $linear4path = local_adele_fixture_store_path(
                'E2E Linear vier Schritte',
                $linear4,
                'fx_l_linear',
                'Linear four',
                $fixturepassword
            );
        }

        // L3/O1: manual completion. Node 1 completes ONLY when a teacher
        // ticks it in the learner's path view; node 2 follows node 1.
        $manualcompletion = $base;
        foreach ($manualcompletion['tree']['nodes'] as $n => $node) {
            if ($node['id'] === 'dndnode_1') {
                foreach ($node['completion']['nodes'] ?? [] as $c => $condition) {
                    if (($condition['data']['label'] ?? '') === 'course_completed') {
                        $data = $condition['data'];
                        $data['label'] = 'manual';
                        $data['name'] = get_string('course_name_condition_completion_manual', 'local_adele');
                        $data['description'] = get_string('course_description_condition_completion_manual', 'local_adele');
                        $data['information'] = get_string('course_information_condition_completion_manual', 'local_adele');
                        $data['value'] = null;
                        $manualcompletion['tree']['nodes'][$n]['completion']['nodes'][$c]['data'] = $data;
                    }
                }
            } else if ($node['id'] === 'dndnode_2') {
                $manualcompletion['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_1'], 1);
            } else if ($node['id'] === 'dndnode_3') {
                $manualcompletion['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_2'], 1);
            }
        }
        $manualcompletionpath = local_adele_fixture_store_path(
            'E2E Manueller Abschluss',
            $manualcompletion,
            'fx_l_manual',
            'Manual completion',
            $fixturepassword
        );

        // L4/O3: master completion. A plain linear path (each node completes
        // on its course, each follows its predecessor); the learner completes
        // nothing - the teacher's "Master completion checkbox" is the only way
        // a node can become completed.
        $masterpath = $base;
        foreach ($masterpath['tree']['nodes'] as $n => $node) {
            if ($node['id'] === 'dndnode_2') {
                $masterpath['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_1'], 1);
            } else if ($node['id'] === 'dndnode_3') {
                $masterpath['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_2'], 1);
            }
        }
        $mastercompletionpath = local_adele_fixture_store_path(
            'E2E Master-Abschluss',
            $masterpath,
            'fx_l_master',
            'Master completion',
            $fixturepassword
        );

        // O2: master access. (a) Linear, node 2 requires node 1 - the master
        // access checkbox opens node 2 without node 1 being completed. (b) Node
        // 1 open only in the 2030 window - the master access opens it now.
        $masteraccess = $base;
        foreach ($masteraccess['tree']['nodes'] as $n => $node) {
            if ($node['id'] === 'dndnode_2') {
                $masteraccess['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_1'], 1);
            } else if ($node['id'] === 'dndnode_3') {
                $masteraccess['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_2'], 1);
            }
        }
        $masteraccesspath = local_adele_fixture_store_path(
            'E2E Master-Zugang',
            $masteraccess,
            'fx_o2_access',
            'Master access',
            $fixturepassword
        );
        // O4: both master checkboxes on the same node, in all four
        // combinations - the same structure as O2, a learner of its own.
        $masterbothpath = local_adele_fixture_store_path(
            'E2E Master beide',
            $masteraccess,
            'fx_o4_both',
            'Master both',
            $fixturepassword
        );
        // P: learner data. ONE path with TWO learners - the chain gives them
        // different progress and then asks who may see whose.
        $privacypath = local_adele_fixture_store_path(
            'E2E Lernerdaten',
            $masteraccess,
            'fx_p_a',
            'Privacy Alpha',
            $fixturepassword
        );
        $privacysecond = local_adele_fixture_store_path(
            'E2E Lernerdaten',
            $masteraccess,
            'fx_p_b',
            'Privacy Beta',
            $fixturepassword
        );
        // ... and a third learner of the SAME path who belongs to another
        // course that embeds it as well (#588): the roster of one course must
        // not show the learners of the other.
        $privacythird = local_adele_fixture_store_path(
            'E2E Lernerdaten',
            $masteraccess,
            'fx_p_c',
            'Privacy Gamma',
            $fixturepassword
        );
        // Z: overall completion. Four paths, each with a learner of its own,
        // each embedded with the completion rule "learning path finished":
        // Z1 regular (linear), Z2 alternative route (the diamond of chain A),
        // Z3 manual completion on EVERY node, Z4 master completion (linear).
        $finalpaths['Z1'] = local_adele_fixture_store_path(
            'E2E Abschluss regulär',
            $masteraccess,
            'fx_z_regular',
            'Final regular',
            $fixturepassword
        );
        if (!empty($routepaths)) {
            $finalpaths['Z2'] = local_adele_fixture_store_path(
                'E2E Abschluss Route',
                $diamond,
                'fx_z_route',
                'Final route',
                $fixturepassword
            );
        }
        $manualall = $masteraccess;
        foreach ($manualall['tree']['nodes'] as $n => $node) {
            foreach ($node['completion']['nodes'] ?? [] as $c => $condition) {
                if (($condition['data']['label'] ?? '') === 'course_completed') {
                    $data = $condition['data'];
                    $data['label'] = 'manual';
                    $data['name'] = get_string('course_name_condition_completion_manual', 'local_adele');
                    $data['description'] = get_string('course_description_condition_completion_manual', 'local_adele');
                    $data['information'] = get_string('course_information_condition_completion_manual', 'local_adele');
                    $data['value'] = null;
                    $manualall['tree']['nodes'][$n]['completion']['nodes'][$c]['data'] = $data;
                }
            }
        }
        $finalpaths['Z3'] = local_adele_fixture_store_path(
            'E2E Abschluss manuell',
            $manualall,
            'fx_z_manual',
            'Final manual',
            $fixturepassword
        );
        $finalpaths['Z4'] = local_adele_fixture_store_path(
            'E2E Abschluss Master',
            $masteraccess,
            'fx_z_master',
            'Final master',
            $fixturepassword
        );
        $windowonly = $timedtemplate['data'];
        $windowonly['value'] = ['start' => LOCAL_ADELE_FIXTURE_WINDOW_START, 'end' => LOCAL_ADELE_FIXTURE_WINDOW_END];
        $masteraccesstimepath = local_adele_fixture_derived_path(
            'E2E Master-Zugang Zeit',
            $base,
            local_adele_fixture_single_condition($firsttemplate, $feedbacktemplate, $windowonly),
            'fx_o2_time',
            'Master access time',
            $fixturepassword
        );

        // M: changing a running path. Node 2 starts WITHOUT an access
        // condition - its learner is in T02 from the start; the chain adds a
        // condition through the editor (M1) and removes it again (M2).
        $running = $base;
        foreach ($running['tree']['nodes'] as $n => $node) {
            if ($node['id'] === 'dndnode_2') {
                $running['tree']['nodes'][$n]['restriction'] = ['nodes' => [], 'edges' => []];
            } else if ($node['id'] === 'dndnode_3') {
                $running['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_2'], 1);
            }
        }
        $runningpath = local_adele_fixture_store_path(
            'E2E Laufende Änderung',
            $running,
            'fx_m_running',
            'Running change',
            $fixturepassword
        );

        // T4/T5: predecessor AND / OR the fixed window. Node 2 of the linear
        // path; two learners per path, so both middle rows of the plan's
        // truth table are walked: one completes T01 first, one reaches the
        // window first.
        $windowdata = $timedtemplate['data'];
        // A window of their own, a year after T1/T2's: a clock jump runs every
        // task due by then, so two chains sharing one window would consume
        // each other's re-evaluations.
        $windowdata['value'] = ['start' => LOCAL_ADELE_FIXTURE_WINDOW2_START, 'end' => LOCAL_ADELE_FIXTURE_WINDOW2_END];
        $predecessordata = $firsttemplate['data'];
        $predecessordata['value'] = ['courses_id' => ['dndnode_1'], 'min_courses' => 1];
        foreach (['and' => 'E2E Zeit UND Vorgänger', 'or' => 'E2E Zeit ODER Vorgänger'] as $relation => $name) {
            $combined = $base;
            foreach ($combined['tree']['nodes'] as $n => $node) {
                if ($node['id'] === 'dndnode_2') {
                    $combined['tree']['nodes'][$n]['restriction'] = local_adele_fixture_two_conditions(
                        $firsttemplate,
                        $feedbacktemplate,
                        $predecessordata,
                        $windowdata,
                        $relation
                    );
                } else if ($node['id'] === 'dndnode_3') {
                    $combined['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_2'], 1);
                }
            }
            foreach (['first' => 'completes first', 'late' => 'window first'] as $who => $lastname) {
                $timecombined[$relation][$who] = local_adele_fixture_store_path(
                    $name,
                    $combined,
                    'fx_t_' . $relation . '_' . $who,
                    'Time ' . $relation . ' ' . $lastname,
                    $fixturepassword
                );
            }
        }
    }
}

// A teacher account for the chain about course roles, plus the setting that
// chain depends on: local_adele turns a configured COURSE role into the
// system role adeleassistant when it is assigned
// (enrollment::assign_assistant_to_role). Without the setting the mechanism
// is off and the chain would be testing nothing.
$teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
if ($teacherroleid) {
    set_config('enrollassistant', $teacherroleid, 'local_adele');
}
$teacher = $DB->get_record('user', ['username' => 'fx_teacher', 'deleted' => 0]);
if (!$teacher) {
    $teacher = (object) [
        'username' => 'fx_teacher',
        'firstname' => 'Fixture',
        'lastname' => 'Teacher',
        'email' => 'fx_teacher@example.invalid',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ];
    $teacher->id = user_create_user($teacher, false, false);
    $teacher = $DB->get_record('user', ['id' => $teacher->id]);
}
$DB->set_field('user', 'password', hash_internal_user_password($fixturepassword), ['id' => $teacher->id]);
// Arrives with nothing: the chain's first step is "this person has no editor
// access", and a leftover assignment from an earlier run would make that step
// pass or fail for reasons of its own.
$assistantroleid = $DB->get_field('role', 'id', ['shortname' => 'adeleassistant']);
if ($assistantroleid) {
    role_unassign((int) $assistantroleid, (int) $teacher->id, $systemcontext->id);
}
foreach (enrol_get_all_users_courses($teacher->id) as $enrolled) {
    foreach (enrol_get_instances($enrolled->id, true) as $instance) {
        $plugin = enrol_get_plugin($instance->enrol);
        if ($plugin && $plugin->allow_unenrol($instance)) {
            $plugin->unenrol_user($instance, $teacher->id);
        }
    }
}

// The assistant edits exactly ONE path, and the chains check both halves of
// that: the path they may edit and a path they may not. Assigned through the
// plugin's own API - an editor row written by hand could differ from what the
// plugin creates, and the chain would then prove nothing about production.
$editorpath = $imported['Linear A2'] ?? null;
if ($editorpath && !empty($roleusers['adeleassistant'])) {
    foreach ($imported as $pathid) {
        // Start from a known state: ANY leftover editor row - from a chain
        // that granted a right and could not take it back, or from an
        // earlier run - would make "may not edit" pass or fail for the wrong
        // reason. The fixture paths get exactly the editors set below.
        $DB->delete_records('local_adele_lp_editors', ['learningpathid' => $pathid]);
    }
    \local_adele\learning_path_editors::create_editors(
        $editorpath,
        (int) $roleusers['adeleassistant']->id
    );
    // The owner always stands on the list of editors (decision 2026-10-08):
    // the fixture paths belong to the administrator, who is put back on each
    // list - the same repair the upgrade step does.
    \local_adele\ownership::ensure_owners_are_editors();
}

// Reset what a previous run of the embedding chains left behind. Deleting an
// ADELE activity only QUEUES the withdrawal of the enrolments it caused, and
// that task is scheduled five minutes out, so without this a second run
// starts with members who still have access - and the chain's precondition
// ("nobody has been carried anywhere yet") fails for a reason that has
// nothing to do with the behaviour under test.
foreach ($DB->get_records('adele', ['course' => $hostcourse->id]) as $activity) {
    $cm = get_coursemodule_from_instance('adele', $activity->id, $hostcourse->id);
    if ($cm) {
        course_delete_module($cm->id);
    }
}
// Only the host course's own people are reset here: the per-path learners
// were subscribed a few lines above, and wiping them would undo the fixture
// that was just built. The host members must arrive with nothing - that is
// what makes "before" and "after" mean something in the embedding chains.
$hostpeople = array_slice($learners, $position, 3);
foreach ($imported as $pathid) {
    foreach ($hostpeople as $person) {
        $DB->delete_records('local_adele_path_user', [
            'learning_path_id' => $pathid,
            'user_id' => $person->id,
        ]);
        if (class_exists('\\enrol_adele\\local\\reconciler')) {
            // Definitive removal, the way enrol_adele itself does it: both
            // the node-course enrolments and the host-course ones. Suspending
            // would leave a record behind that still reads as "carried".
            \enrol_adele\local\reconciler::purge_user($pathid, $person->id);
            \enrol_adele\local\reconciler::purge_all_host_user($pathid, $person->id);
        }
    }
}

// Further host courses, deliberately EMPTY, one per scenario of plan
// section 16: every scenario embeds the path into a course of its own, so
// the effects of one participant source or host mode cannot leak into
// another's assertions.
// E2EHOST2: source "starting node"            (H2)
// E2EHOST3: source "any course of the path"   (H2)
// E2EHOST4: source "starting node", mode hidden  (H3)
// E2EHOST5: source "starting node", mode none    (H3)
// E2EHOST6: sources "this course" + "starting node" combined (H2).
// E2EHOST7: source "any course of the path", mode hidden (H3).
// E2EHOST8: source "any course of the path", mode none   (H3).
// E2EHOST9: two embeddings - another path for "this course", Linear A1 for
// "starting node": the host enrolment ADELE creates for the second must not
// subscribe anybody to the first (H2, #37).
// E2EHOST10: "starting node", mode changed afterwards visible -> hidden ->
// none -> visible (H3, addendum of 2026-10-13).
// E2EHOST11: the same for "any course of the path".
// E2EHOST12: sources "this course" + "starting node"; "this course" removed
// afterwards and restored (H2, addendum of 2026-10-13).
// E2EHOST13: "this course"; the embedding is deleted and restored from the
// recycle bin (H1, decision of 2026-10-15: deleted is cleanly gone).
$extrahosts = [];
foreach ([2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13] as $number) {
    $shortname = 'E2EHOST' . $number;
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        $course = create_course((object) [
            'shortname' => $shortname,
            'fullname' => 'E2E host course ' . $number,
            'category' => (int) $options['category'],
            'summary' => 'Host course for one embedding scenario. Created by seed_fixtures.php.',
            'summaryformat' => FORMAT_HTML,
        ]);
    }
    foreach ($DB->get_records('adele', ['course' => $course->id]) as $activity) {
        $cm = get_coursemodule_from_instance('adele', $activity->id, $course->id);
        if ($cm) {
            course_delete_module($cm->id);
        }
    }
    $extrahosts[$number] = $course;
}
$hostcourse2 = $extrahosts[2];

$studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
$manual = enrol_get_plugin('manual');
$manualinstance = $DB->get_record('enrol', [
    'courseid' => $hostcourse->id,
    'enrol' => 'manual',
], '*', IGNORE_MULTIPLE);
foreach ($hostmembers as $member) {
    if ($manual && $manualinstance && $studentroleid) {
        $manual->enrol_user($manualinstance, $member->id, $studentroleid);
    }
}

// A learner who is ONLY in a later node's course of the reference path (T03,
// node 3), in no host course and on no path: the third participant source
// ("any course of the learning path") is about exactly this person - and the
// second source ("starting node") must NOT reach them.
$nodelearner = $learners[$position + 4] ?? null;
if ($nodelearner) {
    $nodecourse = $DB->get_record('course', ['shortname' => 'T03']);
    if ($nodecourse) {
        $nodeinstance = $DB->get_record('enrol', [
            'courseid' => $nodecourse->id,
            'enrol' => 'manual',
        ], '*', IGNORE_MULTIPLE);
        if ($manual && $nodeinstance && $studentroleid) {
            $manual->enrol_user($nodeinstance, $nodelearner->id, $studentroleid);
        }
    }
    foreach ($imported as $pathid) {
        $DB->delete_records('local_adele_path_user', [
            'learning_path_id' => $pathid,
            'user_id' => $nodelearner->id,
        ]);
        if (class_exists('\\enrol_adele\\local\\reconciler')) {
            \enrol_adele\local\reconciler::purge_user($pathid, $nodelearner->id);
            \enrol_adele\local\reconciler::purge_all_host_user($pathid, $nodelearner->id);
        }
    }
}

// The combined-sources scenario (E2EHOST6) needs one member of its own.
$combomember = reset($hostmembers);
if ($combomember && $manual && $studentroleid) {
    $comboinstance = $DB->get_record('enrol', [
        'courseid' => $extrahosts[6]->id,
        'enrol' => 'manual',
    ], '*', IGNORE_MULTIPLE);
    if ($comboinstance) {
        $manual->enrol_user($comboinstance, $combomember->id, $studentroleid);
    }
}

// A learner who is in the ENTRY COURSE of the reference path but in no host
// course and on no path: the second participant source ("people enrolled in
// a starting node") is about exactly this person, and the chain needs them to
// arrive with nothing else attached.
$entrylearner = $learners[$position + 3] ?? null;
if ($entrylearner) {
    $entrycourse = $DB->get_record('course', ['shortname' => 'T01']);
    if ($entrycourse) {
        $entryinstance = $DB->get_record('enrol', [
            'courseid' => $entrycourse->id,
            'enrol' => 'manual',
        ], '*', IGNORE_MULTIPLE);
        if ($manual && $entryinstance && $studentroleid) {
            $manual->enrol_user($entryinstance, $entrylearner->id, $studentroleid);
        }
    }
    foreach ($imported as $pathid) {
        $DB->delete_records('local_adele_path_user', [
            'learning_path_id' => $pathid,
            'user_id' => $entrylearner->id,
        ]);
        if (class_exists('\\enrol_adele\\local\\reconciler')) {
            \enrol_adele\local\reconciler::purge_user($pathid, $entrylearner->id);
            \enrol_adele\local\reconciler::purge_all_host_user($pathid, $entrylearner->id);
        }
    }
}

// The addendum of 2026-10-13 changes embeddings AFTER they took effect, and
// needs two people of its own, back to the start on every run:
// - "dual": manually in the entry course T01 AND manually in E2EHOST10 and
// E2EHOST11 - an enrolment of their own that a mode change of ADELE's must
// leave alone (H3);
// - "source": manually in E2EHOST12 only - on the path only through the
// source "this course", which the chain removes (H2);
// - "delete": manually in E2EHOST13 only - on the path only through the
// embedding the chain deletes (H1).
// Both complete T01 in the chain, so their completions in the fixture courses
// are forgotten here: a new subscription reads progress from the courses.
$changepeople = [
    'DUAL' => [local_adele_fixture_person('fx_h_dual', 'Host Dual', $fixturepassword), ['T01', 'E2EHOST10', 'E2EHOST11']],
    'SOURCE' => [local_adele_fixture_person('fx_h_source', 'Host Source', $fixturepassword), ['E2EHOST12']],
    'DELETE' => [local_adele_fixture_person('fx_h_delete', 'Host Delete', $fixturepassword), ['E2EHOST13']],
];
$changecourseids = $DB->get_fieldset_select('course', 'id', $DB->sql_like('shortname', ':t'), ['t' => 'T__']);
foreach ($changepeople as [$changeperson, $changeshortnames]) {
    foreach ($changeshortnames as $changeshortname) {
        $changecourse = $DB->get_record('course', ['shortname' => $changeshortname]);
        $changeinstance = $changecourse ? $DB->get_record('enrol', [
            'courseid' => $changecourse->id,
            'enrol' => 'manual',
        ], '*', IGNORE_MULTIPLE) : null;
        if ($manual && $changeinstance && $studentroleid) {
            $manual->enrol_user($changeinstance, $changeperson->id, $studentroleid, 0, 0, ENROL_USER_ACTIVE);
        }
    }
    foreach ($changecourseids as $changecourseid) {
        $changecmids = $DB->get_fieldset_select('course_modules', 'id', 'course = ?', [$changecourseid]);
        if ($changecmids) {
            [$insql, $inparams] = $DB->get_in_or_equal($changecmids);
            $DB->delete_records_select(
                'course_modules_completion',
                "userid = ? AND coursemoduleid {$insql}",
                array_merge([$changeperson->id], $inparams)
            );
        }
        $DB->delete_records('course_completion_crit_compl', ['userid' => $changeperson->id, 'course' => $changecourseid]);
        $DB->delete_records('course_completions', ['userid' => $changeperson->id, 'course' => $changecourseid]);
    }
    foreach ($imported as $pathid) {
        $DB->delete_records('local_adele_path_user', [
            'learning_path_id' => $pathid,
            'user_id' => $changeperson->id,
        ]);
        if (class_exists('\\enrol_adele\\local\\reconciler')) {
            \enrol_adele\local\reconciler::purge_user($pathid, $changeperson->id);
            \enrol_adele\local\reconciler::purge_all_host_user($pathid, $changeperson->id);
        }
    }
}
\cache::make('core', 'completion')->purge();
\cache::make('core', 'coursecompletion')->purge();

printf("export ADELE_BASE_URL='%s'\n", $CFG->wwwroot);
printf("export ADELE_ADMIN_USER='%s'\n", $admin->username);
printf("export ADELE_ADMIN_PASSWORD='%s'\n", $adminpassword);
printf("export ADELE_FIXTURE_PASSWORD='%s'\n", $fixturepassword);
if ($control) {
    printf("export ADELE_FIXTURE_CONTROL_USER='%s'\n", $control->username);
}
// Moodle's root, so a spec can drain the ad-hoc queue instead of waiting for
// cron. Waiting proves nothing about whether the task ever ran.
printf("export ADELE_MOODLE_ROOT='%s'\n", $CFG->dirroot);
if ($entrylearner) {
    printf("export ADELE_FIXTURE_ENTRY_LEARNER='%s'\n", $entrylearner->username);
    printf("export ADELE_FIXTURE_ENTRY_LEARNER_NAME='%s'\n", fullname($entrylearner));
}
// D: diagnosis through a CAT result (plan section 13), only where the catquiz
// ecosystem is installed. A real adaptive test in T01 and a real catquiz
// scale; node 1 of the reference path completes when the person ability on
// that scale reaches the threshold ("ability >= threshold"). Three learners
// for x - epsilon, x and x + epsilon; the signals themselves are created by
// tests/e2e/support/catquiz_signal.php at test time.
$diagnosis = null;
if (
    class_exists('\local_catquiz\catquiz')
    && $DB->get_manager()->table_exists('adaptivequiz')
    && !empty($imported['Linear A1'])
) {
    require_once($CFG->libdir . '/testing/generator/lib.php');
    \core\session\manager::set_user(get_admin());
    $t01 = $DB->get_record('course', ['shortname' => 'T01'], '*', MUST_EXIST);
    $quizname = 'E2E Diagnose CAT';
    $quiz = $DB->get_record('adaptivequiz', ['course' => $t01->id, 'name' => $quizname]);
    if (!$quiz) {
        $quiz = (new testing_data_generator())->get_plugin_generator('mod_adaptivequiz')
            ->create_instance(['course' => $t01->id, 'name' => $quizname]);
    }
    $quizcm = get_coursemodule_from_instance('adaptivequiz', $quiz->id, $t01->id, false, MUST_EXIST);
    $scale = $DB->get_record('local_catquiz_catscales', ['name' => 'E2E Diagnose-Skala', 'parentid' => 0]);
    $scaleid = $scale ? (int) $scale->id : (int) $DB->insert_record('local_catquiz_catscales', (object) [
        'name' => 'E2E Diagnose-Skala',
        'description' => 'Scale for the E2E diagnosis chain. Created by seed_fixtures.php.',
        'parentid' => 0,
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
    $threshold = 2.5;
    $diagbase = json_decode($DB->get_field('local_adele_learning_paths', 'json', ['id' => $imported['Linear A1']]), true);
    foreach ($diagbase['tree']['nodes'] as $n => $node) {
        if ($node['id'] === 'dndnode_1') {
            foreach ($diagbase['tree']['nodes'][$n]['completion']['nodes'] as $c => $condition) {
                if (($condition['data']['label'] ?? '') === 'course_completed') {
                    $diagbase['tree']['nodes'][$n]['completion']['nodes'][$c]['data']['label'] = 'catquiz';
                    $diagbase['tree']['nodes'][$n]['completion']['nodes'][$c]['data']['value'] = [
                        'testid' => (int) $quiz->id,
                        'testid_courseid' => (int) $t01->id,
                        'componentid' => (int) $quiz->id,
                        'scales' => [
                            'parent' => [
                                'id' => (string) $scaleid,
                                'scale' => $threshold,
                                'name' => 'E2E Diagnose-Skala',
                                'attempts' => '',
                            ],
                            'sub' => [],
                        ],
                    ];
                }
            }
        } else if ($node['id'] === 'dndnode_2') {
            $diagbase['tree']['nodes'][$n]['restriction'] = $parentcriterion(['dndnode_1'], 1);
        }
    }
    $diagnosis = ['cmid' => (int) $quizcm->id, 'scale' => $scaleid, 'threshold' => $threshold, 'learners' => []];
    foreach (['below', 'at', 'above'] as $variant) {
        [$pathid, $learner] = local_adele_fixture_store_path(
            'E2E Diagnose',
            $diagbase,
            'fx_d_' . $variant,
            'Diagnosis ' . $variant,
            $fixturepassword
        );
        // A clean slate for the signals of earlier runs.
        $DB->delete_records('local_catquiz_attempts', ['userid' => $learner->id, 'instanceid' => $quiz->id]);
        $DB->delete_records('adaptivequiz_attempt', ['userid' => $learner->id, 'instance' => $quiz->id]);
        $host = local_adele_fixture_host_course(
            'E2ED' . strtoupper($variant),
            'E2E D ' . $variant,
            $pathid,
            $learner,
            (int) $options['category']
        );
        $diagnosis['learners'][$variant] = [$learner, $host];
    }
}

// Each C path embedded into a host course of its own, with its learner as
// the only member: the learner needs the path view to show node states, and
// one shared host course would subscribe every member to every path.
\core\session\manager::set_user(get_admin());
$c1hosts = [];
$c1negatives = [];
foreach ($c1paths as $variant => [$pathid, $learner]) {
    $c1negatives[$variant] = local_adele_fixture_negative_person(
        'fx_c1_' . $variant . '_neg',
        'Negative ' . $variant,
        $fixturepassword,
        $pathid
    );
    $c1hosts[$variant] = local_adele_fixture_host_course(
        'E2EC1' . strtoupper($variant),
        'E2E C1 ' . $variant,
        $pathid,
        $learner,
        (int) $options['category'],
        1,
        false,
        [$c1negatives[$variant]]
    );
}
$routehosts = [];
foreach ($routepaths as $variant => [$pathid, $learner]) {
    $routehosts[$variant] = local_adele_fixture_host_course(
        'E2EA' . strtoupper($variant),
        'E2E A ' . $variant,
        $pathid,
        $learner,
        (int) $options['category']
    );
}
$linear4host = $linear4path ? local_adele_fixture_host_course(
    'E2EL1',
    'E2E L linear',
    $linear4path[0],
    $linear4path[1],
    (int) $options['category']
) : null;
$manualcompletionhost = $manualcompletionpath ? local_adele_fixture_host_course(
    'E2EL3',
    'E2E L manual',
    $manualcompletionpath[0],
    $manualcompletionpath[1],
    (int) $options['category']
) : null;
$mastercompletionhost = $mastercompletionpath ? local_adele_fixture_host_course(
    'E2EL4',
    'E2E L master',
    $mastercompletionpath[0],
    $mastercompletionpath[1],
    (int) $options['category']
) : null;
$masteraccesshosts = [];
foreach (['O2' => $masteraccesspath, 'O2T' => $masteraccesstimepath, 'O4' => $masterbothpath] as $key => $entry) {
    if ($entry) {
        $masteraccesshosts[$key] = [local_adele_fixture_host_course(
            'E2E' . $key,
            'E2E ' . $key . ' master access',
            $entry[0],
            $entry[1],
            (int) $options['category']
        ), $entry[1]];
    }
}
$runninghost = $runningpath ? local_adele_fixture_host_course(
    'E2EM',
    'E2E M running',
    $runningpath[0],
    $runningpath[1],
    (int) $options['category']
) : null;
$c3host = $c3path ? local_adele_fixture_host_course(
    'E2EC3',
    'E2E C3 manual',
    $c3path[0],
    $c3path[1],
    (int) $options['category']
) : null;

// P: the path embedded TWICE into one course - once with "Only own results",
// once with "Results of all other participants" - and the people the chain
// asks about: a teacher with and one without editing rights, a collaborator of
// exactly this path who sits in the course as a participant, and two people
// of ANOTHER course that embeds no path at all - a participant and a teacher -
// who have nothing to do with the path.
$privacy = null;
if ($privacypath && $privacysecond) {
    [$privacyid, $privacya] = $privacypath;
    $privacyb = $privacysecond[1];
    $privacyhost = local_adele_fixture_host_course(
        'E2EP',
        'E2E P own results',
        $privacyid,
        $privacya,
        (int) $options['category'],
        2
    );
    $privacycms = [
        'OWN' => (int) get_coursemodule_from_instance(
            'adele',
            $DB->get_field('adele', 'id', ['course' => $privacyhost->id, 'name' => 'E2E P own results']),
            $privacyhost->id
        )->id,
        'ALL' => local_adele_fixture_embed($privacyhost, 'E2E P all results', $privacyid, 1),
        // Starts as "all results"; the chain turns it to "only own results"
        // and back (P1/P2, addendum of 2026-10-13). An embedding of its own,
        // so OWN and ALL keep meaning what P1-P4 assume.
        'SWITCHABLE' => local_adele_fixture_embed($privacyhost, 'E2E P switchable results', $privacyid, 1),
    ];
    $privacyother = $DB->get_record('course', ['shortname' => 'E2EPX']);
    if (!$privacyother) {
        $privacyother = create_course((object) [
            'shortname' => 'E2EPX',
            'fullname' => 'E2E course without a learning path',
            'category' => (int) $options['category'],
            'summary' => 'Embeds no learning path. Created by seed_fixtures.php.',
            'summaryformat' => FORMAT_HTML,
        ]);
    }
    $privacypeople = [
        'TEACHER' => local_adele_fixture_person('fx_p_teacher', 'Privacy Teacher', $fixturepassword),
        'TUTOR' => local_adele_fixture_person('fx_p_tutor', 'Privacy Tutor', $fixturepassword),
        'COLLABORATOR' => local_adele_fixture_person('fx_p_collab', 'Privacy Collaborator', $fixturepassword),
        'OUTSIDER' => local_adele_fixture_person('fx_p_out', 'Privacy Outsider', $fixturepassword),
        'FOREIGN' => local_adele_fixture_person('fx_p_foreign', 'Privacy Foreign', $fixturepassword),
        'SWITCH' => local_adele_fixture_person('fx_p_switch', 'Privacy Switch', $fixturepassword),
    ];
    $privacymanual = enrol_get_plugin('manual');
    $privacyenrol = static function (stdClass $course, stdClass $person, string $role) use ($DB, $privacymanual): void {
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        $roleid = $DB->get_field('role', 'id', ['shortname' => $role]);
        if ($privacymanual && $instance && $roleid) {
            $privacymanual->enrol_user($instance, $person->id, $roleid);
        }
    };
    $privacyenrol($privacyhost, $privacyb, 'student');
    $privacyenrol($privacyhost, $privacypeople['TEACHER'], 'editingteacher');
    $privacyenrol($privacyhost, $privacypeople['TUTOR'], 'teacher');
    $privacyenrol($privacyhost, $privacypeople['COLLABORATOR'], 'student');
    // Somebody who starts as a teacher of the course (without editing
    // rights) and changes sides in the chain (#587). Back to the start on
    // every run: only the teaching role, no learner path.
    $privacyenrol($privacyhost, $privacypeople['SWITCH'], 'teacher');
    role_unassign(
        (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
        (int) $privacypeople['SWITCH']->id,
        context_course::instance($privacyhost->id)->id
    );
    $DB->delete_records('local_adele_path_user', [
        'learning_path_id' => $privacyid,
        'user_id' => $privacypeople['SWITCH']->id,
    ]);
    if (class_exists('\\enrol_adele\\local\\reconciler')) {
        \enrol_adele\local\reconciler::purge_user($privacyid, (int) $privacypeople['SWITCH']->id);
    }
    $privacyenrol($privacyother, $privacypeople['OUTSIDER'], 'student');
    // A teacher - of the OTHER course only. Without editing rights: an
    // editing teacher would become an ADELE assistant (enrollassistant).
    $privacyenrol($privacyother, $privacypeople['FOREIGN'], 'teacher');
    \local_adele\learning_path_editors::create_editors($privacyid, (int) $privacypeople['COLLABORATOR']->id);
    // The same path in a second course, with a learner of its own.
    if ($privacythird) {
        $privacysecondhost = local_adele_fixture_host_course(
            'E2EP2',
            'E2E P second course',
            $privacyid,
            $privacythird[1],
            (int) $options['category'],
            1
        );
        $privacypeople['C'] = $privacythird[1];
        $privacycms['SECOND'] = (int) get_coursemodule_from_instance(
            'adele',
            $DB->get_field('adele', 'id', ['course' => $privacysecondhost->id, 'name' => 'E2E P second course']),
            $privacysecondhost->id
        )->id;
    }
    $privacy = [
        'path' => $privacyid,
        'host' => $privacyhost,
        'other' => $privacyother,
        'cms' => $privacycms,
        'people' => ['A' => $privacya, 'B' => $privacyb] + $privacypeople,
    ];
}

// Z: each path in a host course of its own, the activity completing when the
// learning path is finished.
foreach ($finalpaths as $key => [$pathid, $learner]) {
    $finalhost = local_adele_fixture_host_course(
        'E2E' . $key,
        'E2E ' . $key . ' final',
        $pathid,
        $learner,
        (int) $options['category'],
        1,
        true
    );
    printf("export ADELE_FIXTURE_%s_LEARNER='%s'\n", $key, $learner->username);
    printf("export ADELE_FIXTURE_%s_HOST='%d'\n", $key, $finalhost->id);
}

// F: feedback texts. The conditions other chains test for their EFFECT, each
// with a learner and a host course of its own, so that this chain can walk
// the states and read the texts without disturbing anybody: predecessor
// (1 of 1, 2 of 2), manual release, manual completion, a fixed time window,
// window AND / OR predecessor - and one learner whose language is German.
// The time fixtures get paths and windows of their own (2032, 2033), later
// than every window of chain T: moving the clock runs every task due by then,
// for every learner on a path - shared windows would consume each other's
// re-evaluations. A window may be open at one end (null).
// CUSTOM: node 2 carries a text the author wrote instead of the default.
// [source definition, name of the own path or null, window or null].
/** @var int Start of chain F's first window: 2032-01-15 10:00:00 UTC. */
const LOCAL_ADELE_FIXTURE_F_WINDOW3_START = 1957773600;
/** @var int End of chain F's first window: 2032-01-15 12:00:00 UTC. */
const LOCAL_ADELE_FIXTURE_F_WINDOW3_END = 1957780800;
/** @var int Start of chain F's second window: 2033-01-15 10:00:00 UTC. */
const LOCAL_ADELE_FIXTURE_F_WINDOW4_START = 1989396000;
/** @var int End of chain F's second window: 2033-01-15 12:00:00 UTC. */
const LOCAL_ADELE_FIXTURE_F_WINDOW4_END = 1989403200;
/** @var string The author's own text on node 2 of the CUSTOM fixtures. */
const LOCAL_ADELE_FIXTURE_F_CUSTOM_TEXT = 'E2E author text: finish Testkurs 01 first';
$feedbackwindow3 = [LOCAL_ADELE_FIXTURE_F_WINDOW3_START, LOCAL_ADELE_FIXTURE_F_WINDOW3_END];
$feedbackwindow4 = [LOCAL_ADELE_FIXTURE_F_WINDOW4_START, LOCAL_ADELE_FIXTURE_F_WINDOW4_END];
$feedbackfixtures = [
    'LINEAR' => ['E2E Vorgänger 1 von 1', null, null],
    'ALL' => ['E2E Vorgänger 2 von 2', null, null],
    'ACCESS' => ['E2E Manuelle Freigabe', null, null],
    'MANUAL' => ['E2E Abschluss manuell', null, null],
    'TIME' => ['E2E Master-Zugang Zeit', 'E2E F Zeitfenster', $feedbackwindow3],
    // Same window, a learner on the other side of the world: UTC+9.
    'TIME_TOKYO' => ['E2E Master-Zugang Zeit', 'E2E F Zeitfenster', $feedbackwindow3],
    'TIME_START' => ['E2E Master-Zugang Zeit', 'E2E F Nur Beginn', [LOCAL_ADELE_FIXTURE_F_WINDOW3_START, null]],
    'TIME_END' => ['E2E Master-Zugang Zeit', 'E2E F Nur Ende', [null, LOCAL_ADELE_FIXTURE_F_WINDOW3_END]],
    'AND' => ['E2E Zeit UND Vorgänger', 'E2E F Zeit UND Vorgänger', $feedbackwindow4],
    'AND_LATE' => ['E2E Zeit UND Vorgänger', 'E2E F Zeit UND Vorgänger', $feedbackwindow4],
    'OR' => ['E2E Zeit ODER Vorgänger', 'E2E F Zeit ODER Vorgänger', $feedbackwindow4],
    'CUSTOM' => ['E2E Vorgänger 1 von 1', 'E2E F Eigener Text', null],
    'CUSTOM_DE' => ['E2E Vorgänger 1 von 1', 'E2E F Eigener Text', null],
    'DE' => ['E2E Vorgänger 1 von 1', null, null],
];
// The list of installed languages is cached; a pack installed a moment ago
// by another process is not on it yet.
get_string_manager()->reset_caches();
$german = get_string_manager()->translation_exists('de', false);
if (!$german) {
    // The language pack, the way an administrator installs it. Without
    // network access the German part of the chain is skipped, not faked.
    try {
        (new \tool_langimport\controller())->install_languagepacks('de');
        get_string_manager()->reset_caches();
        $german = get_string_manager()->translation_exists('de', false);
    } catch (\Throwable $e) {
        $german = false;
    }
}
foreach ($feedbackfixtures as $key => [$sourcename, $ownname, $window]) {
    $definition = $GLOBALS['local_adele_fixture_definitions'][$sourcename] ?? null;
    $isgerman = substr($key, -2) === 'DE';
    if (!$definition || ($isgerman && !$german)) {
        continue;
    }
    foreach ($definition['tree']['nodes'] as $n => $treenode) {
        foreach ($treenode['restriction']['nodes'] ?? [] as $c => $condition) {
            if ($window && ($condition['data']['label'] ?? '') === 'timed') {
                $definition['tree']['nodes'][$n]['restriction']['nodes'][$c]['data']['value'] =
                    ['start' => $window[0], 'end' => $window[1]];
            }
            if (
                strpos($key, 'CUSTOM') === 0 && $treenode['id'] === 'dndnode_2' &&
                ($condition['type'] ?? '') === 'feedback'
            ) {
                // What the editor stores when the author unticks "use
                // default" and writes a text of their own.
                $definition['tree']['nodes'][$n]['restriction']['nodes'][$c]['data']['feedback_before_checkmark'] = false;
                $definition['tree']['nodes'][$n]['restriction']['nodes'][$c]['data']['feedback_before'] =
                    LOCAL_ADELE_FIXTURE_F_CUSTOM_TEXT;
            }
        }
    }
    $feedbacklabel = strtolower(str_replace('_', ' ', $key));
    // Language and time zone BEFORE the subscription: the first texts are
    // rendered there. An explicit zone, so that the dates the chain reads do
    // not depend on the site's default.
    $feedbackperson = local_adele_fixture_person('fx_f_' . strtolower($key), 'Feedback ' . $feedbacklabel, $fixturepassword);
    $DB->set_field('user', 'lang', $isgerman ? 'de' : 'en', ['id' => $feedbackperson->id]);
    $DB->set_field('user', 'timezone', $key === 'TIME_TOKYO' ? 'Asia/Tokyo' : 'UTC', ['id' => $feedbackperson->id]);
    [$feedbackpathid, $feedbacklearner] = local_adele_fixture_store_path(
        $ownname ?? $sourcename,
        $definition,
        'fx_f_' . strtolower($key),
        'Feedback ' . $feedbacklabel,
        $fixturepassword
    );
    // Named after the key, not numbered: a numbered course would pass to
    // another fixture - with its members - whenever the list changes.
    $feedbackhost = local_adele_fixture_host_course(
        'E2EF_' . $key,
        'E2E F ' . $feedbacklabel,
        $feedbackpathid,
        $feedbacklearner,
        (int) $options['category']
    );
    printf("export ADELE_FIXTURE_F_%s_LEARNER='%s'\n", $key, $feedbacklearner->username);
    printf("export ADELE_FIXTURE_F_%s_HOST='%d'\n", $key, $feedbackhost->id);
}
printf("export ADELE_FIXTURE_F_WINDOW3_START='%d'\n", LOCAL_ADELE_FIXTURE_F_WINDOW3_START);
printf("export ADELE_FIXTURE_F_WINDOW3_END='%d'\n", LOCAL_ADELE_FIXTURE_F_WINDOW3_END);
printf("export ADELE_FIXTURE_F_WINDOW4_START='%d'\n", LOCAL_ADELE_FIXTURE_F_WINDOW4_START);
printf("export ADELE_FIXTURE_F_WINDOW4_END='%d'\n", LOCAL_ADELE_FIXTURE_F_WINDOW4_END);
printf("export ADELE_FIXTURE_F_CUSTOM_TEXT='%s'\n", LOCAL_ADELE_FIXTURE_F_CUSTOM_TEXT);

if ($privacy) {
    printf("export ADELE_FIXTURE_PATH_P='%d'\n", $privacy['path']);
    printf("export ADELE_FIXTURE_P_HOST='%d'\n", $privacy['host']->id);
    printf("export ADELE_FIXTURE_P_HOST_CONTEXT='%d'\n", context_course::instance($privacy['host']->id)->id);
    printf("export ADELE_FIXTURE_P_OTHER='%d'\n", $privacy['other']->id);
    printf("export ADELE_FIXTURE_P_OTHER_CONTEXT='%d'\n", context_course::instance($privacy['other']->id)->id);
    // A node course of the path: its learners are enrolled there by ADELE,
    // and it embeds nothing.
    $privacynode = $DB->get_field('course', 'id', ['shortname' => 'T01']);
    if ($privacynode) {
        printf("export ADELE_FIXTURE_P_NODE_CONTEXT='%d'\n", context_course::instance($privacynode)->id);
    }
    foreach ($privacy['cms'] as $key => $cmid) {
        printf("export ADELE_FIXTURE_P_CM_%s='%d'\n", $key, $cmid);
        printf("export ADELE_FIXTURE_P_CM_%s_CONTEXT='%d'\n", $key, context_module::instance($cmid)->id);
    }
    foreach ($privacy['people'] as $key => $person) {
        printf("export ADELE_FIXTURE_P_%s='%s'\n", $key, $person->username);
        printf("export ADELE_FIXTURE_P_%s_ID='%d'\n", $key, $person->id);
    }
}
foreach ($c1paths as $variant => [$pathid, $learner]) {
    $key = strtoupper($variant);
    printf("export ADELE_FIXTURE_PATH_C1_%s='%d'\n", $key, $pathid);
    printf("export ADELE_FIXTURE_C1_%s_LEARNER='%s'\n", $key, $learner->username);
    printf("export ADELE_FIXTURE_C1_%s_HOST='%d'\n", $key, $c1hosts[$variant]->id);
    printf("export ADELE_FIXTURE_C1_%s_NEGATIVE='%s'\n", $key, $c1negatives[$variant]->username);
}
foreach ($routepaths as $variant => [$pathid, $learner]) {
    $key = strtoupper($variant);
    printf("export ADELE_FIXTURE_A_%s_LEARNER='%s'\n", $key, $learner->username);
    printf("export ADELE_FIXTURE_A_%s_LEARNER_NAME='%s'\n", $key, fullname($learner));
    printf("export ADELE_FIXTURE_A_%s_HOST='%d'\n", $key, $routehosts[$variant]->id);
}
if ($diagnosis) {
    printf("export ADELE_FIXTURE_D_CMID='%d'\n", $diagnosis['cmid']);
    printf("export ADELE_FIXTURE_D_SCALE='%d'\n", $diagnosis['scale']);
    printf("export ADELE_FIXTURE_D_THRESHOLD='%s'\n", $diagnosis['threshold']);
    foreach ($diagnosis['learners'] as $variant => [$learner, $host]) {
        $key = strtoupper($variant);
        printf("export ADELE_FIXTURE_D_%s_LEARNER='%s'\n", $key, $learner->username);
        printf("export ADELE_FIXTURE_D_%s_LEARNER_NAME='%s'\n", $key, fullname($learner));
        printf("export ADELE_FIXTURE_D_%s_HOST='%d'\n", $key, $host->id);
    }
}
if ($linear4path && $linear4host) {
    printf("export ADELE_FIXTURE_L1_LEARNER='%s'\n", $linear4path[1]->username);
    printf("export ADELE_FIXTURE_L1_HOST='%d'\n", $linear4host->id);
}
if ($manualcompletionpath && $manualcompletionhost) {
    printf("export ADELE_FIXTURE_L3_LEARNER='%s'\n", $manualcompletionpath[1]->username);
    printf("export ADELE_FIXTURE_L3_LEARNER_NAME='%s'\n", fullname($manualcompletionpath[1]));
    printf("export ADELE_FIXTURE_L3_HOST='%d'\n", $manualcompletionhost->id);
}
if ($mastercompletionpath && $mastercompletionhost) {
    printf("export ADELE_FIXTURE_L4_LEARNER='%s'\n", $mastercompletionpath[1]->username);
    printf("export ADELE_FIXTURE_L4_HOST='%d'\n", $mastercompletionhost->id);
}
foreach ($masteraccesshosts as $key => [$host, $learner]) {
    printf("export ADELE_FIXTURE_%s_LEARNER='%s'\n", $key, $learner->username);
    printf("export ADELE_FIXTURE_%s_HOST='%d'\n", $key, $host->id);
}
if ($runningpath && $runninghost) {
    printf("export ADELE_FIXTURE_PATH_M='%d'\n", $runningpath[0]);
    printf("export ADELE_FIXTURE_M_LEARNER='%s'\n", $runningpath[1]->username);
    printf("export ADELE_FIXTURE_M_HOST='%d'\n", $runninghost->id);
}
if ($c3path && $c3host) {
    printf("export ADELE_FIXTURE_PATH_C3='%d'\n", $c3path[0]);
    printf("export ADELE_FIXTURE_C3_LEARNER='%s'\n", $c3path[1]->username);
    printf("export ADELE_FIXTURE_C3_LEARNER_NAME='%s'\n", fullname($c3path[1]));
    printf("export ADELE_FIXTURE_C3_HOST='%d'\n", $c3host->id);
}
foreach ($timecombined as $relation => $learners) {
    foreach ($learners as $who => [$pathid, $learner]) {
        printf("export ADELE_FIXTURE_T_%s_%s='%s'\n", strtoupper($relation), strtoupper($who), $learner->username);
    }
}
if ($durationpath && $durationlearner) {
    printf("export ADELE_FIXTURE_PATH_DURATION='%d'\n", $durationpath);
    printf("export ADELE_FIXTURE_DURATION_LEARNER='%s'\n", $durationlearner->username);
    printf("export ADELE_FIXTURE_DURATION_FROM='%d'\n", $durationcreated);
}
if ($timepath && $timelearner) {
    printf("export ADELE_FIXTURE_PATH_TIMEWINDOW='%d'\n", $timepath);
    printf("export ADELE_FIXTURE_TIME_LEARNER='%s'\n", $timelearner->username);
    printf("export ADELE_FIXTURE_TIMEWINDOW_START='%d'\n", LOCAL_ADELE_FIXTURE_WINDOW_START);
    printf("export ADELE_FIXTURE_TIMEWINDOW_END='%d'\n", LOCAL_ADELE_FIXTURE_WINDOW_END);
    printf("export ADELE_FIXTURE_TIMEWINDOW2_START='%d'\n", LOCAL_ADELE_FIXTURE_WINDOW2_START);
}
printf("export ADELE_FIXTURE_TEACHER='%s'\n", $teacher->username);
printf("export ADELE_FIXTURE_TEACHER_NAME='%s'\n", fullname($teacher));
foreach (
    [
    'ADELE_FIXTURE_MANAGER' => 'adelemanager',
    'ADELE_FIXTURE_ASSISTANT' => 'adeleassistant',
    ] as $variable => $roleshortname
) {
    if (!empty($roleusers[$roleshortname])) {
        printf("export %s='%s'\n", $variable, $roleusers[$roleshortname]->username);
        printf("export %s_NAME='%s'\n", $variable, fullname($roleusers[$roleshortname]));
    }
}
printf("export ADELE_FIXTURE_HOST_COURSE='%d'\n", $hostcourse->id);
foreach ($extrahosts as $number => $course) {
    printf("export ADELE_FIXTURE_HOST_COURSE_%d='%d'\n", $number, $course->id);
}
if ($nodelearner) {
    printf("export ADELE_FIXTURE_NODE_LEARNER='%s'\n", $nodelearner->username);
    printf("export ADELE_FIXTURE_NODE_LEARNER_NAME='%s'\n", fullname($nodelearner));
}
foreach (array_values($hostmembers) as $index => $member) {
    printf("export ADELE_FIXTURE_HOST_MEMBER_%d='%s'\n", $index + 1, $member->username);
    // The participants list addresses people by display name, not by login.
    printf("export ADELE_FIXTURE_HOST_MEMBER_%d_NAME='%s'\n", $index + 1, fullname($member));
}
if ($hostoutsider) {
    printf("export ADELE_FIXTURE_HOST_OUTSIDER='%s'\n", $hostoutsider->username);
}
foreach ($changepeople as $key => [$changeperson]) {
    printf("export ADELE_FIXTURE_HOST_%s='%s'\n", $key, $changeperson->username);
    printf("export ADELE_FIXTURE_HOST_%s_NAME='%s'\n", $key, fullname($changeperson));
}
foreach ($imported as $name => $id) {
    // One variable per path, keyed by its name in upper snake case, so a spec
    // can name the path it means instead of counting rows.
    $key = trim(strtoupper(preg_replace('/[^a-z0-9]+/i', '_', $name)), '_');
    printf("export ADELE_FIXTURE_PATH_%s='%d'\n", $key, $id);
    if (!empty($subscribed[$name])) {
        printf("export ADELE_FIXTURE_USERPATH_%s='%d'\n", $key, $subscribed[$name]);
    }
    if (!empty($assigned[$name])) {
        printf("export ADELE_FIXTURE_LEARNER_%s='%s'\n", $key, $assigned[$name]);
    }
}
foreach ($shortnames as $oldid => $shortname) {
    printf("export ADELE_FIXTURE_COURSE_%s='%s'\n", $shortname, $coursemap[$oldid]);
}
