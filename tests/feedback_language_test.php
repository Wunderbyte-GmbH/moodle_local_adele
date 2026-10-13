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
use context_system;
use local_adele\event\user_path_updated;

/**
 * The texts a learner reads on a node, and language (#589, #590).
 *
 * A path stores copies of the default texts in the language of whoever built
 * it. These tests build one path the way an ENGLISH author's editor stores it
 * and read it as a GERMAN learner - and the other way round:
 *
 *  - the recompute renders in the language of the learner the path belongs
 *    to, whoever triggers it;
 *  - a default is rendered from the language string, never from the stored
 *    copy - so no "Complete diesen Kurs";
 *  - a text the author wrote themselves is theirs in every language;
 *  - a recompute in one language followed by the fetch-time re-render in
 *    another gives exactly the texts of a recompute in that other language;
 *  - #590: the wording around time windows and manual completion.
 *
 * German needs a language pack. The test site has none, so setUp() provides
 * the minimum that makes "de" an installed language; the plugin's own German
 * strings (lang/de) then apply, everything else falls back to English.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\relation_update
 * @covers \local_adele\course_restriction\conditions\timed
 */
final class feedback_language_test extends advanced_testcase {
    /** @var int 2030-01-15 10:00:00 UTC. */
    private const START = 1894701600;
    /** @var int 2030-01-15 12:00:00 UTC. */
    private const END = 1894708800;
    /** @var string What an author wrote instead of the default. */
    private const AUTHOR_TEXT = 'Ask Ms Okafor for the key first';

