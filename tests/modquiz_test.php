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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * PHPUnit test case for the 'modquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(modquiz::class)]
final class modquiz_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
    }

    /**
     * Test get_mod_quizzes function with no data.
     */
    #[RunInSeparateProcess]
    public function test_get_mod_quizzes_with_no_data(): void {
        global $DB;
        $DB = $this->createMock(FakeModquizDB::class);

        // Expect the get_records_sql method to be called and return an empty array.
        $DB->method('get_in_or_equal')->willReturn(['SELECT clause', []]);
        $DB->expects($this->once())
            ->method('get_records_sql')
            ->willReturn([]);

        // Call the method under test.
        $result = modquiz::get_mod_quizzes([['course_node_id' => '1'], ['course_node_id' => '2']]);

        // Assert that the result is an empty array.
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    /**
     * Test get_mod_quizzes function with data.
     */
    #[RunInSeparateProcess]
    public function test_get_mod_quizzes_with_data(): void {
        global $DB;
        $DB = $this->createMock(FakeModquizDB::class);

        // Mock data returned from the DB.
        $mockedrecords = [
            (object)[
                'id' => 1,
                'course' => 101,
                'name' => 'Quiz 1',
                'coursename' => 'Course 1',
            ],
            (object)[
                'id' => 2,
                'course' => 102,
                'name' => 'Quiz 2',
                'coursename' => 'Course 2',
            ],
        ];

        // Expect the get_records_sql method to be called once and return the mocked records.
        $DB->method('get_in_or_equal')->willReturn(['SELECT clause', []]);
        $DB->expects($this->once())
            ->method('get_records_sql')
            ->willReturn($mockedrecords);

        // Call the method under test.
        $result = modquiz::get_mod_quizzes([['course_node_id' => '1'], ['course_node_id' => '2']]);

        // Assert that the result is an array.
        $this->assertIsArray($result);

        // Assert the structure of the array.
        $this->assertCount(2, $result);
        $this->assertEquals('Quiz 1', $result[0]['name']);
        $this->assertEquals('Course 1', $result[0]['coursename']);
        $this->assertEquals(1, $result[0]['id']);
    }
}

/**
 * Fake DB stub with the methods used by modquiz tests.
 */
class FakeModquizDB {
    /**
     * @param string $sql
     * @param array|null $params
     * @return array
     */
    public function get_records_sql(string $sql, array $params = null): array {
        return [];
    }

    /**
     * @param mixed $items
     * @return array
     */
    public function get_in_or_equal(mixed $items): array {
        return ['', []];
    }
}
