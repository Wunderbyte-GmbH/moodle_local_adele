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
 * Seed a browser-test fixture for local_adele.
 *
 * Creates a learning path with one course node, then prints shell assignments
 * so the caller can source them or feed them into the job environment:
 *
 *     php local/adele/tests/playwright/seed.php > /tmp/seed.env
 *     . /tmp/seed.env
 *
 * DESTRUCTIVE. It resets the admin password so the browser suite has a known
 * credential, and is meant for a throwaway CI site, never for an installation
 * anyone depends on. It refuses to run unless ADELE_SEED_I_KNOW=1 is set.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
// The data generators live under lib/testing and are not autoloaded outside
// the PHPUnit bootstrap; this script runs against a normal site.
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/testing/generator/data_generator.php');

if (getenv('ADELE_SEED_I_KNOW') !== '1') {
    cli_error(
        "Refusing to run: this script resets the admin password and writes fixture data.\n" .
        "Set ADELE_SEED_I_KNOW=1 to confirm this is a throwaway site."
    );
}

$adminpassword = getenv('ADELE_ADMIN_PASSWORD') ?: 'Playwright!23';
$suffix = time();
$lpname = 'Playwright-Lernpfad ' . $suffix;
$courseshortname = 'PW' . $suffix;

// Moodle's default is to mark the session cookie Secure. A browser refuses
// to store such a cookie over plain http, so no session is established. The
// browser suite runs against http://127.0.0.1, so the flag has to go.
if (!empty($CFG->cookiesecure)) {
    set_config('cookiesecure', 0);
}

// A site served over plain HTTP cannot keep a Secure session cookie. Moodle's
// installer defaults cookiesecure to on, the browser then drops the cookie,
// no session exists, and the login token check fails — so Moodle answers a
// perfectly valid login with "Invalid login, please try again". The failure
// names the wrong cause, which is why it belongs here rather than in a
// workaround inside the browser suite.
if (strpos($CFG->wwwroot, 'https://') !== 0) {
    set_config('cookiesecure', 0);
}

// A known admin credential for the browser suite.
$admin = get_admin();
$admin->password = hash_internal_user_password($adminpassword);
$DB->set_field('user', 'password', $admin->password, ['id' => $admin->id]);

$generator = new testing_data_generator();

$course = $generator->create_course([
    'shortname' => $courseshortname,
    'fullname' => 'Playwright-Zielkurs ' . $suffix,
]);

$json = [
    'tree' => [
        'nodes' => [
            [
                'id' => 'dndnode_1',
                'type' => 'courseNode',
                'parentCourse' => ['starting_node'],
                'data' => ['course_node_id' => [(int) $course->id]],
            ],
        ],
        'edges' => [],
    ],
];

$lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
    'name' => $lpname,
    'description' => '',
    'timecreated' => time(),
    'timemodified' => time(),
    'createdby' => (int) $admin->id,
    'json' => json_encode($json),
]);

$student = $generator->create_user([
    'username' => 'pwstudent' . $suffix,
    'firstname' => 'Playwright',
    'lastname' => 'Student',
]);

$DB->insert_record('local_adele_path_user', (object) [
    'user_id' => (int) $student->id,
    'course_id' => 0,
    'learning_path_id' => $lpid,
    'status' => 'active',
    'timecreated' => time(),
    'timemodified' => time(),
    'createdby' => (int) $admin->id,
    'json' => json_encode($json + [
        'user_path_relation' => [
            'dndnode_1' => ['feedback' => ['status' => 'accessible']],
        ],
    ]),
]);


$fixturepassword = 'Playwright!23';

/**
 * Create or reuse a user with a fixed username.
 *
 * Fixed, not randomised: the specs assert on these exact identifiers, and a
 * suffix would make the assertion meaningless. Reused rather than recreated
 * so the seed can run repeatedly against the same throwaway instance.
 *
 * @param testing_data_generator $generator The generator.
 * @param string $username The fixed username.
 * @param string $firstname First name.
 * @param string $lastname Last name.
 * @param string $password The password to set.
 * @return object The user record.
 */