    /** @var int[] Three courses with completion. */
    private array $courses = [];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest(true);
        // Minimal German pack: enough for Moodle to list "de" as installed.
        $dir = $CFG->langotherroot . '/de';
        make_writable_directory($dir);
        file_put_contents(
            $dir . '/langconfig.php',
            "<?php\n\$string['thislanguage'] = 'Deutsch';\n\$string['parentlanguage'] = '';\n"
        );
        get_string_manager()->reset_caches(true);
        $this->assertTrue(get_string_manager()->translation_exists('de', false), 'the test needs German');
        set_config('timezone', 'UTC');
        for ($i = 0; $i < 3; $i++) {
            $this->courses[] = (int) self::getDataGenerator()->create_course(['enablecompletion' => 1])->id;
        }
    }

    protected function tearDown(): void {
        force_current_language('');
        get_string_manager()->reset_caches(true);
        parent::tearDown();
    }

    /**
     * A condition with its feedback node, as the editor of an English author
     * stores them: copies of the English defaults, "use default" ticked.
     *
     * @param string $type 'restriction' or 'completion'.
     * @param string $id Condition id.
     * @param string $label Condition class.
     * @param mixed $value The condition's value.
     * @param string|null $next A condition chained behind this one (AND).
     * @param array|null $feedback Overrides of the feedback node's data; null for "no feedback node".
     * @return array The nodes.
     */
    private function condition(string $type, string $id, string $label, $value, ?string $next, ?array $feedback = []): array {
        $class = '\\local_adele\\course_' . $type . '\\conditions\\' . $label;
        $previous = force_current_language('en');
        $defaults = (new $class())->get_description();
        force_current_language($previous);
        unset($defaults['id'], $defaults['label']);
        $children = array_values(array_filter([$feedback === null ? null : $id . '_feedback', $next]));
        $nodes = [[
            'id' => $id,
            'parentCondition' => ['starting_condition'],
            'childCondition' => $children,
            'data' => array_merge($defaults, ['label' => $label, 'value' => $value, 'visibility' => true]),
        ]];
        if ($feedback !== null) {
            $data = [
                'label' => 'feedback',
                'childCondition' => $id,
                'visibility' => true,
                'information' => $defaults['information'],
            ];
            foreach ($type === 'restriction' ? ['before'] : ['before', 'inbetween', 'after'] as $state) {
                $data['feedback_' . $state . '_checkmark'] = true;
                $data['feedback_' . $state] = $defaults['description_' . $state] ?? '';
            }
            $nodes[] = [
                'id' => $id . '_feedback',
                'type' => 'feedback',
                'parentCondition' => [$id],
                'childCondition' => [],
                'data' => array_merge($data, $feedback),
            ];
        }
        return $nodes;
    }

    /**
     * One course node.
     *
     * @param string $id
     * @param int $course Index into $this->courses.
     * @param string $name
     * @param array $restriction Condition nodes.
     * @param array|null $completion Condition nodes; default: complete the course.
     * @return array
     */
    private function node(string $id, int $course, string $name, array $restriction, ?array $completion = null): array {
        return [
            'id' => $id,
            'type' => 'custom',
            'parentCourse' => ['starting_node'],
            'childCourse' => [],
            'data' => ['course_node_id' => [(string) $this->courses[$course]], 'fullname' => $name, 'node_id' => $id],
            'restriction' => ['nodes' => $restriction, 'edges' => []],
            'completion' => [
                'nodes' => $completion
                    ?? $this->condition('completion', 'condition_1', 'course_completed', ['min_courses' => 1], null),
                'edges' => [],
            ],
        ];
    }

    /**
     * Second in a chain: not a starting condition.
     *
     * @param array $nodes Nodes of one condition.
     * @param string $parent The condition in front.
     * @return array
     */
    private function chained(array $nodes, string $parent): array {
        $nodes[0]['parentCondition'] = [$parent];
        return $nodes;
    }

    /**
     * The path of these tests:
     *  node 1 - free, completed by completing its course;
     *  node 2 - after node 1 (default text);
     *  node 3 - after node 1 AND inside a fixed window (one feedback node for the chain);
     *  node 4 - after node 1, with a text the author wrote.
     *
     * @return array
     */
    private function tree(): array {
        $after1 = ['courses_id' => ['dndnode_1'], 'min_courses' => 1];
        $window = ['start' => self::START, 'end' => self::END];
        return ['tree' => ['edges' => [], 'nodes' => [
            $this->node('dndnode_1', 0, 'Kurs A', []),
            $this->node('dndnode_2', 1, 'Kurs B', $this->condition('restriction', 'condition_1', 'parent_courses', $after1, null)),
            $this->node('dndnode_3', 2, 'Kurs C', array_merge(
                $this->condition('restriction', 'condition_1', 'parent_courses', $after1, 'condition_2'),
                $this->chained($this->condition('restriction', 'condition_2', 'timed', $window, null, null), 'condition_1')
            )),
            $this->node('dndnode_4', 2, 'Kurs D', $this->condition(
                'restriction',
                'condition_1',
                'parent_courses',
                $after1,
                null,
                ['feedback_before_checkmark' => false, 'feedback_before' => self::AUTHOR_TEXT]
            )),
        ]], 'modules' => null];
    }

    /**
     * Store the path for a learner and recompute it - triggered by whoever is
     * the current user, in whatever the current language is.
     *
     * @param \stdClass $learner
     * @param array $tree
     * @return int The id of the learner's path.
     */
    private function recompute(\stdClass $learner, array $tree): int {
        global $DB;
        $now = time();
        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'language ' . $learner->id . ' ' . random_string(5), 'json' => json_encode($tree),
            'timecreated' => $now, 'timemodified' => $now, 'createdby' => $learner->id,
        ]);
        $id = (int) $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $learner->id, 'course_id' => $this->courses[0], 'learning_path_id' => $lpid,
            'status' => 'active', 'json' => json_encode($tree),
            'timecreated' => $now, 'timemodified' => $now, 'createdby' => $learner->id,
        ]);
        $userpath = $DB->get_record('local_adele_path_user', ['id' => $id]);
        $userpath->json = json_decode($userpath->json, true);
        relation_update::updated_single(user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => context_system::instance(),
            'other' => ['userpath' => $userpath],
        ]));
        return $id;
    }

    /**
     * The learner's path as stored.
     *
     * @param int $id
     * @return array The decoded json.
     */
    private function stored(int $id): array {
        global $DB;
        return json_decode($DB->get_field('local_adele_path_user', 'json', ['id' => $id]), true);
    }

    /**
     * Everything a viewer reads on the nodes of a path: the text fields of
     * every node's feedback, nothing else.
     *
     * @param array $json The decoded path.
     * @return array
     */
    private function texts(array $json): array {
        $texts = [];
        foreach ($json['user_path_relation'] as $nodeid => $relation) {
            $feedback = $relation['feedback'];
            foreach (['information', 'before', 'inbetween', 'after', 'after_all'] as $field) {
                $texts[$nodeid]['completion'][$field] = array_values((array) ($feedback['completion'][$field] ?? []));
            }
            foreach (['information', 'before', 'before_valid', 'before_active', 'before_timed', 'inbetween_timed'] as $field) {
                $texts[$nodeid]['restriction'][$field] = $feedback['restriction'][$field] ?? null;
            }
        }
        return $texts;
    }

    /**
     * A learner.
     *
     * @param string $lang Profile language.
     * @return \stdClass
     */
    private function learner(string $lang): \stdClass {
        return self::getDataGenerator()->create_user(['lang' => $lang, 'timezone' => 'UTC']);
    }

    /**
     * Whoever triggers the recompute - an English teacher, cron - a German
     * learner's path is rendered in German, completely.
     */
    public function test_recompute_renders_in_the_learners_language(): void {
        $this->mock_clock_with_frozen(self::START - HOURSECS);
        $learner = $this->learner('de');
        $this->setAdminUser();
        $this->assertSame('en', current_language(), 'the one who triggers it is English');

        $json = $this->stored($this->recompute($learner, $this->tree()));

        $this->assertSame('en', current_language(), 'the language of the session is put back');
        $this->assertSame('de', $json['feedback_lang']);
        $de = $this->texts($json);
        $this->assertSame(['diesen Kurs bearbeiten.'], $de['dndnode_1']['completion']['information']);
        $this->assertSame(['diesen Kurs erfolgreich bearbeiten'], $de['dndnode_1']['completion']['before']);
        $this->assertSame(
            ['condition_1_feedback' => 'Abschluss des Vorgängers „Kurs A“'],
            $de['dndnode_2']['restriction']['before_active']
        );
        $all = json_encode($de, JSON_UNESCAPED_UNICODE);
        foreach (['Complete', 'complete', 'successfully', 'Completion', 'predecessor', ' and '] as $english) {
            $this->assertStringNotContainsString($english, $all, 'no English in a German path');
        }
        $this->assertDoesNotMatchRegularExpression('/\{[a-z0-9_]+\}/i', $all, 'no raw placeholder');
    }

    /**
     * The other way round: a German teacher's session does not turn an
     * English learner's path German.
     */
    public function test_a_german_session_does_not_change_an_english_learners_path(): void {
        $this->mock_clock_with_frozen(self::START - HOURSECS);
        $learner = $this->learner('en');
        $this->setAdminUser();
        force_current_language('de');

        $json = $this->stored($this->recompute($learner, $this->tree()));

        $this->assertSame('de', current_language());
        $this->assertSame('en', $json['feedback_lang']);
        $en = $this->texts($json);
        $this->assertSame(['Complete this course.'], $en['dndnode_1']['completion']['information']);
        $this->assertSame(
            ['condition_1_feedback' => 'Completion of the predecessor „Kurs A“'],
            $en['dndnode_2']['restriction']['before_active']
        );
        $this->assertStringNotContainsString('Vorgänger', json_encode($en, JSON_UNESCAPED_UNICODE));
    }

    /**
     * The regression that was measured: the learner themselves triggers the
     * recompute, in their German session, on a path whose stored copies are
     * English. Before: "Complete diesen Kurs", marked as German.
     */
    public function test_the_learners_own_action_does_not_mix_languages(): void {
        $this->mock_clock_with_frozen(self::START - HOURSECS);
        $learner = $this->learner('de');
        $this->setUser($learner);
        $this->assertSame('de', current_language());

        $de = $this->texts($this->stored($this->recompute($learner, $this->tree())));

        $this->assertSame(['diesen Kurs bearbeiten.'], $de['dndnode_2']['completion']['information']);
        $this->assertSame(['diesen Kurs erfolgreich bearbeiten'], $de['dndnode_2']['completion']['before']);
    }

    /**
     * What the author wrote is what every learner reads - after a recompute
     * and after the re-render for a viewer with another language.
     */
    public function test_the_authors_own_text_is_kept_in_every_language(): void {
        $this->mock_clock_with_frozen(self::START - HOURSECS);
        foreach (['en', 'de'] as $lang) {
            $learner = $this->learner($lang);
            $this->setAdminUser();
            $id = $this->recompute($learner, $this->tree());
            $stored = $this->texts($this->stored($id));
            $this->assertSame(
                ['condition_1_feedback' => self::AUTHOR_TEXT],
                $stored['dndnode_4']['restriction']['before_active'],
                $lang . ': after the recompute'
            );
            // A viewer with the OTHER language.
            force_current_language($lang === 'en' ? 'de' : 'en');
            $rendered = $this->texts(json_decode(
                relation_update::rerender_feedback_language(json_encode($this->stored($id)), $learner->id),
                true
            ));
            force_current_language('');
            $this->assertSame(
                ['condition_1_feedback' => self::AUTHOR_TEXT],
                $rendered['dndnode_4']['restriction']['before_active'],
                $lang . ': after the re-render for the other language'
            );
            $this->assertSame(self::AUTHOR_TEXT, $rendered['dndnode_4']['restriction']['before']['condition_1_feedback']);
            // The neighbour without a text of its own follows the viewer.
            $this->assertSame(
                ['condition_1_feedback' => $lang === 'en'
                    ? 'Abschluss des Vorgängers „Kurs A“'
                    : 'Completion of the predecessor „Kurs A“'],
                $rendered['dndnode_2']['restriction']['before_active']
            );
        }
    }

    /**
     * Recompute in one language, re-render at fetch time for the other: the
     * texts are exactly those of a recompute in that other language. In three
     * states of the path, both directions.
     *
     * @dataProvider states
     * @param int $now The instant.
     * @param bool $completed Whether the learner has completed course A.
     */
    public function test_rerender_equals_recompute_in_that_language(int $now, bool $completed): void {
        $this->mock_clock_with_frozen($now);
        foreach ([['en', 'de'], ['de', 'en']] as [$stored, $viewed]) {
            $paths = [];
            foreach ([$stored, $viewed] as $lang) {
                $learner = $this->learner($lang);
                if ($completed) {
                    $completion = new \completion_completion(['course' => $this->courses[0], 'userid' => $learner->id]);
                    $completion->mark_complete($now - 60);
                }
                $this->setAdminUser();
                $paths[$lang] = [$learner, $this->recompute($learner, $this->tree())];
            }
            // The path stored in one language, fetched by a viewer with the other...
            [$learner, $id] = $paths[$stored];
            force_current_language($viewed);
            $rendered = $this->texts(json_decode(
                relation_update::rerender_feedback_language(json_encode($this->stored($id)), $learner->id),
                true
            ));
            force_current_language('');
            // The state really is the one this case is about.
            $this->assertSame(
                $completed ? 'completed' : 'accessible',
                $this->stored($id)['user_path_relation']['dndnode_1']['feedback']['status']
            );
            // ... reads what a learner with that language has stored.
            $expected = $this->texts($this->stored($paths[$viewed][1]));
            $this->assertSame($expected, $rendered, "stored in {$stored}, viewed in {$viewed}");
        }
    }

    /**
     * States of the path.
     *
     * @return array[]
     */
    public static function states(): array {
        return [
            'before the window, nothing completed' => [self::START - HOURSECS, false],
            'before the window, predecessor completed' => [self::START - HOURSECS, true],
            'inside the window, nothing completed' => [self::START + 60, false],
            'inside the window, predecessor completed' => [self::START + 60, true],
            'after the window, predecessor completed' => [self::END + 60, true],
        ];
    }

    /**
     * #590, 1: a node locked behind a FIXED window is not told "From
     * subscription you have access until ..." - neither before the window nor
     * inside it while another condition is missing.
     */
    public function test_a_fixed_window_does_not_promise_access_from_subscription(): void {
        $learner = $this->learner('en');
        $this->setAdminUser();
        foreach ([self::START - HOURSECS, self::START + 60] as $now) {
            $this->mock_clock_with_frozen($now);
            $node = $this->texts($this->stored($this->recompute($learner, $this->tree())))['dndnode_3'];
            $this->assertNull($node['restriction']['before_timed'], 'no "until" sentence on a locked fixed window');
            $this->assertNotSame([], $node['restriction']['before_active'], 'the node is locked and says why');
        }
    }

    /**
     * #590, 1, the other side: once the node is open, the end of the window is named.
     */
    public function test_an_open_window_names_its_end(): void {
        $this->mock_clock_with_frozen(self::START + 60);
        $learner = $this->learner('en');
        $completion = new \completion_completion(['course' => $this->courses[0], 'userid' => $learner->id]);
        $completion->mark_complete(self::START);
        $this->setAdminUser();
        $node = $this->texts($this->stored($this->recompute($learner, $this->tree())))['dndnode_3'];
        $this->assertSame('You have access to this node until 15/01/30, 12:00.', $node['restriction']['inbetween_timed']);
    }

    /**
     * #590, 2: a window open at one end reads as a sentence, in both languages.
     */
    public function test_a_window_open_at_one_end_reads_as_a_sentence(): void {
        $this->mock_clock_with_frozen(self::START - HOURSECS);
        $expected = [
            'en' => ['15/01/30, 10:00 to open end', 'now to 15/01/30, 12:00'],
            'de' => ['15/01/30, 10:00 bis auf Weiteres', 'sofort bis 15/01/30, 12:00'],
        ];
        foreach ($expected as $lang => [$noend, $nostart]) {
            $tree = ['tree' => ['edges' => [], 'nodes' => [
                $this->node('dndnode_1', 0, 'Kurs A', $this->condition(
                    'restriction',
                    'condition_1',
                    'timed',
                    ['start' => self::START, 'end' => null],
                    null
                )),
                $this->node('dndnode_2', 1, 'Kurs B', $this->condition(
                    'restriction',
                    'condition_1',
                    'timed',
                    ['start' => null, 'end' => self::END],
                    null
                )),
            ]], 'modules' => null];
            $learner = $this->learner($lang);
            $this->setAdminUser();
            $texts = $this->texts($this->stored($this->recompute($learner, $tree)));
            $this->assertSame(['condition_1_feedback' => $noend], $texts['dndnode_1']['restriction']['information'], $lang);
            $this->assertSame(['condition_1_feedback' => $nostart], $texts['dndnode_2']['restriction']['information'], $lang);
        }
    }

    /**
     * #590, 3: the info symbol of a manual completion names a criterion, not
     * a fact - also on a path that still stores the earlier wording; and an
     * information text the author wrote stays theirs.
     */
    public function test_manual_completion_information(): void {
        $this->mock_clock_with_frozen(self::START - HOURSECS);
        $manual = function (array $data): array {
            $nodes = $this->condition('completion', 'condition_1', 'manual', null, null);
            // What a path built before the change carries.
            $nodes[0]['data']['information'] = 'manual completion recorded';
            $nodes[1]['data']['information'] = 'manual completion recorded';
            $nodes[0]['data'] = array_merge($nodes[0]['data'], $data);
            return $nodes;
        };
        $tree = ['tree' => ['edges' => [], 'nodes' => [
            $this->node('dndnode_1', 0, 'Kurs A', [], $manual([])),
            $this->node('dndnode_2', 1, 'Kurs B', [], $manual([
                'information' => 'Hand in your portfolio',
                'informationedited' => true,
            ])),
        ]], 'modules' => null];
        $defaults = ['en' => 'Manual completion by the teacher', 'de' => 'Manueller Abschluss durch die Lehrkraft'];
        foreach ($defaults as $lang => $default) {
            $learner = $this->learner($lang);
            $this->setAdminUser();
            $texts = $this->texts($this->stored($this->recompute($learner, $tree)));
            $this->assertSame([$default], $texts['dndnode_1']['completion']['information'], $lang);
            $this->assertSame(['Hand in your portfolio'], $texts['dndnode_2']['completion']['information'], $lang);
        }
    }
}
