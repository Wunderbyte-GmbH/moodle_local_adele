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

/**
 * Guard for issue #582 (Wunderbyte-GmbH/moodle-local_adele): the plugin reads "now" from core's clock only.
 *
 * Every read of the current time goes through \core\di::get(\core\clock::class),
 * so that tests can freeze it and so that the plugin and Moodle's own task
 * manager - which already uses that clock - agree on what "now" is. This test
 * fails as soon as a plain time() or an argument-less new DateTime() comes
 * back into the plugin code.
 *
 * It works on PHP tokens rather than text: a comment that mentions time() is
 * not a call, and a method named time() on some object is not the global one.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core\clock
 */
final class clock_usage_test extends \basic_testcase {
    /**
     * No plugin file outside tests/ reads the wall clock directly.
     *
     * @return void
     */
    public function test_no_direct_wall_clock_reads(): void {
        $root = dirname(__DIR__);
        $files = [];
        foreach (['classes', 'db'] as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        foreach (['lib.php', 'locallib.php', 'index.php', 'view.php'] as $name) {
            if (is_file($root . '/' . $name)) {
                $files[] = $root . '/' . $name;
            }
        }

        $offences = [];
        foreach ($files as $file) {
            foreach (self::find_wall_clock_reads((string) file_get_contents($file)) as $line => $what) {
                $offences[] = substr($file, strlen($root) + 1) . ':' . $line . ' ' . $what;
            }
        }

        $this->assertSame(
            [],
            $offences,
            "Read the current time through \\core\\di::get(\\core\\clock::class) (issue #582)."
        );
    }

    /**
     * Find global time() calls and argument-less DateTime constructions.
     *
     * @param string $source PHP source.
     * @return array Line number => description.
     */
    private static function find_wall_clock_reads(string $source): array {
        $tokens = array_values(array_filter(token_get_all($source), function ($token) {
            return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
        }));
        $found = [];
        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }
            $next = $tokens[$i + 1] ?? null;
            $after = $tokens[$i + 2] ?? null;
            $previous = $tokens[$i - 1] ?? null;

            // A call to the global time(): not a method, not a declaration.
            $isname = in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true);
            if ($isname && ltrim(strtolower($token[1]), '\\') === 'time' && $next === '(' && $after === ')') {
                $prevtype = is_array($previous) ? $previous[0] : $previous;
                if (!in_array($prevtype, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                    $found[$token[2]] = 'time()';
                }
            }

            // An argument-less DateTime construction, with or without a leading backslash.
            if (
                $token[0] === T_NEW && is_array($next) && ltrim($next[1], '\\') === 'DateTime'
                    && ($tokens[$i + 2] ?? null) === '(' && ($tokens[$i + 3] ?? null) === ')'
            ) {
                $found[$token[2]] = 'new DateTime()';
            }
        }
        return $found;
    }
}
