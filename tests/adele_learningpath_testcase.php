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

namespace local_adele;

use advanced_testcase;
use mod_adele_observer;

/**
 * Abstract base test case for local_adele learning path integration tests.
 *
 * Provides shared setUp() infrastructure, fixture loading, event helpers, and
 * the mark_course_complete_in_db() utility.  Concrete subclasses implement
 * patch_node_ids() to assign real course IDs to the fixture tree nodes.
 *
 * Directory layout assumed:
 *   local/adele/tests/fixtures/  — JSON fixtures
 *   local/adele/tests/           — this file lives here
 *
 * @package    local_adele
 * @author     Christian Badusch
 * @copyright  2026 Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class adele_learningpath_testcase extends advanced_testcase {
    /**
     * Course IDs created during setUp(), indexed 0-4.
     *
     * @var int[]
     */
    protected array $courseids = [];

    /**
     * Event sink capturing all events fired during the test.
     *
     * @var \phpunit_event_sink
     */
    protected $sink;

    /**
     * Course ID used as the "home" course for the mod_adele instance.
     *
     * @var int
     */
    protected int $startingcourseid;

    /**
     * The mod_adele activity instance created in the starting course.
     *
     * @var object
     */
    protected object $adelestart;

    /**
     * Fixture filename relative to tests/fixtures/.
     * Subclasses MUST declare this to make the fixture dependency explicit.
     *
     * Example:
     *   protected string $fixturefile = 'alise_zugangs_lp_einfach.json';
     *
     * @var string
     */
    abstract protected function fixturefile(): string;

    // -------------------------------------------------------------------------
    // Bootstrap.

    /**
     * Set up test environment: creates courses, users, fixture, and event sink.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Create 5 courses (all with completion tracking enabled).
        $generator = self::getDataGenerator();
        $this->courseids = [];
        for ($i = 1; $i <= 5; $i++) {
            $course = $generator->create_course(
                ['fullname' => 'Test Course ' . $i, 'enablecompletion' => 1]
            );
            $this->courseids[] = $course->id;
        }

        // Enrol two users in the first (starting) course.
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $generator->enrol_user($user1->id, $this->courseids[0]);
        $generator->enrol_user($user2->id, $this->courseids[0]);

        $this->startingcourseid = $this->courseids[0];

        // Load the JSON fixture and let the subclass stamp real course IDs.
        $jsonstring = file_get_contents(__DIR__ . '/fixtures/' . $this->fixturefile());
        $jsonarray  = json_decode($jsonstring, true);
        $nodedata   = json_decode($jsonarray['json'], true);

        $this->patch_node_ids($nodedata['tree']['nodes']);

        $jsonarray['json'] = json_encode($nodedata);
        $lpid = $generator
            ->get_plugin_generator('local_adele')
            ->create_adele_learningpaths($jsonarray);

        // Open the event sink before creating the mod_adele instance so the
        // course_module_created event is captured.
        $this->sink = $this->redirectEvents();

        $this->adelestart = $generator->get_plugin_generator('mod_adele')->create_instance([
            'course'          => $this->startingcourseid,
            'name'            => 'Adele Activity',
            'participantslist' => [1],
            'learningpathid'  => $lpid,
        ]);
    }

    // -------------------------------------------------------------------------
    // Abstract hook.

    /**
     * Assign real course IDs (and any other fixture data overrides) to the
     * learning-path tree nodes.  Called during setUp() after the fixture is
     * decoded and before it is re-encoded and persisted.
     *
     * @param array $nodes Reference to $nodedata['tree']['nodes'].
     */
    abstract protected function patch_node_ids(array &$nodes): void;

    // -------------------------------------------------------------------------
    // Event helpers.

    /**
     * Dispatch the course_module_created event so that mod_adele_observer
     * creates local_adele_path_user rows for every enrolled user.
     *
     * Call this at the start of each test scenario that needs users subscribed
     * to the learning path.
     */
    protected function subscribe_users_to_lp(): void {
        $events       = $this->sink->get_events();
        $createdevents = array_values(array_filter(
            $events,
            fn($e) => $e->eventname === '\\core\\event\\course_module_created'
        ));
        mod_adele_observer::saved_module($createdevents[0]);
    }

    /**
     * Return all user_path_updated events collected so far, re-indexed from 0.
     *
     * @return array<\local_adele\event\user_path_updated>
     */
    protected function get_update_events(): array {
        return array_values(array_filter(
            $this->sink->get_events(),
            fn($e) => $e->eventname === '\\local_adele\\event\\user_path_updated'
        ));
    }

    /**
     * Return all node_finished events collected so far, re-indexed from 0.
     *
     * @return array<\local_adele\event\node_finished>
     */
    protected function get_node_finished_events(): array {
        return array_values(array_filter(
            $this->sink->get_events(),
            fn($e) => $e->eventname === '\\local_adele\\event\\node_finished'
        ));
    }

    // -------------------------------------------------------------------------
    // DB helpers.

    /**
     * Insert (or update) a course_completions record so that
     * completion_completion::fetch() considers the course completed by $userid.
     *
     * Also purges the MUC 'core/coursecompletion' cache entry so that any
     * subsequent is_complete() call reads the freshly written row immediately.
     *
     * The course must have been created with enablecompletion = 1.
     *
     * @param int $courseid
     * @param int $userid
     */
    protected function mark_course_complete_in_db(int $courseid, int $userid): void {
        global $DB;
        if (!$DB->record_exists('course_completions', ['course' => $courseid, 'userid' => $userid])) {
            $DB->insert_record('course_completions', (object)[
                'course'        => $courseid,
                'userid'        => $userid,
                'timeenrolled'  => time(),
                'timestarted'   => time(),
                'timecompleted' => time(),
                'reaggregate'   => 0,
            ]);
        } else {
            $DB->set_field(
                'course_completions',
                'timecompleted',
                time(),
                ['course' => $courseid, 'userid' => $userid]
            );
        }
        $cache = \cache::make('core', 'coursecompletion');
        $cache->delete($userid . '_' . $courseid);
    }
}
