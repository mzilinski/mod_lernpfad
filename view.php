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

/**
 * Shows a learning path on its own page.
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'lernpfad');
$lernpfad = $DB->get_record('lernpfad', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/lernpfad:view', $context);

$event = \mod_lernpfad\event\course_module_viewed::create(['objectid' => $lernpfad->id, 'context' => $context]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('lernpfad', $lernpfad);
$event->trigger();

$PAGE->set_url('/mod/lernpfad/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($lernpfad->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($lernpfad);

echo $OUTPUT->header();
if (trim(strip_tags($lernpfad->intro))) {
    echo $OUTPUT->box(format_module_intro('lernpfad', $lernpfad, $cm->id), 'generalbox');
}
echo \mod_lernpfad\local\map::render_for_cm($cm, (int)$USER->id);
echo $OUTPUT->footer();
