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
 * Settings form for mod_lernpfad.
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once(__DIR__ . '/lib.php');

/**
 * Settings form.
 */
class mod_lernpfad_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('name'), ['size' => 48]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'mapsettings', get_string('mapsettings', 'lernpfad'));
        $mform->addElement('select', 'scope', get_string('scope', 'lernpfad'), [
            LERNPFAD_SCOPE_SECTION => get_string('scope_section', 'lernpfad'),
            LERNPFAD_SCOPE_COURSE => get_string('scope_course', 'lernpfad'),
        ]);
        $mform->addHelpButton('scope', 'scope', 'lernpfad');
        $mform->addElement('select', 'orientation', get_string('orientation', 'lernpfad'), [
            LERNPFAD_ORIENT_AUTO => get_string('orientation_auto', 'lernpfad'),
            LERNPFAD_ORIENT_HORIZONTAL => get_string('orientation_horizontal', 'lernpfad'),
            LERNPFAD_ORIENT_VERTICAL => get_string('orientation_vertical', 'lernpfad'),
        ]);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
