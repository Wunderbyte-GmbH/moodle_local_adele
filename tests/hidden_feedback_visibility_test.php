<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_adele;

use advanced_testcase;
use local_adele\event\user_path_updated;

/**
 * The "Verbergen" (visibility) toggle on a feedback node must ONLY hide the
 * displayed feedback text and NEVER change the actual lock/completion verdict
 * (GitHub #474).
 *
 * The recompute engine built restriction and completion feedback with two
 * different visibility guards: the restriction loop honoured visibility
 * (visibility ?? false) but the completion loop did not (isset(...visibility),
 * always true because the key is always present), and the restriction
 * before_active (student speech bubble) fell back to the default description
 * even when hidden. Result: hidden criteria still surfaced on one or both of
 * the two display surfaces (info-symbol / speech bubble), inconsistently.
 *
 * This test recomputes the SAME locked node twice - once with the feedback
 * nodes visible, once hidden - and asserts:
 *  - visible: the feedback text is present on both surfaces;
 *  - hidden:  the feedback text is gone on both surfaces;
 *  - the status fields (status / status_restriction / status_completion) that
 *    drive locking and node colour are byte-for-byte identical either way.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\relation_update::updated_single
 * @covers \local_adele\relation_update::getfeedback
 */
final class hidden_feedback_visibility_test extends advanced_testcase {
    /** @var int */
    private $c1;
    /** @var int */
    private $c2;

    /** @var string Custom restriction feedback text (shown in the speech bubble while locked). */
    private const RESTRICTION_MSG = 'restriction custom hidden text';
    /** @var string Custom completion information text (shown behind the info symbol). */
    private const COMPLETION_INFO = 'completion custom info text';
    /** @var string Custom "completed" message (shown in the bubble once the node is done). */
    private const COMPLETED_MSG = 'well done you completed this node';

