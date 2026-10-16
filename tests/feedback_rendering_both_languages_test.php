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
 * Comprehensive rendering tests for the learner-facing node feedback + info-symbol text,
 * verified in BOTH shipped languages (de + en). The student view derives its feedback from
 * these strings, so they must never leave an unresolved {placeholder}, never render empty
 * where a requirement is needed, and read as the expected sentence in each language.
 *
 * Two layers:
 *  - VALUE level: assert each key's raw template per language by including the plugin's
 *    lang/<lang>/local_adele.php directly (the de core pack is not installed in the test DB,
 *    so get_string() cannot return German).
 *  - RESOLUTION level: recompute real trees (default en) and assert the composed feedback -
 *    the count-aware parent_courses phrasing, the course_completed info-symbol, AND/OR
 *    composition, and a global "no unresolved placeholder" sweep.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\relation_update::getfeedback
 * @covers \local_adele\course_restriction\conditions\parent_courses::get_restriction_status
 */
final class feedback_rendering_both_languages_test extends advanced_testcase {
    /** @var int[] Four completion-enabled courses. */
    private array $courses = [];
    /** @var int */
    private int $userid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        for ($i = 0; $i < 4; $i++) {
            $this->courses[] = (int) $gen->create_course(['enablecompletion' => 1])->id;
        }
        $user = $gen->create_user();
        $this->userid = (int) $user->id;
        $this->setUser($user);
        foreach ($this->courses as $c) {
            $gen->enrol_user($this->userid, $c);
        }
    }

    /**
     * Load the raw $string template array from a lang file. get_string() cannot be used for
     * German here (the de core pack is not installed in the test DB, so it falls back to en),
     * so we read the plugin's lang/<lang>/local_adele.php directly to assert the TEMPLATES.
     *
     * @param string $lang
     * @return array
     */
    private function langstrings(string $lang): array {
        $string = [];
        include(__DIR__ . '/../lang/' . $lang . '/local_adele.php');
        return $string;
    }

    // Value-level tests: the raw templates read correctly in each language.

    /**
     * The count-aware parent_courses fragments must read correctly in both languages, for the
     * singular / all / K-of-N forms confirmed with the customer.
     *
     * @return void
     */
    public function test_parent_requirement_fragments_both_languages(): void {
        $de = $this->langstrings('de');
        $this->assertSame('Abschluss des Vorgängers {$a}', $de['course_restriction_parent_single']);
        $this->assertSame('Abschluss der Vorgänger {$a}', $de['course_restriction_parent_all']);
        $this->assertSame(
            'Abschluss von {$a->numb_courses} der folgenden Vorgänger: {$a->node_list}',
            $de['course_restriction_parent_kofn']
        );

        $en = $this->langstrings('en');
        $this->assertSame('Completion of the predecessor {$a}', $en['course_restriction_parent_single']);
        $this->assertSame('Completion of the predecessors {$a}', $en['course_restriction_parent_all']);
        $this->assertSame(
            'Completion of {$a->numb_courses} of the following predecessors: {$a->node_list}',
            $en['course_restriction_parent_kofn']
        );
    }

    /**
     * Key learner-facing templates must have the confirmed wording, terminology ("Kurs/Stapel",
     * no stray "Node"), correct register, and English that is actually English.
     *
     * @return void
     */
    public function test_key_learner_templates_both_languages(): void {
        $de = $this->langstrings('de');
        $en = $this->langstrings('en');

        // Status header (locked).
        $this->assertSame(
            'Sie haben keinen Zugang zu diesem Kurs/Stapel. Eine Freischaltung erfolgt, wenn:',
            $de['node_access_restriction_before']
        );
        $this->assertSame(
            'You do not have access to this course/stack. It will be unlocked when:',
            $en['node_access_restriction_before']
        );
        // No stray "Node" left in the learner-facing German headers.
        $headers = ['node_access_restriction_before', 'node_access_restriction_after', 'node_access_completion_before',
            'node_access_completion_after', 'node_access_not_accessible'];
        foreach ($headers as $key) {
            $this->assertStringNotContainsStringIgnoringCase('Node', $de[$key], "de $key still says Node");
        }
        // Info-symbol templates.
        $this->assertSame('{item} bearbeiten.', $de['course_information_condition_course_completed']);
        $this->assertSame('Complete {item}.', $en['course_information_condition_course_completed']);
        $this->assertSame('diesen Kurs', $de['course_description_before_condition_course_completed_item']);
        $this->assertSame('this course', $en['course_description_before_condition_course_completed_item']);
        // The stray English in the de file is gone.
        $this->assertStringNotContainsStringIgnoringCase(
            'Finish Quiz',
            $de['course_information_condition_modquiz'],
            'de modquiz info must be German.'
        );
    }

    /**
     * No learner-facing template may carry a double space or a trailing space in either language.
     *
     * @return void
     */
    public function test_no_stray_whitespace_in_learner_templates(): void {
        $keys = [
            'node_access_restriction_before', 'node_access_restriction_after', 'node_access_restriction_inbetween',
            'node_access_completion_before', 'node_access_completion_inbetween', 'node_access_completion_after',
            'course_information_condition_course_completed', 'course_information_condition_specific_course',
            'course_description_before_condition_course_completed',
            'course_restriction_parent_single', 'course_restriction_parent_all', 'course_restriction_parent_kofn',
        ];
        foreach (['de', 'en'] as $lang) {
            $strings = $this->langstrings($lang);
            foreach ($keys as $key) {
                $value = $strings[$key];
                $this->assertStringNotContainsString('  ', $value, "[$lang] $key has a double space: '$value'");
                $this->assertSame(rtrim($value), $value, "[$lang] $key has a trailing space: '$value'");
            }
        }
    }

    // Resolution-level tests: the pipeline composes the strings correctly (default en).

    /**
     * Recompute a tree and return the persisted user_path_relation map.
     *
     * @param array $tree
     * @return array
     */
    private function recompute(array $tree): array {
        global $DB;
        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'rendering LP', 'json' => json_encode($tree),
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $this->userid,
        ]);
        $id = $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $this->userid, 'course_id' => $this->courses[0], 'learning_path_id' => $lpid,
            'status' => 'active', 'json' => json_encode($tree),
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $this->userid,
        ]);
        $userpath = $DB->get_record('local_adele_path_user', ['id' => $id]);
        $userpath->json = json_decode($userpath->json, true);
        $event = user_path_updated::create([
            'objectid' => $userpath->id, 'context' => context_system::instance(),
            'other' => ['userpath' => $userpath],
        ]);
        relation_update::updated_single($event);
        return json_decode($DB->get_record('local_adele_path_user', ['id' => $id])->json, true)['user_path_relation'];
    }

    /**
     * A completion node (course_completed over $courses, min $min, feedback info unset so the
     * info-symbol falls back to the lang default).
     *
     * @param string $id
     * @param int[] $courses
     * @param int $min
     * @param string $fullname
     * @return array
     */
    private function completion_node(string $id, array $courses, int $min, string $fullname): array {
        return [
            'id' => $id, 'type' => 'circle',
            'parentCourse' => ['starting_node'], 'childCourse' => [],
            'data' => ['course_node_id' => array_map('strval', $courses), 'fullname' => $fullname],
            'restriction' => ['nodes' => [], 'edges' => []],
            'completion' => [
                'nodes' => [
                    ['id' => 'condition_1', 'parentCondition' => ['starting_condition'],
                        'childCondition' => ['condition_1_feedback'],
                        'data' => ['id' => 170, 'label' => 'course_completed',
                            'value' => ['min_courses' => $min], 'description_before' => 'complete']],
                    ['id' => 'condition_1_feedback', 'parentCondition' => ['condition_1'], 'childCondition' => [],
                        'data' => ['label' => 'feedback', 'visibility' => true,
                            'feedback_before' => '{item} erfolgreich bearbeiten']],
                ],
                'edges' => [],
            ],
        ];
    }

    /**
     * A tree of $numparents standalone parent nodes plus one child locked behind a
     * parent_courses restriction referencing them all, min $min. The child's restriction
     * feedback node carries no information field (default fallback → {node_requirement}).
     *
     * @param int $numparents
     * @param int $min
     * @return array
     */
    private function build_parent_tree(int $numparents, int $min): array {
        $nodes = [];
        $parentids = [];
        for ($i = 0; $i < $numparents; $i++) {
            $pid = 'dndnode_' . ($i + 1);
            $parentids[] = $pid;
            $nodes[] = $this->completion_node($pid, [$this->courses[$i]], 1, 'Kurs ' . chr(65 + $i));
        }
        $nodes[] = [
            'id' => 'child', 'type' => 'circle',
            'parentCourse' => $parentids, 'childCourse' => [],
            'data' => ['course_node_id' => [(string) $this->courses[3]], 'fullname' => 'Child'],
            'restriction' => [
                'nodes' => [
                    ['id' => 'condition_1', 'parentCondition' => ['starting_condition'], 'childCondition' => [],
                        'data' => ['id' => 150, 'label' => 'parent_courses',
                            'value' => ['courses_id' => $parentids, 'min_courses' => $min],
                            'description_before' => 'parent']],
                    ['id' => 'condition_1_feedback', 'parentCondition' => null, 'childCondition' => null,
                        'data' => ['label' => 'feedback', 'visibility' => true,
                            'feedback_before_checkmark' => true, 'feedback_before' => 'parent']],
                ],
                'edges' => [],
            ],
            'completion' => ['nodes' => [], 'edges' => []],
        ];
        return ['tree' => ['nodes' => $nodes, 'edges' => []], 'modules' => null];
    }

    /**
     * The parent_courses info-symbol renders the exact count-aware English phrase for the
     * singular / all / K-of-N cases (proves build_node_requirement picks the right form).
     *
     * @return void
     */
    public function test_parent_courses_count_aware_resolution_en(): void {
        $cases = [
            [1, 1, 'Completion of the predecessor „Kurs A“'],
            [2, 2, 'Completion of the predecessors „Kurs A“ and „Kurs B“'],
            [2, 1, 'Completion of 1 of the following predecessors: „Kurs A“, „Kurs B“'],
            [3, 2, 'Completion of 2 of the following predecessors: „Kurs A“, „Kurs B“, „Kurs C“'],
        ];
        foreach ($cases as [$n, $k, $expected]) {
            $relation = $this->recompute($this->build_parent_tree($n, $k));
            $info = array_values((array) ($relation['child']['feedback']['restriction']['information'] ?? []));
            $this->assertSame($expected, $info[0] ?? '', "parent_courses N=$n K=$k info-symbol");
        }
    }

    /**
     * The course_completed info-symbol renders the resolved requirement (single + multi),
     * with no dropped word and no leftover placeholder.
     *
     * @return void
     */
    public function test_course_completed_info_symbol_resolution_en(): void {
        $single = $this->recompute(['tree' => ['nodes' => [
            $this->completion_node('dndnode_1', [$this->courses[0]], 1, 'Solo'),
        ], 'edges' => []], 'modules' => null]);
        $this->assertSame(
            'Complete this course.',
            $single['dndnode_1']['feedback']['completion']['information'][0] ?? ''
        );

        $multi = $this->recompute(['tree' => ['nodes' => [
            $this->completion_node('dndnode_1', [$this->courses[0], $this->courses[1], $this->courses[2]], 1, 'Stack'),
        ], 'edges' => []], 'modules' => null]);
        $this->assertSame(
            'Complete 1 of 3 courses.',
            $multi['dndnode_1']['feedback']['completion']['information'][0] ?? ''
        );
    }

    /**
     * OR composition: two alternative parent_courses columns (each a starting_condition) yield
     * two separate before_valid requirement entries for a locked node (the Vue layer joins them
     * with "oder"/"or").
     *
     * @return void
     */
    public function test_or_columns_yield_two_requirements(): void {
        $tree = $this->build_parent_tree(1, 1);
        // Add a second, independent restriction column referencing a different parent.
        $tree['tree']['nodes'][] = $this->completion_node('dndnode_2', [$this->courses[1]], 1, 'Kurs B');
        // Add a second parent_courses column to the child node (referencing dndnode_2).
        foreach ($tree['tree']['nodes'] as &$n) {
            if ($n['id'] === 'child') {
                $n['restriction']['nodes'][] = ['id' => 'condition_2', 'parentCondition' => ['starting_condition'],
                    'childCondition' => [], 'data' => ['id' => 151, 'label' => 'parent_courses',
                        'value' => ['courses_id' => ['dndnode_2'], 'min_courses' => 1], 'description_before' => 'p']];
                $n['restriction']['nodes'][] = ['id' => 'condition_2_feedback', 'parentCondition' => null,
                    'childCondition' => null, 'data' => ['label' => 'feedback', 'visibility' => true,
                        'feedback_before' => 'p']];
            }
        }
        unset($n);
        $relation = $this->recompute($tree);
        $bv = (array) ($relation['child']['feedback']['restriction']['before_valid'] ?? []);
        $this->assertGreaterThanOrEqual(2, count(array_filter($bv)), 'Two OR columns must give two requirements.');
    }

    /**
     * Global sweep: no rendered feedback/info string in a mixed tree may contain an unresolved
     * {placeholder}.
     *
     * @return void
     */
    public function test_no_unresolved_placeholders(): void {
        $relation = $this->recompute($this->build_parent_tree(2, 1));
        array_walk_recursive($relation, function ($value) {
            if (\is_string($value) && $value !== '') {
                $this->assertDoesNotMatchRegularExpression(
                    '/\{[a-z0-9_]+\}/i',
                    $value,
                    "Unresolved placeholder in rendered feedback: '$value'"
                );
            }
        });
    }

    /**
     * The fetch-time re-render (#493) must produce the info-symbol from the condition's CURRENT
     * language default, ignoring both the stale stored text and a per-node template authored in
     * another language - so info + feedback follow a language switch. Simulated here with a
     * stored user_path_relation whose info text and per-node template are deliberately foreign;
     * after rerender_feedback_language() the info-symbol is the current (en) default.
     *
     * @return void
     */
    public function test_fetch_rerender_forces_current_language_info_symbol(): void {
        $node = [
            'id' => 'dndnode_1', 'type' => 'circle',
            'parentCourse' => ['starting_node'], 'childCourse' => [],
            'data' => ['course_node_id' => [(string) $this->courses[0]], 'fullname' => 'Solo'],
            'restriction' => ['nodes' => [], 'edges' => []],
            'completion' => ['nodes' => [
                ['id' => 'condition_1', 'parentCondition' => ['starting_condition'],
                    'childCondition' => ['condition_1_feedback'],
                    'data' => ['id' => 170, 'label' => 'course_completed',
                        'value' => ['min_courses' => 1], 'description_before' => 'x']],
                ['id' => 'condition_1_feedback', 'parentCondition' => ['condition_1'], 'childCondition' => [],
                    // As the editor stores a feedback node left on "use default".
                    'data' => ['label' => 'feedback', 'visibility' => true, 'feedback_before_checkmark' => true,
                        'information' => 'FOREIGN {item} FOREIGN', 'feedback_before' => '{item}']],
            ], 'edges' => []],
        ];
        $json = json_encode([
            'tree' => ['nodes' => [$node], 'edges' => []],
            'user_path_relation' => ['dndnode_1' => ['feedback' => [
                'completion' => ['information' => ['STALE OLD TEXT'], 'before' => [],
                    'inbetween' => [], 'after' => [], 'after_all' => []],
                'restriction' => ['information' => [], 'before' => []],
            ]]],
            'modules' => null,
        ]);
        $out = json_decode(relation_update::rerender_feedback_language($json, $this->userid), true);
        $fb = $out['user_path_relation']['dndnode_1']['feedback'];
        // Info-symbol from the current-language default.
        $this->assertSame(
            ['Complete this course.'],
            $fb['completion']['information'] ?? [],
            'Fetch-time re-render must render the info-symbol from the current-language default.'
        );
        // The speech-bubble text must ALSO follow the language - rendered from the condition's
        // description_before default, not the foreign per-node template.
        $this->assertSame(
            ['successfully complete this course'],
            $fb['completion']['before'] ?? [],
            'Fetch-time re-render must render the speech-bubble text from the current-language default.'
        );
    }

    /**
     * Fetch-time re-render (#493) must translate the restriction speech-bubble requirement too:
     * a locked parent_courses node whose stored before text is in another language is re-rendered
     * from the condition default (the count-aware node_requirement) in the current language.
     *
     * @return void
     */
    public function test_fetch_rerender_translates_restriction_bubble(): void {
        // A course_completed parent plus a child locked behind a parent_courses restriction on it.
        $tree = $this->build_parent_tree(1, 1);
        // Seed a stored user_path_relation whose restriction before text is a FOREIGN template.
        $tree['user_path_relation'] = [
            'dndnode_1' => ['feedback' => [
                'completion' => ['information' => [''], 'before' => [], 'inbetween' => [], 'after' => [], 'after_all' => []],
                'restriction' => ['information' => [], 'before' => []],
            ]],
            'child' => ['feedback' => [
                'status_restriction' => 'before',
                'completion' => ['information' => [], 'before' => [], 'inbetween' => [], 'after' => [], 'after_all' => []],
                'restriction' => [
                    'information' => ['condition_1_feedback' => 'FREMD'],
                    'before' => ['condition_1_feedback' => 'FREMD template'],
                    'before_valid' => ['condition_1_feedback' => 'FREMD template'],
                    'before_active' => ['condition_1_feedback' => ''],
                ],
            ]],
        ];
        $out = json_decode(relation_update::rerender_feedback_language(json_encode($tree), $this->userid), true);
        $childrestriction = $out['user_path_relation']['child']['feedback']['restriction'];
        $this->assertSame(
            ['condition_1_feedback' => 'Completion of the predecessor „Kurs A“'],
            $childrestriction['before'],
            'Restriction bubble text must be re-rendered from the current-language condition default.'
        );
        $this->assertSame(
            'Completion of the predecessor „Kurs A“',
            $childrestriction['before_valid']['condition_1_feedback'] ?? '',
            'before_valid (what the locked bubble shows) must follow the language too.'
        );
    }
}
