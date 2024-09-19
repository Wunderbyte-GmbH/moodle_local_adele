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
use moodle_recordset;
use stdClass;

/**
 * PHPUnit test case for the 'users' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_test extends advanced_testcase {

    protected function setUp(): void {
        global $DB;

        // Mock the global $DB object to ensure sql_concat, sql_like, and get_recordset_sql can be called.
        $DB = $this->getMockBuilder(stdClass::class)
            ->addMethods(['sql_concat', 'sql_like', 'get_recordset_sql'])
            ->getMock();
    }

    /**
     * Test the get_users function.
     * @covers \local_adele\users::load_users
     * @runInSeparateProcess
     */
    public function test_load_users() {
        global $DB;

        // Mock the SQL functions.
        $DB->expects($this->once())
            ->method('sql_concat')
            ->willReturn("CONCAT(u.id, ' ', u.firstname, ' ', u.lastname, ' ', u.email)");

        $DB->expects($this->exactly(2))
            ->method('sql_like')
            ->withConsecutive(
                ['fulltextstring', ':param1', false],
                ['fulltextstring', ':param2', false]
            )
            ->willReturnOnConsecutiveCalls(
                "fulltextstring LIKE :param1",
                "fulltextstring LIKE :param2"
            );

        // Mock the moodle_recordset to return a record set with a close() method.
        $recordset = $this->getMockBuilder(moodle_recordset::class)
            ->onlyMethods(['close', 'valid', 'current', 'key', 'next', 'rewind'])
            ->getMock();

        // Mock the users in the recordset.
        $user1 = (object)[
            'id' => 1,
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
        ];

        $user2 = (object)[
            'id' => 2,
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'email' => 'jane.smith@example.com',
        ];

        // Mock the iteration over the recordset.
        $recordset->expects($this->exactly(3))
            ->method('valid')
            ->willReturnOnConsecutiveCalls(true, true, false); // Iterate over two users.

        $recordset->expects($this->exactly(2)) // Call current() for each valid record.
            ->method('current')
            ->willReturnOnConsecutiveCalls($user1, $user2);

        $recordset->expects($this->once())
            ->method('close'); // Expect close() to be called once.

        // Mock get_recordset_sql to return the mocked recordset.
        $DB->expects($this->once())
            ->method('get_recordset_sql')
            ->willReturn($recordset);

        // Call the method.
        $result = users::load_users('John Smith');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertCount(2, $result['list']);
        $this->assertEquals('John', $result['list'][1]->firstname);
        $this->assertEquals('Jane', $result['list'][2]->firstname);
        $this->assertEmpty($result['warnings'], 'Expected no warnings when fewer than 100 results.');
    }

    /**
     * Test the get_users function overload.
     * @covers \local_adele\users::load_users
     * @runInSeparateProcess
     */
    public function test_load_users_exceeds_limit() {
        global $DB;

        $DB->expects($this->once())
            ->method('sql_concat')
            ->willReturn("CONCAT(u.id, ' ', u.firstname, ' ', u.lastname, ' ', u.email)");

        $DB->expects($this->any())
            ->method('sql_like')
            ->willReturn("fulltextstring LIKE :param");

        $recordset = $this->getMockBuilder(moodle_recordset::class)
            ->onlyMethods(['close', 'valid', 'current', 'key', 'next', 'rewind'])
            ->getMock();

        $users = [];
        for ($i = 1; $i <= 102; $i++) {
            $users[] = (object)[
                'id' => $i,
                'firstname' => 'User' . $i,
                'lastname' => 'Test',
                'email' => 'user' . $i . '@example.com',
            ];
        }

        $validsequence = array_fill(0, 102, true);
        $validsequence[] = false;

        $recordset->expects($this->exactly(103))
            ->method('valid')
            ->willReturnOnConsecutiveCalls(...$validsequence);

        $recordset->expects($this->exactly(102))
            ->method('current')
            ->willReturnOnConsecutiveCalls(...$users);

        $recordset->expects($this->once())
            ->method('close');

        $DB->expects($this->once())
            ->method('get_recordset_sql')
            ->willReturn($recordset);

        $result = users::load_users('Test');

        // Assertions.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertCount(0, $result['list'], 'Expected no users to be returned when results exceed 100.');
        $this->assertNotEmpty($result['warnings'], 'Expected a warning when results exceed 100.');
    }

}
