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

namespace local_adele\security;

use advanced_testcase;
use context_course;
use context_module;
use context_system;
use local_adele\external\get_lp_user_path_relation;
use local_adele\external\get_lp_user_path_relations;
use local_adele\external\save_lp_user_path_relation;

// The attributes stand above the class comment: between comment and class
// they would hide the class-level @covers tags from the coding-style check
// (moodle.PHPUnit.TestCaseCovers), which only looks right above the class.
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
/**
 * Learner data and the course the request claims to come from.
 *
 * Both getters take a context id from the client. Rights that come from a
 * course - teaching it, being enrolled in it - used to count in ANY course,
 * so the context of an unrelated course opened the learner data of every
 * path. Found by the E2E chain P (learner data); measured on a running site:
 *
 *  - a participant of a course without any learning path read the complete
 *    roster of a path;
 *  - a learner whose activity says 'Only own results' read the complete roster
 *    through the context of a node course ADELE had enrolled them in;
 *  - a teacher of an unrelated course read the roster and one learner's
 *    snapshot including the e-mail address.
 *
 * The rights now count only in a course that embeds the path.
 *
 * Second half, same chain: authority that comes from the PATH. It was
 * check_access() - "may edit some path" - and so gave every assistant and
 * every collaborator of any path the learners of all paths. It is now the
 * path itself: managers, and the editors of this path. And the write side:
 * save_user_path_relation accepted the context of any course as well; a
 * teacher of an unrelated course set a master completion for a learner of a
 * foreign path (measured).
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\external\get_lp_user_path_relation
 * @covers \local_adele\external\get_lp_user_path_relations
 * @covers \local_adele\external\save_lp_user_path_relation
 * @covers \local_adele\learning_paths::is_embedded_in_course
 * @covers \local_adele\learning_paths::has_learner_data_authority
 * @runTestsInSeparateProcesses
 */
