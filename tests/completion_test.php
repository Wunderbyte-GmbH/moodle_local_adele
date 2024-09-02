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
 * @author David Szkiba <david.szkiba@wunderbyte.at>
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use local_adele\helper\user_path_relation;
use local_adele\event\user_path_updated;
use local_adele\completion;
use advanced_testcase;
use context_system;

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
class completion_test extends advanced_testcase {
    /**
     * Test the searchnestedarray method.
     *
     * @runInSeparateProcess
     */
    public function test_searchnestedarray() {
        $haystack = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
            ['id' => 3, 'name' => 'Item 3'],
        ];

        $needle = 2;
        $key = 'id';

        // Test with a existing needle.
        $result = completion::searchnestedarray($haystack, $needle, $key);
        $expectedresult = ['id' => 2, 'name' => 'Item 2'];
        $this->assertEquals($expectedresult, $result);

        // Test with a non-existing needle.
        $needle = 4;
        $result = completion::searchnestedarray($haystack, $needle, $key);
        $this->assertNull($result);
    }
}