function adele_pw_user($generator, string $username, string $firstname, string $lastname, string $password) {
    global $DB;
    $existing = $DB->get_record('user', ['username' => $username]);
    if ($existing) {
        $DB->set_field('user', 'password', hash_internal_user_password($password), ['id' => $existing->id]);
        return $existing;
    }
    return $generator->create_user([
        'username' => $username,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $username . '@example.invalid',
        'password' => $password,
    ]);
}

$manager = adele_pw_user($generator, 'pw_adele_manager', 'Playwright', 'Manager', $fixturepassword);
$assistant = adele_pw_user($generator, 'pw_adele_assistant', 'Playwright', 'Assistant', $fixturepassword);

// The two ADELE system roles are created by local_adele's own db/install.php,
// so the fixture assigns them rather than inventing its own.
$systemcontext = context_system::instance();
foreach ([['adelemanager', $manager], ['adeleassistant', $assistant]] as [$shortname, $user]) {
    $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
    if ($roleid) {
        role_assign((int) $roleid, (int) $user->id, $systemcontext->id);
    }
}

$visibletitle = 'Assistant sichtbar';
$invisibletitle = 'Assistant unsichtbar';
$emptytree = ['tree' => ['nodes' => [], 'edges' => []]];

$pathids = [];
foreach ([[$visibletitle, 1], [$invisibletitle, 0]] as [$title, $visible]) {
    $existing = $DB->get_record('local_adele_learning_paths', ['name' => $title]);
    if ($existing) {
        $DB->set_field('local_adele_learning_paths', 'visibility', $visible, ['id' => $existing->id]);
        $DB->set_field('local_adele_learning_paths', 'createdby', $manager->id, ['id' => $existing->id]);
        $pathids[$title] = (int) $existing->id;
        continue;
    }
    $pathids[$title] = (int) $DB->insert_record('local_adele_learning_paths', (object) [
        'name' => $title,
        'description' => 'Playwright-Fixture',
        'timecreated' => time(),
        'timemodified' => time(),
        'createdby' => (int) $manager->id,
        'visibility' => $visible,
        'json' => json_encode($emptytree),
    ]);
}

$visiblebtitle = 'Assistant sichtbar mit Bearbeiter';
$invisiblebtitle = 'Assistant unsichtbar mit Bearbeiter';
foreach ([[$visiblebtitle, 1], [$invisiblebtitle, 0]] as [$title, $visible]) {
    $existing = $DB->get_record('local_adele_learning_paths', ['name' => $title]);
    if ($existing) {
        $DB->set_field('local_adele_learning_paths', 'visibility', $visible, ['id' => $existing->id]);
        $DB->set_field('local_adele_learning_paths', 'createdby', $manager->id, ['id' => $existing->id]);
        $pathids[$title] = (int) $existing->id;
    } else {
        $pathids[$title] = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => $title,
            'description' => 'Playwright-Fixture',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => (int) $manager->id,
            'visibility' => $visible,
            'json' => json_encode($emptytree),
        ]);
    }
    // The real API, not a hand-written row: an editor assignment invented by
    // the fixture could differ from what the plugin itself would create, and
    // the test would then prove nothing about the production relation.
    \local_adele\learning_path_editors::create_editors($pathids[$title], (int) $assistant->id);
}

// The owner stays the manager in every case. An assistant who owns a path
// sees it for an entirely different reason, which would make the visibility
// assertion meaningless.

$navcourse = $DB->get_record('course', ['shortname' => 'PWNAV458']);
if (!$navcourse) {
    $navcourse = $generator->create_course([
        'shortname' => 'PWNAV458',
        'fullname' => 'PW Kurs Navigation 458',
    ]);
}
$collaborator = adele_pw_user($generator, 'pw_collaborator', 'Playwright', 'Kollaborator', $fixturepassword);
$t0 = adele_pw_user($generator, 'pw_t0', 'Playwright', 'T Null', $fixturepassword);
$generator->enrol_user((int) $collaborator->id, (int) $navcourse->id, 'editingteacher');
$generator->enrol_user((int) $t0->id, (int) $navcourse->id, 'teacher');

