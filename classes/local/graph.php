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

namespace mod_lernpfad\local;

use cm_info;
use completion_info;
use moodle_url;
use section_info;

/**
 * Builds the station graph of a learning path for one user.
 *
 * Nodes are activities (scope "section") or sections (scope "course"); a mod_subsection
 * activity inside a section map becomes one station for its whole subsection. Edges come
 * from positive completion and grade conditions in the access restrictions. Without any
 * restriction between the stations the course order is used.
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graph {
    /** @var array<string, array> nodes keyed by id ("c<cmid>" or "s<sectionid>") */
    public array $nodes = [];
    /** @var array<int, array{0:string,1:string}> edges [from, to] */
    public array $edges = [];
    /** @var bool true if the edges come from the course order, not from restrictions */
    public bool $sequential = false;

    /** @var \course_modinfo */
    private $modinfo;
    /** @var completion_info */
    private completion_info $completion;
    /** @var int */
    private int $userid;
    /** @var array<int, string> cmid => node id that represents it */
    private array $owner = [];
    /** @var array<int, int>|null grade item id => cmid */
    private ?array $gradeitems = null;
    /** @var int[] all cmids in course order */
    private array $order = [];

    /**
     * Build the graph for a learning path activity.
     *
     * @param cm_info $self the learning path itself
     * @param int $scope LERNPFAD_SCOPE_*
     * @param int $userid
     * @return self
     */
    public static function for_cm(cm_info $self, int $scope, int $userid): self {
        $g = new self();
        $g->modinfo = get_fast_modinfo($self->get_course(), $userid);
        $g->completion = new completion_info($self->get_course());
        $g->userid = $userid;
        foreach ($g->modinfo->get_section_info_all() as $section) {
            foreach ($section->get_sequence_cm_infos() as $cm) {
                $g->order[] = (int)$cm->id;
            }
        }
        if ($scope == LERNPFAD_SCOPE_COURSE) {
            $g->build_course((int)$self->id);
        } else {
            $g->build_section($self->get_section_info(), (int)$self->id);
        }
        $g->derive_edges();
        return $g;
    }

    /**
     * Stations = activities of one section; subsections become one station each.
     *
     * @param section_info $section
     * @param int $selfid
     */
    private function build_section(section_info $section, int $selfid): void {
        foreach ($section->get_sequence_cm_infos() as $cm) {
            if ($cm->id == $selfid || !$this->shown($cm) || in_array($cm->modname, ['label', 'lernpfad'])) {
                continue;
            }
            $sub = $cm->modname === 'subsection' ? $cm->get_delegated_section_info() : null;
            if ($sub) {
                $this->add_section_node($sub, $cm->get_formatted_name());
            } else {
                $this->add_cm_node($cm);
            }
        }
    }

    /**
     * Stations = top-level sections except section 0.
     *
     * @param int $selfid
     */
    private function build_course(int $selfid): void {
        foreach ($this->modinfo->get_listed_section_info_all() as $section) {
            if ($section->section == 0 || !$this->section_shown($section)) {
                continue;
            }
            $this->add_section_node($section, get_section_name($section->course, $section));
        }
    }

    /**
     * Is a course module listed for this user (visible, or restricted but shown)?
     *
     * @param cm_info $cm
     * @return bool
     */
    private function shown(cm_info $cm): bool {
        return !$cm->deletioninprogress && $cm->is_visible_on_course_page();
    }

    /**
     * Is a section listed for this user?
     *
     * @param section_info $section
     * @return bool
     */
    private function section_shown(section_info $section): bool {
        return $section->uservisible || ($section->visible && !empty($section->availableinfo));
    }

    /**
     * Add a station for one activity.
     *
     * @param cm_info $cm
     */
    private function add_cm_node(cm_info $cm): void {
        $id = 'c' . $cm->id;
        $tracked = $this->completion->is_enabled($cm) != COMPLETION_TRACKING_NONE;
        $done = $tracked && $this->is_complete($cm);
        $url = $cm->url ?? new moodle_url('/course/view.php', ['id' => $cm->course], 'module-' . $cm->id);
        $this->nodes[$id] = [
            'id' => $id,
            'name' => self::plain($cm->get_formatted_name()),
            'url' => $cm->uservisible ? $url->out(false) : null,
            'available' => (bool)$cm->available,
            'tracked' => $tracked,
            'done' => $done,
            'total' => $tracked ? 1 : 0,
            'completed' => $done ? 1 : 0,
            'availability' => [$cm->availability],
            'position' => array_search((int)$cm->id, $this->order),
        ];
        $this->owner[(int)$cm->id] = $id;
    }

    /**
     * Add a station for a whole section (incl. its subsections).
     *
     * @param section_info $section
     * @param string $name
     */
    private function add_section_node(section_info $section, string $name): void {
        $id = 's' . $section->id;
        $total = $completed = 0;
        $availability = [$section->availability];
        $first = null;
        foreach ($this->section_cms($section) as $cm) {
            $this->owner[(int)$cm->id] = $id;
            $availability[] = $cm->availability;
            $first ??= array_search((int)$cm->id, $this->order);
            if ($this->shown($cm) && $this->completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                $total++;
                $completed += $this->is_complete($cm) ? 1 : 0;
            }
        }
        $url = new moodle_url('/course/section.php', ['id' => $section->id]);
        $this->nodes[$id] = [
            'id' => $id,
            'name' => self::plain($name),
            'url' => $section->uservisible ? $url->out(false) : null,
            'available' => (bool)$section->available,
            'tracked' => $total > 0,
            'done' => $total > 0 && $completed == $total,
            'total' => $total,
            'completed' => $completed,
            'availability' => $availability,
            'position' => $first === false || $first === null ? PHP_INT_MAX - 1000 + $section->section : $first,
        ];
    }

    /**
     * All course modules of a section, including those in its subsections.
     *
     * @param section_info $section
     * @return cm_info[]
     */
    private function section_cms(section_info $section): array {
        $out = [];
        foreach ($section->get_sequence_cm_infos() as $cm) {
            if ($cm->modname === 'subsection' && ($sub = $cm->get_delegated_section_info())) {
                $out = array_merge($out, $this->section_cms($sub));
            } else if ($cm->modname !== 'lernpfad') {
                $out[] = $cm;
            }
        }
        return $out;
    }

    /**
     * Formatted (HTML-escaped) name as plain text.
     *
     * @param string $html
     * @return string
     */
    private static function plain(string $html): string {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Completion state "complete" (incl. passed) for the user.
     *
     * @param cm_info $cm
     * @return bool
     */
    private function is_complete(cm_info $cm): bool {
        $state = $this->completion->get_data($cm, false, $this->userid)->completionstate;
        return in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS]);
    }

    /**
     * Edges from restrictions; fall back to the course order.
     */
    private function derive_edges(): void {
        $seen = [];
        foreach ($this->nodes as $to => $node) {
            foreach ($node['availability'] as $json) {
                if (empty($json) || !($tree = json_decode($json, true))) {
                    continue;
                }
                foreach ($this->referenced_cms($tree, $node['position']) as $cmid) {
                    $from = $this->owner[$cmid] ?? null;
                    if ($from && $from !== $to && !isset($seen["$from>$to"])) {
                        $seen["$from>$to"] = true;
                        $this->edges[] = [$from, $to];
                    }
                }
            }
        }
        uasort($this->nodes, fn($a, $b) => $a['position'] <=> $b['position']);
        if (!$this->edges && count($this->nodes) > 1) {
            $ids = array_keys($this->nodes);
            for ($i = 1; $i < count($ids); $i++) {
                $this->edges[] = [$ids[$i - 1], $ids[$i]];
            }
            $this->sequential = true;
        }
        foreach ($this->nodes as &$node) {
            unset($node['availability']);
        }
    }

    /**
     * Course modules that a restriction tree requires (positive completion/grade conditions).
     *
     * @param array $tree availability JSON (decoded)
     * @param int $position course-order position of the restricted station (for cm = -1)
     * @return int[]
     */
    private function referenced_cms(array $tree, int $position): array {
        if (isset($tree['op']) && in_array($tree['op'], ['!&', '!|'])) {
            return [];
        }
        $out = [];
        foreach ($tree['c'] ?? [] as $c) {
            if (isset($c['c'])) {
                $out = array_merge($out, $this->referenced_cms($c, $position));
            } else if (($c['type'] ?? '') === 'completion' && in_array((int)($c['e'] ?? 1), [1, 2])) {
                $cmid = (int)$c['cm'];
                $out[] = $cmid === -1 ? $this->previous_tracked($position) : $cmid;
            } else if (($c['type'] ?? '') === 'grade' && !empty($c['id'])) {
                $out[] = $this->grade_item_cm((int)$c['id']);
            }
        }
        return array_filter($out);
    }

    /**
     * Previous activity with completion before a course position (condition "previous activity").
     *
     * @param int $position
     * @return int
     */
    private function previous_tracked(int $position): int {
        for ($i = min($position, count($this->order)) - 1; $i >= 0; $i--) {
            $cm = $this->modinfo->get_cm($this->order[$i]);
            if ($this->completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                return (int)$cm->id;
            }
        }
        return 0;
    }

    /**
     * Course module of an activity grade item.
     *
     * @param int $itemid
     * @return int
     */
    private function grade_item_cm(int $itemid): int {
        global $DB;
        if ($this->gradeitems === null) {
            $this->gradeitems = [];
            $items = $DB->get_records(
                'grade_items',
                ['courseid' => $this->modinfo->get_course_id(), 'itemtype' => 'mod'],
                '',
                'id, itemmodule, iteminstance'
            );
            foreach ($items as $item) {
                $cms = $this->modinfo->get_instances_of($item->itemmodule);
                if (isset($cms[$item->iteminstance])) {
                    $this->gradeitems[(int)$item->id] = (int)$cms[$item->iteminstance]->id;
                }
            }
        }
        return $this->gradeitems[$itemid] ?? 0;
    }
}
