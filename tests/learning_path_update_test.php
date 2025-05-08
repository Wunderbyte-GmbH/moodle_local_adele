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
 * Tests strategy
 *
 * @package    local_adele
 * @author Jacob Viertel
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use local_adele\learning_path_update;
use advanced_testcase;
use moodle_database;
use stdClass;

/**
 * Tests strategy
 *
 * @package    local_adele
 * @author Jacob Viertel
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_adele
 */
final class learning_path_update_test extends advanced_testcase {
    /**
     * Test the update_visibility method.
     *
     * @runInSeparateProcess
     */
    public function test_update_visibility(): void {
        global $DB;

        $DB = $this->createMock(moodle_database::class);
        $DB->expects($this->once())
            ->method('update_record')
            ->with(
                $this->equalTo('local_adele_learning_paths'),
                $this->callback(function ($data) {
                    return $data->id === 1 && $data->visibility === true;
                })
            )
            ->willReturn(true);

        $this->set_global_db($DB);

        $result = learning_path_update::update_visiblity(1, true);

        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test the update_animations method.
     *
     * @runInSeparateProcess
     */
    public function test_update_animations(): void {
        global $DB;
        $learningpathid = 1;
        $userid = 2;
        $nodeid = 'node-1';
        $animations = json_encode([
            'seenrestriction' => true,
            'seencompletion' => false,
        ]);
        $record = new stdClass();
        $record->id = 10;
        $record->json = json_encode([
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'node-1',
                        'data' => [
                            'animations' => new stdClass(),
                        ],
                    ],
                    [
                        'id' => 'node-2',
                        'data' => [
                            'animations' => new stdClass(),
                        ],
                    ],
                ],
            ],
        ]);
        // Mock the DB get_record method.
        $DB = $this->createMock(moodle_database::class);
        $DB->expects($this->once())
            ->method('get_record')
            ->with(
                'local_adele_path_user',
                [
                    'user_id' => $userid,
                    'learning_path_id' => $learningpathid,
                    'status' => 'active',
                ],
                'id, json'
            )
            ->willReturn($record);

        // Mock the DB update_record method.
        $DB->expects($this->once())
            ->method('update_record')
            ->with(
                'local_adele_path_user',
                $this->callback(function ($arg) use ($record) {
                    $json = json_decode($arg['json']);
                    return $arg['id'] == $record->id &&
                          $json->tree->nodes[0]->data->animations->seenrestriction == true &&
                          $json->tree->nodes[0]->data->animations->seencompletion == false;
                })
            )
            ->willReturn(true);

        // Run the update_animations function.
        $result = learning_path_update::update_animations(
            $learningpathid,
            $userid,
            $nodeid,
            $animations
        );
        // Assert the expected result.
        $this->assertEquals(['success' => true], $result);
    }

    /**
     * Helper method to replace the global $DB with a mock.
     *
     * @param \moodle_database $mockdb
     */
    protected function set_global_db($mockdb) {
        global $DB;
        $DB = $mockdb;
    }
}