printf("export ADELE_BASE_URL='%s'\n", $CFG->wwwroot);
printf("export ADELE_ADMIN_USER='%s'\n", $admin->username);
printf("export ADELE_ADMIN_PASSWORD='%s'\n", $adminpassword);
printf("export ADELE_LP_NAME='%s'\n", $lpname);
printf("export ADELE_LP_ID='%d'\n", $lpid);
printf("export ADELE_COURSE_SHORTNAME='%s'\n", $courseshortname);
printf("export ADELE_FIXTURE_PASSWORD='%s'\n", $fixturepassword);
printf("export ADELE_MANAGER_USERNAME='%s'\n", $manager->username);
printf("export ADELE_ASSISTANT_USERNAME='%s'\n", $assistant->username);
printf("export ADELE_VISIBLE_PATH_TITLE='%s'\n", $visibletitle);
printf("export ADELE_INVISIBLE_PATH_TITLE='%s'\n", $invisibletitle);
printf("export ADELE_VISIBLE_PATH_ID='%d'\n", $pathids[$visibletitle]);
printf("export ADELE_INVISIBLE_PATH_ID='%d'\n", $pathids[$invisibletitle]);
printf("export ADELE_VISIBLE_PATH_B_TITLE='%s'\n", $visiblebtitle);
printf("export ADELE_INVISIBLE_PATH_B_TITLE='%s'\n", $invisiblebtitle);
printf("export ADELE_COLLABORATOR_USERNAME='%s'\n", $collaborator->username);
printf("export ADELE_T0_USERNAME='%s'\n", $t0->username);
printf("export ADELE_NAV_COURSE_ID='%d'\n", (int) $navcourse->id);
printf("export ADELE_NAV_COURSE_URL='%s'\n", $CFG->wwwroot . '/course/view.php?id=' . (int) $navcourse->id);

// Self-check: every variable the suite reads must actually have been printed.
//
// This exists because it already went wrong once. A seed that had lost its
// fixture block still ran, still printed the six basic variables, and still
// exited zero — the failure surfaced much later as four browser tests
// complaining about unset environment variables, in a plugin nobody had
// touched. Silence is the wrong answer when half the fixture is missing.
$expected = [
    'ADELE_BASE_URL', 'ADELE_ADMIN_USER', 'ADELE_ADMIN_PASSWORD',
    'ADELE_LP_NAME', 'ADELE_LP_ID', 'ADELE_COURSE_SHORTNAME',
    'ADELE_FIXTURE_PASSWORD', 'ADELE_MANAGER_USERNAME', 'ADELE_ASSISTANT_USERNAME',
    'ADELE_VISIBLE_PATH_TITLE', 'ADELE_INVISIBLE_PATH_TITLE',
    'ADELE_VISIBLE_PATH_ID', 'ADELE_INVISIBLE_PATH_ID',
    'ADELE_VISIBLE_PATH_B_TITLE', 'ADELE_INVISIBLE_PATH_B_TITLE',
    'ADELE_COLLABORATOR_USERNAME', 'ADELE_T0_USERNAME',
    'ADELE_NAV_COURSE_ID', 'ADELE_NAV_COURSE_URL',
];
$printed = [];
foreach (file(__FILE__) as $line) {
    if (preg_match('/^printf\\("export (ADELE_[A-Z0-9_]+)=/', $line, $m)) {
        $printed[] = $m[1];
    }
}
$missing = array_diff($expected, $printed);
if ($missing) {
    cli_error(
        'Seed is incomplete: it does not print ' . implode(', ', $missing) . '. ' .
        'The browser suite reads these, so a partial fixture would fail far from its cause.'
    );
}
