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
 * Test signal: one finished CAT attempt with a given person ability.
 *
 * What a real adaptive test leaves behind, without playing one through:
 *  1. an mod_adaptivequiz attempt (with its question usage), state completed;
 *  2. the local_catquiz result row whose JSON carries the person ability
 *     per scale - what local_adele's catquiz condition reads;
 *  3. the event \mod_adaptivequiz\event\attempt_completed, as the learner -
 *     what local_adele observes to re-evaluate the learner's paths.
 *
 * Usage (from the Moodle root, test sites only):
 *   ADELE_SEED_I_KNOW=1 php local/adele/tests/e2e/support/catquiz_signal.php \
 *       --user=fx_d_at --cmid=123 --scale=45 --ability=2.5
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/questionlib.php');
// Observers of the event (completion tracking) expect what a web request has
// already loaded.
require_once($CFG->libdir . '/completionlib.php');

[$options] = cli_get_params(['user' => '', 'cmid' => 0, 'scale' => 0, 'ability' => null], []);
if (getenv('ADELE_SEED_I_KNOW') !== '1') {
    cli_error('Writes test data. Set ADELE_SEED_I_KNOW=1 on a throwaway site only.');
}
if ($options['user'] === '' || !$options['cmid'] || !$options['scale'] || !is_numeric($options['ability'])) {
    cli_error('Needs --user, --cmid, --scale and --ability.');
}

$user = $DB->get_record('user', ['username' => $options['user'], 'deleted' => 0], '*', MUST_EXIST);
$cm = get_coursemodule_from_id('adaptivequiz', (int) $options['cmid'], 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$ability = (float) $options['ability'];
$scaleid = (int) $options['scale'];
$now = time();

// 1. The adaptive test's own attempt.
$quba = question_engine::make_questions_usage_by_activity('mod_adaptivequiz', $context);
$quba->set_preferred_behaviour('deferredfeedback');
question_engine::save_questions_usage_by_activity($quba);
$attemptid = $DB->insert_record('adaptivequiz_attempt', (object) [
    'instance' => $cm->instance,
    'userid' => $user->id,
    'uniqueid' => $quba->get_id(),
    'attemptstate' => 'completed',
    'attemptstopcriteria' => 'test signal',
    'questionsattempted' => 1,
    'difficultysum' => 0,
    'standarderror' => 0,
    'measure' => $ability,
    'resultvalid' => 1,
    'timecreated' => $now,
    'timemodified' => $now,
]);

// 2. The catquiz result: person ability per scale.
$DB->insert_record('local_catquiz_attempts', (object) [
    'userid' => $user->id,
    'scaleid' => $scaleid,
    'contextid' => $context->id,
    'courseid' => $cm->course,
    'attemptid' => $attemptid,
    'component' => 'mod_adaptivequiz',
    'instanceid' => $cm->instance,
    'teststrategy' => 0,
    'status' => 0,
    'total_number_of_testitems' => 1,
    'number_of_testitems_used' => 1,
    'personability_before_attempt' => 0,
    'personability_after_attempt' => $ability,
    'starttime' => $now,
    'endtime' => $now,
    'json' => json_encode(['personabilities' => [$scaleid => $ability], 'contextid' => $context->id]),
    'timecreated' => $now,
    'timemodified' => $now,
]);

// 3. The event, as the learner.
\mod_adaptivequiz\event\attempt_completed::create([
    'objectid' => $attemptid,
    'context' => $context,
    'userid' => $user->id,
])->trigger();

echo "signal: {$user->username} ability {$ability} on scale {$scaleid} (attempt {$attemptid})\n";
