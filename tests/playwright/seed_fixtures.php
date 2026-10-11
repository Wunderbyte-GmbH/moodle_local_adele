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

    $existing = $DB->get_field('local_adele_learning_paths', 'id', ['name' => $fixture['name']]);
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
    }
}

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
    global $DB, $CFG;
    foreach ($base['tree']['nodes'] as $n => $node) {
        if ($node['id'] === 'dndnode_1') {
            $base['tree']['nodes'][$n]['restriction'] = $restriction;
        }
    }
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
$timepath = null;
$timelearner = null;
$durationpath = null;
$durationlearner = null;
$durationcreated = 0;
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

// A second host course, deliberately EMPTY: the chain about the other
// participant source ("people enrolled in a starting node") must not inherit
// the members of the first one, or it could not tell the two sources apart.
$hostcourse2 = $DB->get_record('course', ['shortname' => 'E2EHOST2']);
if (!$hostcourse2) {
    $hostcourse2 = create_course((object) [
        'shortname' => 'E2EHOST2',
        'fullname' => 'E2E host course (starting node source)',
        'category' => (int) $options['category'],
        'summary' => 'Host course for the second participant source. Created by seed_fixtures.php.',
        'summaryformat' => FORMAT_HTML,
    ]);
}
foreach ($DB->get_records('adele', ['course' => $hostcourse2->id]) as $activity) {
    $cm = get_coursemodule_from_instance('adele', $activity->id, $hostcourse2->id);
    if ($cm) {
        course_delete_module($cm->id);
    }
}

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
printf("export ADELE_FIXTURE_HOST_COURSE_2='%d'\n", $hostcourse2->id);
foreach (array_values($hostmembers) as $index => $member) {
    printf("export ADELE_FIXTURE_HOST_MEMBER_%d='%s'\n", $index + 1, $member->username);
    // The participants list addresses people by display name, not by login.
    printf("export ADELE_FIXTURE_HOST_MEMBER_%d_NAME='%s'\n", $index + 1, fullname($member));
}
if ($hostoutsider) {
    printf("export ADELE_FIXTURE_HOST_OUTSIDER='%s'\n", $hostoutsider->username);
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