    /**
     * Two-node branch. Node B is locked by a manual restriction and carries a
     * manual completion criterion. Both the restriction and completion feedback
     * nodes are wired and set to CUSTOM mode; their visibility is parameterised.
     *
     * @param bool $visible Whether the feedback nodes are visible (eye on) or hidden ("Verbergen").
     * @return array Decoded learning-path tree json structure.
     */
    private function build_tree(bool $visible): array {
        return [
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1', 'type' => 'circle',
                        'parentCourse' => ['starting_node'], 'childCourse' => ['dndnode_2'],
                        'data' => ['course_node_id' => [(string) $this->c1], 'fullname' => 'Node A'],
                        'restriction' => ['nodes' => [], 'edges' => []],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                    [
                        'id' => 'dndnode_2', 'type' => 'circle',
                        'parentCourse' => ['dndnode_1'], 'childCourse' => [],
                        'data' => ['course_node_id' => [(string) $this->c2], 'fullname' => 'Node B'],
                        'restriction' => [
                            'nodes' => [
                                [
                                    'id' => 'condition_1',
                                    'parentCondition' => ['starting_condition'],
                                    'childCondition' => ['condition_1_feedback'],
                                    'data' => [
                                        'id' => 150,
                                        'label' => 'manual',
                                        'description_before' => 'must be released manually',
                                    ],
                                ],
                                [
                                    'id' => 'condition_1_feedback',
                                    'parentCondition' => ['condition_1'],
                                    'childCondition' => [],
                                    'data' => [
                                        'label' => 'feedback',
                                        'visibility' => $visible,
                                        'feedback_before_checkmark' => false,
                                        'feedback_before' => self::RESTRICTION_MSG,
                                        'information' => 'restriction info',
                                    ],
                                ],
                            ],
                            'edges' => [],
                        ],
                        'completion' => [
                            'nodes' => [
                                [
                                    'id' => 'condition_1',
                                    'parentCondition' => ['starting_condition'],
                                    'childCondition' => ['condition_1_feedback'],
                                    'data' => [
                                        'id' => 160,
                                        'label' => 'manual',
                                        'description_before' => 'complete this manually',
                                        // The author's own information text, as the
                                        // editor stores it (manual_check.vue): on the
                                        // condition, marked as edited.
                                        'information' => self::COMPLETION_INFO,
                                        'informationedited' => true,
                                    ],
                                ],
                                [
                                    'id' => 'condition_1_feedback',
                                    'parentCondition' => ['condition_1'],
                                    'childCondition' => [],
                                    'data' => [
                                        'label' => 'feedback',
                                        'visibility' => $visible,
                                        'feedback_before_checkmark' => false,
                                        'feedback_before' => 'do this to complete',
                                        'feedback_inbetween_checkmark' => false,
                                        'information' => self::COMPLETION_INFO,
                                    ],
                                ],
                            ],
                            'edges' => [],
                        ],
                    ],
                ],
                'edges' => [['source' => 'dndnode_1', 'target' => 'dndnode_2', 'data' => []]],
            ],
            'modules' => null,
        ];
    }

    /**
     * Recompute the branch and return the feedback envelope for the locked node B.
     *
     * @param bool $visible Whether the feedback nodes are visible or hidden.
     * @return array The user_path_relation entry for dndnode_2.
     */
    private function recompute(bool $visible): array {
        return $this->recompute_tree($this->build_tree($visible), 'dndnode_2');
    }

    /**
     * Insert a learning path + user path for the given tree, fire the recompute
     * event and return the resulting user_path_relation entry for one node.
     *
     * @param array $tree The learning-path tree json structure.
     * @param string $nodeid The node id whose relation entry to return.
     * @return array The user_path_relation entry for $nodeid.
     */
    private function recompute_tree(array $tree, string $nodeid): array {
        global $DB;

        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Visibility LP',
            'json' => json_encode($tree),
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => 2,
        ]);
        $id = $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $this->userid, 'course_id' => $this->c1, 'learning_path_id' => $lpid,
            'status' => 'active', 'json' => json_encode($tree),
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $this->userid,
        ]);
        $userpath = $DB->get_record('local_adele_path_user', ['id' => $id]);
        $userpath->json = json_decode($userpath->json, true);

        $event = user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => \context_system::instance(),
            'other' => ['userpath' => $userpath],
        ]);
        relation_update::updated_single($event);

        $json = json_decode($DB->get_record('local_adele_path_user', ['id' => $id])->json, true);
        return $json['user_path_relation'][$nodeid];
    }

    /** @var int */
    private $userid;

    /**
     * Hiding a feedback node removes its text from BOTH display surfaces while
     * leaving the lock/completion verdict (and thus node colour) unchanged.
     */
    public function test_visibility_hides_feedback_but_not_lock_state(): void {
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $this->c1 = (int) $gen->create_course()->id;
        $this->c2 = (int) $gen->create_course()->id;
        $user = $gen->create_user();
        $this->userid = (int) $user->id;
        $this->setUser($user);

        $visiblerel = $this->recompute(true);
        $hiddenrel = $this->recompute(false);
        $visible = $visiblerel['feedback'];
        $hidden = $hiddenrel['feedback'];

        // Speech bubble (restriction.before_active) - the locked-state student message.
        $visiblebubble = implode(' ', (array) ($visible['restriction']['before_active'] ?? []));
        $hiddenbubble = implode(' ', (array) ($hidden['restriction']['before_active'] ?? []));
        $this->assertStringContainsString(
            self::RESTRICTION_MSG,
            $visiblebubble,
            'Visible restriction feedback must appear in the speech bubble.'
        );
        $this->assertStringNotContainsString(
            self::RESTRICTION_MSG,
            $hiddenbubble,
            'Hidden restriction feedback must NOT appear in the speech bubble.'
        );
        $this->assertStringNotContainsString(
            'must be released manually',
            $hiddenbubble,
            'Hidden restriction feedback must not fall back to the default description.'
        );

        // Info symbol (completion.information) - the completion criteria list.
        $visibleinfo = implode(' ', (array) ($visible['completion']['information'] ?? []));
        $hiddeninfo = implode(' ', (array) ($hidden['completion']['information'] ?? []));
        $this->assertStringContainsString(
            self::COMPLETION_INFO,
            $visibleinfo,
            'Visible completion information must appear behind the info symbol.'
        );
        $this->assertStringNotContainsString(
            self::COMPLETION_INFO,
            $hiddeninfo,
            'Hidden completion information must NOT appear behind the info symbol.'
        );

        // THE CORE GUARANTEE (#474): visibility must never move ANY status. Every
        // status field must mirror the actual node state at all times - a locked
        // node stays 'before' whether or not its feedback text is hidden. In
        // particular status_restriction must NOT flip 'before' -> 'after' just
        // because the feedback is hidden (that would tell the student a still-locked
        // node is accessible). We assert all three status fields plus the underlying
        // condition-evaluation results are byte-for-byte identical.
        foreach (['status', 'status_restriction', 'status_completion'] as $field) {
            $this->assertSame(
                $visible[$field] ?? null,
                $hidden[$field] ?? null,
                "Visibility must not change {$field} - it must always mirror the real node state."
            );
        }
        foreach (['singlerestrictionnode', 'allrestrictioncriteria', 'completionnode', 'singlecompletionnode'] as $field) {
            $this->assertEquals(
                $visiblerel[$field] ?? null,
                $hiddenrel[$field] ?? null,
                "Visibility must not change {$field} (the condition-evaluation result that drives locking)."
            );
        }

        // The node is genuinely locked, so status_restriction must be 'before' in
        // BOTH runs - proving the hide only removed the text, not the lock state.
        $this->assertSame(
            'before',
            $visible['status_restriction'] ?? null,
            'Node B is locked by the manual restriction, so status_restriction must be before.'
        );
        $this->assertSame(
            'before',
            $hidden['status_restriction'] ?? null,
            'Hiding the feedback must NOT flip the locked node to after.'
        );

        // Sanity: we actually exercised the restriction speech-bubble surface -
        // before_active carried real text in the visible run.
        $this->assertNotSame(
            '',
            $visiblebubble,
            'The visible run must populate before_active, or the test proves nothing.'
        );
    }

    /**
     * Single accessible node whose manual completion is already MET. Its completion
     * feedback node carries a custom "completed" message, with parameterised visibility.
     *
     * @param bool $visible Whether the completion feedback node is visible or hidden.
     * @return array Decoded learning-path tree json structure.
     */
    private function build_completed_tree(bool $visible): array {
        return [
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1', 'type' => 'circle',
                        'parentCourse' => ['starting_node'], 'childCourse' => [],
                        'data' => [
                            'course_node_id' => [(string) $this->c1],
                            'fullname' => 'Node A',
                            'manualcompletion' => true,
                            'manualcompletionvalue' => true,
                        ],
                        'restriction' => ['nodes' => [], 'edges' => []],
                        'completion' => [
                            'nodes' => [
                                [
                                    'id' => 'condition_1',
                                    'parentCondition' => ['starting_condition'],
                                    'childCondition' => ['condition_1_feedback'],
                                    'data' => [
                                        'id' => 170,
                                        'label' => 'manual',
                                        'description_before' => 'complete this manually',
                                    ],
                                ],
                                [
                                    'id' => 'condition_1_feedback',
                                    'parentCondition' => ['condition_1'],
                                    'childCondition' => [],
                                    'data' => [
                                        'label' => 'feedback',
                                        'visibility' => $visible,
                                        'feedback_after_checkmark' => false,
                                        'feedback_after' => self::COMPLETED_MSG,
                                        'information' => 'completion info',
                                    ],
                                ],
                            ],
                            'edges' => [],
                        ],
                    ],
                ],
                'edges' => [],
            ],
            'modules' => null,
        ];
    }

    /**
     * Hiding the feedback of an ALREADY-COMPLETED node must remove its "completed"
     * message from the speech bubble, yet the node must stay 'completed' (green) -
     * the completion verdict is independent of the feedback text (#474).
     */
    public function test_completed_node_hides_message_but_stays_completed(): void {
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $this->c1 = (int) $gen->create_course()->id;
        $user = $gen->create_user();
        $this->userid = (int) $user->id;
        $this->setUser($user);

        $visible = $this->recompute_tree($this->build_completed_tree(true), 'dndnode_1')['feedback'];
        $hidden = $this->recompute_tree($this->build_completed_tree(false), 'dndnode_1')['feedback'];

        // The completed message shown in the bubble (completion.after, promoted from
        // after_all once the criterion is met).
        $visibleafter = implode(' ', (array) ($visible['completion']['after'] ?? []));
        $hiddenafter = implode(' ', (array) ($hidden['completion']['after'] ?? []));
        $this->assertStringContainsString(
            self::COMPLETED_MSG,
            $visibleafter,
            'Visible completed feedback must appear in the speech bubble.'
        );
        $this->assertStringNotContainsString(
            self::COMPLETED_MSG,
            $hiddenafter,
            'Hidden completed feedback must NOT appear in the speech bubble.'
        );

        // The completion verdict must be identical and must stay completed either way.
        $this->assertSame(
            'after',
            $visible['status_completion'] ?? null,
            'The node must be recognised as completed (status_completion=after).'
        );
        $this->assertSame(
            $visible['status_completion'] ?? null,
            $hidden['status_completion'] ?? null,
            'Hiding the feedback must not change status_completion.'
        );
        $this->assertSame(
            'completed',
            $visible['status'] ?? null,
            'A completed node must have overall status=completed (drives the green colour).'
        );
        $this->assertSame(
            'completed',
            $hidden['status'] ?? null,
            'Hiding the completed feedback must NOT drop the completed status/colour.'
        );
    }
}
