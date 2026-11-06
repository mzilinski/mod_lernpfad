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

namespace mod_lernpfad;

use mod_lernpfad\local\layout;
use mod_lernpfad\local\map;

/**
 * Tests for the layered layout and the label heuristics.
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_lernpfad\local\layout::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_lernpfad\local\map::class)]
final class layout_test extends \advanced_testcase {
    public function test_diamond_and_chain(): void {
        // A -> B, A -> C, B -> D, C -> D, D -> E.
        $g = layout::compute(
            ['a', 'b', 'c', 'd', 'e'],
            [['a', 'b'], ['a', 'c'], ['b', 'd'], ['c', 'd'], ['d', 'e']]
        );
        $this->assertSame([0, 1, 1, 2, 3], array_column($g, 'rank'));
        $this->assertEquals(-0.5, $g['b']['lane']);
        $this->assertEquals(0.5, $g['c']['lane']);
        $this->assertEquals($g['d']['lane'], $g['e']['lane']);
    }

    public function test_chain_stays_on_branch_lane(): void {
        // Fork after b: c and x; c -> d -> e is a chain and keeps c's lane.
        $g = layout::compute(
            ['a', 'b', 'c', 'x', 'd', 'e'],
            [['a', 'b'], ['b', 'c'], ['b', 'x'], ['c', 'd'], ['d', 'e']]
        );
        $this->assertEquals($g['c']['lane'], $g['d']['lane']);
        $this->assertEquals($g['c']['lane'], $g['e']['lane']);
    }

    public function test_cycle_does_not_loop(): void {
        $g = layout::compute(['a', 'b'], [['a', 'b'], ['b', 'a']]);
        $this->assertSame(0, $g['a']['rank']);
        $this->assertSame(1, $g['b']['rank']);
    }

    public function test_labels(): void {
        $l = map::labels([
            'x' => 'PA1 Einstieg',
            'y' => 'PA1 Lernbuch: Linux-Administration',
            'z' => 'PA1 Test: Linux-Admin',
        ]);
        $this->assertSame(['', ['Einstieg']], $l['x']);
        $this->assertSame('Lernbuch', $l['y'][0]);
        $this->assertSame('Test', $l['z'][0]);
        $s = map::labels(['s' => 'PA2 · SSH-Grundlagen & Key-Management']);
        $this->assertSame('PA2', $s['s'][0]);
        $this->assertLessThanOrEqual(3, count($s["s"][1]));
    }
}