final class learner_data_embedding_test extends advanced_testcase {
    /** @var \stdClass The course that embeds the path, with 'Only own results'. */
    private $host;
    /** @var \stdClass A course that embeds nothing. */
    private $other;
    /** @var int The learning path. */
    private $lpid;
    /** @var \stdClass The activity embedding the path. */
    private $activity;
    /** @var \stdClass Learner A. */
    private $alpha;
    /** @var \stdClass Learner B. */
    private $beta;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->host = $gen->create_course();
        $this->other = $gen->create_course();
        $this->lpid = $this->make_path('Path');
        $this->activity = $this->embed($this->host, $this->lpid, 2);
        $this->alpha = $gen->create_and_enrol($this->host, 'student');
        $this->beta = $gen->create_and_enrol($this->host, 'student');
        $this->make_relation($this->alpha->id, $this->lpid);
        $this->make_relation($this->beta->id, $this->lpid);
    }

    /**
     * A learning path.
     *
     * @param string $name Its name.
     * @return int
     */
    private function make_path(string $name): int {
        global $DB;
        return (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => $name, 'json' => '{"tree":{"nodes":[]}}', 'visibility' => 1, 'createdby' => 2,
        ]);
    }

    /**
     * Embed a path into a course.
     *
     * @param \stdClass $course The course.
     * @param int $lpid The path.
     * @param int $userlist 2 = only own results, 1 = all results.
     * @return \stdClass The activity.
     */
    private function embed(\stdClass $course, int $lpid, int $userlist): \stdClass {
        return $this->getDataGenerator()->create_module('adele', [
            'course' => $course->id,
            'learningpathid' => $lpid,
            'view' => 1,
            'userlist' => $userlist,
            'participantslist' => '0',
        ]);
    }

    /**
     * An active snapshot of a learner on a path.
     *
     * @param int $userid The learner.
     * @param int $lpid The path.
     */
    private function make_relation(int $userid, int $lpid): void {
        global $DB;
        // Enrolling into a course that embeds the path subscribes already.
        if ($DB->record_exists('local_adele_path_user', ['user_id' => $userid, 'learning_path_id' => $lpid])) {
            return;
        }
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $userid,
            'course_id' => $this->host->id,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'json' => '{"tree":{"nodes":[]}}',
            'createdby' => $userid,
        ]);
    }

    /**
     * The ids a roster call returns.
     *
     * @param int $contextid The context the client passes.
     * @return int[]
     */
    private function roster(int $contextid): array {
        global $USER;
        $rows = get_lp_user_path_relations::execute((int) $USER->id, $this->lpid, $contextid);
        $ids = array_map('intval', array_column($rows, 'id'));
        sort($ids);
        return $ids;
    }

    /**
     * Somebody who is merely enrolled in a course without any learning path
     * must not read the roster by passing the context of that course.
     */
    public function test_participant_of_another_course_cannot_read_roster(): void {
        $outsider = $this->getDataGenerator()->create_and_enrol($this->other, 'student');
        $this->setUser($outsider);
        $this->expectException(\required_capability_exception::class);
        $this->roster(context_course::instance($this->other->id)->id);
    }

    /**
     * 'Only own results' holds whatever context the learner passes: the
     * context of another course they are enrolled in (a node course, say)
     * must not hand them the complete roster.
     */
    public function test_own_results_cannot_be_widened_through_another_course(): void {
        $this->getDataGenerator()->enrol_user($this->alpha->id, $this->other->id, 'student');
        $this->setUser($this->alpha);

        // The legitimate call: exactly the own row.
        $this->assertSame(
            [(int) $this->alpha->id],
            $this->roster(context_module::instance($this->activity->cmid)->id)
        );

        $this->expectException(\required_capability_exception::class);
        $this->roster(context_course::instance($this->other->id)->id);
    }

    /**
     * Another ADELE activity of the same course, showing a DIFFERENT path with
     * 'all results', does not speak for this path.
     */
    public function test_another_activity_does_not_lift_own_results(): void {
        $second = $this->embed($this->host, $this->make_path('Second path'), 1);
        $this->setUser($this->alpha);
        $this->assertSame(
            [(int) $this->alpha->id],
            $this->roster(context_module::instance($second->cmid)->id)
        );
    }

    /**
     * A teacher of an unrelated course must not read the roster of the path.
     */
    public function test_teacher_of_another_course_cannot_read_roster(): void {
        $foreign = $this->getDataGenerator()->create_and_enrol($this->other, 'teacher');
        $this->setUser($foreign);
        $this->expectException(\required_capability_exception::class);
        $this->roster(context_course::instance($this->other->id)->id);
    }

    /**
     * A teacher of an unrelated course must not read one learner's snapshot
     * (which carries the e-mail address) either.
     */
    public function test_teacher_of_another_course_cannot_read_a_learner(): void {
        $foreign = $this->getDataGenerator()->create_and_enrol($this->other, 'teacher');
        $this->setUser($foreign);
        $this->expectException(\required_capability_exception::class);
        get_lp_user_path_relation::execute(
            $this->lpid,
            (int) $this->beta->id,
            context_course::instance($this->other->id)->id
        );
    }

    /**
     * No regression: the teacher of the embedding course reads the roster and
     * a learner, whatever the result visibility says.
     */
    public function test_teacher_of_the_embedding_course_reads_roster_and_learner(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->host, 'teacher');
        $this->setUser($teacher);
        $modulecontextid = context_module::instance($this->activity->cmid)->id;

        // The teacher's enrolment subscribed the teacher as well; what matters
        // is that both learners are there.
        $roster = $this->roster($modulecontextid);
        $this->assertContains((int) $this->alpha->id, $roster);
        $this->assertContains((int) $this->beta->id, $roster);
        $one = get_lp_user_path_relation::execute($this->lpid, (int) $this->beta->id, $modulecontextid);
        $this->assertSame((int) $this->beta->id, (int) $one['user_id']);
    }

    /**
     * No regression: a learner reads their own snapshot from wherever they
     * open the path.
     */
    public function test_owner_reads_own_snapshot_in_any_context(): void {
        $this->getDataGenerator()->enrol_user($this->alpha->id, $this->other->id, 'student');
        $this->setUser($this->alpha);
        $one = get_lp_user_path_relation::execute(
            $this->lpid,
            (int) $this->alpha->id,
            context_course::instance($this->other->id)->id
        );
        $this->assertSame((int) $this->alpha->id, (int) $one['user_id']);
    }

    /**
     * A context outside any course carries no course rights - and is no
     * coding error: a site administrator gets the roster; a learner is
     * turned away.
     */
    public function test_system_context_serves_administrators_only(): void {
        $systemcontextid = context_system::instance()->id;

        $this->setAdminUser();
        $this->assertSame([(int) $this->alpha->id, (int) $this->beta->id], $this->roster($systemcontextid));

        $this->setUser($this->alpha);
        $this->expectException(\required_capability_exception::class);
        $this->roster($systemcontextid);
    }

    /**
     * The people who build and manage paths, none of them a teacher of the
     * embedding course.
     *
     * @return \stdClass[] By what they are.
     */
    private function path_people(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $system = context_system::instance();
        $people = [];

        $people['ADELE manager'] = $gen->create_user();
        $managerrole = $gen->create_role();
        assign_capability('local/adele:canmanage', CAP_ALLOW, $managerrole, $system);
        role_assign($managerrole, $people['ADELE manager']->id, $system);

        $people['assistant, editor of another path'] = $gen->create_user();
        $assistantrole = $gen->create_role();
        assign_capability('local/adele:assist', CAP_ALLOW, $assistantrole, $system);
        role_assign($assistantrole, $people['assistant, editor of another path']->id, $system);
        $DB->insert_record('local_adele_lp_editors', (object) [
            'userid' => $people['assistant, editor of another path']->id,
            'learningpathid' => $this->make_path('Their own path'),
        ]);

        $people['editor of this path'] = $gen->create_user();
        $DB->insert_record('local_adele_lp_editors', (object) [
            'userid' => $people['editor of this path']->id,
            'learningpathid' => $this->lpid,
        ]);
        return $people;
    }

    /**
     * Building or managing paths gives no view of the learners and no say
     * over their paths (#586, addendum of 2026-10-07): the ADELE manager, an
     * assistant who edits another path, an editor of THIS path - none reads
     * the roster or a learner, none changes a learner's path, through the
     * embedding activity or without a course.
     */
    public function test_building_or_managing_paths_gives_no_access_to_learner_data(): void {
        $contexts = [
            'the embedding activity' => context_module::instance($this->activity->cmid)->id,
            'no course' => context_system::instance()->id,
        ];
        foreach ($this->path_people() as $who => $person) {
            $this->setUser($person);
            $this->assertTrue((bool) \local_adele\learning_paths::check_access(), $who . ': may work in the editor');
            $this->assertFalse(\local_adele\learning_paths::has_learner_data_authority(), $who);
            foreach ($contexts as $where => $contextid) {
                $calls = [
                    'roster' => function () use ($contextid) {
                        $this->roster($contextid);
                    },
                    'learner' => function () use ($contextid) {
                        get_lp_user_path_relation::execute($this->lpid, (int) $this->beta->id, $contextid);
                    },
                    'change' => function () use ($contextid) {
                        $this->save($contextid);
                    },
                ];
                foreach ($calls as $what => $call) {
                    try {
                        $call();
                        $this->fail("{$who}, {$what}, through {$where}: must be refused");
                    } catch (\required_capability_exception $e) {
                        $this->assertSame('nopermissions', $e->errorcode, "{$who}, {$what}, through {$where}");
                    }
                }
            }
        }
    }

    /**
     * The same people AS TEACHERS of the embedding course have what a teacher
     * has - from the course role, not from the path: they read the course's
     * learners there, still nothing without a course, and change a learner's
     * path only with local/adele:teacheredit.
     */
    public function test_path_people_who_teach_the_course_have_the_teachers_rights(): void {
        $gen = $this->getDataGenerator();
        $modulecontextid = context_module::instance($this->activity->cmid)->id;
        foreach ($this->path_people() as $who => $person) {
            $gen->enrol_user($person->id, $this->host->id, 'teacher');
            $this->setUser($person);
            $this->assertSame(
                [(int) $this->alpha->id, (int) $this->beta->id],
                $this->roster($modulecontextid),
                $who . ' as a teacher without editing rights: the roster of the course'
            );
            $one = get_lp_user_path_relation::execute($this->lpid, (int) $this->beta->id, $modulecontextid);
            $this->assertSame((int) $this->beta->id, (int) $one['user_id'], $who);
            try {
                $this->roster(context_system::instance()->id);
                $this->fail($who . ': no roster without a course');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
            try {
                $this->save($modulecontextid);
                $this->fail($who . ' without editing rights must not change a learner path');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }

            $gen->enrol_user($person->id, $this->host->id, 'editingteacher');
            $this->assertTrue($this->save($modulecontextid)['success'], $who . ' as an editing teacher');
        }
    }

    /**
     * Change a learner's path the way the path view does.
     *
     * @param int $contextid The context the client passes.
     * @param int|null $payloadpath The path named inside the payload; defaults to the path of the call.
     * @return array
     */
    private function save(int $contextid, ?int $payloadpath = null): array {
        global $USER;
        return save_lp_user_path_relation::execute(
            $this->lpid,
            (int) $USER->id,
            json_encode([
                'route' => ['learningpathId' => $payloadpath ?? $this->lpid, 'userId' => (int) $this->beta->id],
                'nodes' => [],
            ]),
            $contextid
        );
    }

    /**
     * A teacher of an unrelated course must not change a learner's path.
     */
    public function test_teacher_of_another_course_cannot_change_a_learner_path(): void {
        $foreign = $this->getDataGenerator()->create_and_enrol($this->other, 'teacher');
        $this->setUser($foreign);
        $this->expectException(\required_capability_exception::class);
        $this->save(context_course::instance($this->other->id)->id);
    }

    /**
     * The path that is written is the one named inside the payload - so the
     * permission must be about that one. A teacher whose own course embeds
     * some path must not write a foreign path by naming their own in the call.
     */
    public function test_payload_must_belong_to_the_path_of_the_call(): void {
        $ownpath = $this->make_path('Embedded in the other course');
        $this->embed($this->other, $ownpath, 1);
        $foreign = $this->getDataGenerator()->create_and_enrol($this->other, 'editingteacher');
        $this->setUser($foreign);
        $this->expectException(\invalid_parameter_exception::class);
        save_lp_user_path_relation::execute(
            $ownpath,
            (int) $foreign->id,
            json_encode([
                'route' => ['learningpathId' => $this->lpid, 'userId' => (int) $this->beta->id],
                'nodes' => [],
            ]),
            context_course::instance($this->other->id)->id
        );
    }

    /**
     * A participant must not change anybody's path, the own one included.
     */
    public function test_participant_cannot_change_a_learner_path(): void {
        $this->setUser($this->alpha);
        $this->expectException(\required_capability_exception::class);
        $this->save(context_module::instance($this->activity->cmid)->id);
    }

    /**
     * No regression: the teacher of the embedding course changes a learner's
     * path, and so does a site administrator without any course.
     */
    public function test_teacher_of_the_embedding_course_changes_a_learner_path(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->host, 'editingteacher');
        $this->setUser($teacher);
        $this->assertTrue($this->save(context_module::instance($this->activity->cmid)->id)['success']);

        $this->setAdminUser();
        $this->assertTrue($this->save(context_system::instance()->id)['success']);
    }
}
