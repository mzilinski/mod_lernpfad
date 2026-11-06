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

use mod_lernpfad\local\graph;
use mod_lernpfad\local\map;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/lernpfad/lib.php');
require_once($CFG->libdir . '/completionlib.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_lernpfad\local\graph::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_lernpfad\local\map::class)]
/**
 * Tests for the graph built from a course.
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_lernpfad\local\graph
 * @covers     \mod_lernpfad\local\map
 */
final class graph_test extends \advanced_testcase {
    /**
     * Course with completion, three pages in section 1 and a learning path.
     *
     * @return array [course, pages[], lernpfad cm_info, student]
     */
    private function setup_course(int $scope): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1, 'numsections' => 3]);
        $pages = [];
        foreach ([1, 1, 2] as $i => $section) {
            $pages[] = $gen->create_module('page', ['course' => $course->id, 'section' => $section,
                'name' => 'P' . $i, 'completion' => COMPLETION_TRACKING_MANUAL]);
        }
        $lp = $gen->create_module('lernpfad', ['course' => $course->id, 'section' => 1, 'scope' => $scope]);
        $student = $gen->create_and_enrol($course, 'student');
        $cm = get_fast_modinfo($course, $student->id)->get_cm($lp->cmid);
        return [$course, $pages, $cm, $student];
    }

    public function test_section_scope_falls_back_to_course_order(): void {
        [, $pages, $cm, $student] = $this->setup_course(LERNPFAD_SCOPE_SECTION);
        $g = graph::for_cm($cm, LERNPFAD_SCOPE_SECTION, $student->id);
        $this->assertSame(['c' . $pages[0]->cmid, 'c' . $pages[1]->cmid], array_keys($g->nodes));
        $this->assertTrue($g->sequential);
        $this->assertSame([['c' . $pages[0]->cmid, 'c' . $pages[1]->cmid]], $g->edges);
    }

    public function test_course_scope_edges_from_restrictions_and_progress(): void {
        global $DB;
        [$course, $pages, , $student] = $this->setup_course(LERNPFAD_SCOPE_COURSE);
        // Section 2 requires P1 (section 1) to be complete.
        $avail = json_encode(['op' => '&', 'c' => [['type' => 'completion', 'cm' => (int)$pages[1]->cmid, 'e' => 1]],
            'showc' => [true]]);
        $DB->set_field('course_sections', 'availability', $avail, ['course' => $course->id, 'section' => 2]);
        rebuild_course_cache($course->id, true);
        // Student completes P0.
        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($course, $student->id);
        $completion->update_state($modinfo->get_cm($pages[0]->cmid), COMPLETION_COMPLETE, $student->id);

        $modinfo = get_fast_modinfo($course, $student->id);
        $lp = array_values($modinfo->get_instances_of('lernpfad'))[0];
        $g = graph::for_cm($lp, LERNPFAD_SCOPE_COURSE, $student->id);
        $s1 = 's' . $modinfo->get_section_info(1)->id;
        $s2 = 's' . $modinfo->get_section_info(2)->id;
        $this->assertContains([$s1, $s2], $g->edges);
        $this->assertFalse($g->sequential);
        $this->assertSame(1, $g->nodes[$s1]['completed']);
        $this->assertSame(2, $g->nodes[$s1]['total']);
        $this->assertFalse($g->nodes[$s2]['available']);

        $html = map::render($g, LERNPFAD_ORIENT_AUTO, 'x');
        $this->assertStringContainsString('lp-locked', $html);
        $this->assertStringContainsString('lp-progress', $html);
    }
}
