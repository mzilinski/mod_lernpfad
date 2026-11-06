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
 * Library functions for mod_lernpfad.
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Karte zeigt die Aktivitäten des eigenen Abschnitts (oder Unterabschnitts). */
define('LERNPFAD_SCOPE_SECTION', 0);
/** Karte zeigt die Abschnitte des ganzen Kurses. */
define('LERNPFAD_SCOPE_COURSE', 1);

/** Ausrichtung automatisch wählen. */
define('LERNPFAD_ORIENT_AUTO', 0);
/** Stationen von links nach rechts. */
define('LERNPFAD_ORIENT_HORIZONTAL', 1);
/** Stationen von oben nach unten. */
define('LERNPFAD_ORIENT_VERTICAL', 2);

/**
 * Supported features.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function lernpfad_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO, FEATURE_BACKUP_MOODLE2, FEATURE_SHOW_DESCRIPTION, FEATURE_NO_VIEW_LINK,
            FEATURE_IDNUMBER => true,
        FEATURE_GROUPS, FEATURE_GROUPINGS, FEATURE_COMPLETION_TRACKS_VIEWS, FEATURE_GRADE_HAS_GRADE,
            FEATURE_GRADE_OUTCOMES => false,
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_RESOURCE,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_CONTENT,
        default => null,
    };
}

/**
 * Add a new instance.
 *
 * @param stdClass $data form data
 * @return int new instance id
 */
function lernpfad_add_instance($data) {
    global $DB;
    $data->timemodified = time();
    return $DB->insert_record('lernpfad', $data);
}

/**
 * Update an instance.
 *
 * @param stdClass $data form data
 * @return bool
 */
function lernpfad_update_instance($data) {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    return $DB->update_record('lernpfad', $data);
}

/**
 * Delete an instance.
 *
 * @param int $id instance id
 * @return bool
 */
function lernpfad_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('lernpfad', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('lernpfad', ['id' => $id]);
    return true;
}

/**
 * Cached course module info (settings travel in customdata, no DB query on the course page).
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|false
 */
function lernpfad_get_coursemodule_info($coursemodule) {
    global $DB;
    $rec = $DB->get_record(
        'lernpfad',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, scope, orientation'
    );
    if (!$rec) {
        return false;
    }
    $info = new cached_cm_info();
    $info->name = $rec->name;
    $info->customdata = ['scope' => (int)$rec->scope, 'orientation' => (int)$rec->orientation];
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('lernpfad', $rec, $coursemodule->id, false);
    }
    return $info;
}

/**
 * Render the map on the course page (per user, because states differ).
 *
 * @param cm_info $cm
 */
function lernpfad_cm_info_view(cm_info $cm): void {
    global $USER;
    $intro = $cm->content;
    $map = \mod_lernpfad\local\map::render_for_cm($cm, (int)$USER->id);
    $cm->set_custom_cmlist_item(true);
    $cm->set_content($intro . $map, true);
}
