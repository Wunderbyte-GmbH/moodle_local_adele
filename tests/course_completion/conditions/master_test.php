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

namespace local_adele\course_completion\conditions;

use advanced_testcase;
use local_adele\course_completion\conditions\master;

/**
 * Unit tests for the master class.
 *
 * @package     local_adele
 * @copyright   2023 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class master_test extends advanced_testcase {

    /**
     * Test get_description method.
     * @covers \local_adele\course_completion\conditions\master
     */
    public function test_get_description() {
        $this->resetAfterTest(true);

        $instance = new master();
        $description = $instance->get_description();

        $this->assertIsArray($description);
        $this->assertArrayHasKey('id', $description);
        $this->assertArrayHasKey('name', $description);
        $this->assertArrayHasKey('description', $description);
        $this->assertEquals(COURSES_COND_MASTER, $description['id']);
        $this->assertEquals('master', $description['label']);
    }

    /**
     * Test get_completion_status method.
     * @covers \local_adele\course_completion\conditions\master
     */
    public function test_get_completion_status() {
        $this->resetAfterTest(true);

        $instance = new master();
        $node = [
            'data' => [
                'completion' => [
                    'master' => ['completion' => true],
                ],
            ],
        ];
        $userid = 1;
        $status = $instance->get_completion_status($node, $userid);

        $this->assertTrue($status);

        $node['data']['completion']['master']['completion'] = false;
        $status = $instance->get_completion_status($node, $userid);

        $this->assertFalse($status);
    }
}
