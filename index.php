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
 * Lists all learning paths of a course.
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_course_login($course);
$context = context_course::instance($course->id);

$event = \mod_lernpfad\event\course_module_instance_list_viewed::create(['context' => $context]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$PAGE->set_url('/mod/lernpfad/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'lernpfad'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'lernpfad'));

$table = new html_table();
$table->head = [get_string('name')];
foreach (get_fast_modinfo($course)->get_instances_of('lernpfad') as $cm) {
    if ($cm->uservisible) {
        $table->data[] = [html_writer::link(new moodle_url('/mod/lernpfad/view.php', ['id' => $cm->id]),
            $cm->get_formatted_name())];
    }
}
echo $table->data ? html_writer::table($table) : $OUTPUT->notification(get_string('thereareno', 'moodle',
    get_string('modulenameplural', 'lernpfad')), 'info');
echo $OUTPUT->footer();
