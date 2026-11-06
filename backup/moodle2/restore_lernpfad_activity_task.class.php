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
 * Restore task for mod_lernpfad.
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/lernpfad/backup/moodle2/restore_lernpfad_stepslib.php');

/**
 * Restore task.
 */
class restore_lernpfad_activity_task extends restore_activity_task {
    /**
     * No settings.
     */
    protected function define_my_settings() {
    }

    /**
     * One structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_lernpfad_activity_structure_step('lernpfad_structure', 'lernpfad.xml'));
    }

    /**
     * Contents with links.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('lernpfad', ['intro'], 'lernpfad')];
    }

    /**
     * Link decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('LERNPFADVIEWBYID', '/mod/lernpfad/view.php?id=$1', 'course_module'),
            new restore_decode_rule('LERNPFADINDEX', '/mod/lernpfad/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Course log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
