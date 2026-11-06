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

/**
 * Renders a learning path as an inline SVG route map (server side, per user).
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class map {
    /** Station radius. */
    const R = 13;
    /** Horizontal: width of one rank, height of one lane. */
    const COLW = 130;
    /** Lane height (horizontal layout). */
    const LANEH = 112;
    /** Vertical: height of one rank. */
    const ROWH = 68;
    /** Lane width (vertical layout). */
    const LANEW = 250;
    /** Outer padding. */
    const PAD = 16;
    /** Label font size. */
    const FONT = 13;
    /** Line height of label lines. */
    const LINE = 16;
    /** Horizontal layout up to this many ranks (automatic direction). */
    const MAXRANKS_H = 6;

    /**
     * Map HTML for a learning path course module.
     *
     * @param cm_info $cm
     * @param int $userid
     * @return string
     */
    public static function render_for_cm(cm_info $cm, int $userid): string {
        $data = $cm->customdata ?: [];
        $scope = (int)($data['scope'] ?? LERNPFAD_SCOPE_SECTION);
        $orient = (int)($data['orientation'] ?? LERNPFAD_ORIENT_AUTO);
        $graph = graph::for_cm($cm, $scope, $userid);
        return self::render($graph, $orient, 'lernpfad-' . $cm->id);
    }

    /**
     * Map HTML for a graph.
     *
     * @param graph $graph
     * @param int $orient LERNPFAD_ORIENT_*
     * @param string $domid
     * @return string
     */
    public static function render(graph $graph, int $orient, string $domid): string {
        if (!$graph->nodes) {
            return \html_writer::div(get_string('empty', 'lernpfad'), 'lernpfad lernpfad-empty text-muted');
        }
        $ids = array_keys($graph->nodes);
        $grid = layout::compute($ids, $graph->edges);
        $ranks = max(array_column($grid, 'rank')) + 1;
        $horizontal = $orient == LERNPFAD_ORIENT_HORIZONTAL
            || ($orient == LERNPFAD_ORIENT_AUTO && $ranks <= self::MAXRANKS_H);
        $labels = self::labels(array_column($graph->nodes, 'name', 'id'),
            $horizontal ? self::COLW - 14 : self::LANEW - self::R * 2 - 30);
        $states = self::states($graph);

        $lanes = array_column($grid, 'lane');
        [$minlane, $maxlane] = [min($lanes), max($lanes)];
        $xy = [];
        foreach ($grid as $id => $g) {
            $lane = $g['lane'] - $minlane;
            $xy[$id] = $horizontal
                ? [self::PAD + $g['rank'] * self::COLW + self::COLW / 2, self::PAD + self::R + 4 + $lane * self::LANEH]
                : [self::PAD + self::R + 4 + $lane * self::LANEW, self::PAD + self::ROWH / 2 + $g['rank'] * self::ROWH];
        }
        if ($horizontal) {
            $w = self::PAD * 2 + $ranks * self::COLW;
            $h = self::PAD * 2 + self::R * 2 + 8 + ($maxlane - $minlane) * self::LANEH + self::LINE * 4 + 4;
        } else {
            $w = self::PAD * 2 + ($maxlane - $minlane) * self::LANEW + self::LANEW;
            $h = self::PAD * 2 + $ranks * self::ROWH;
        }

        $svg = [];
        // Tracks.
        foreach ($graph->edges as [$a, $b]) {
            $cls = 'lp-edge';
            if ($states[$a] === 'done') {
                $cls .= ' lp-edge-done';
            }
            if ($states[$b] === 'locked') {
                $cls .= ' lp-edge-locked';
            }
            $svg[] = '<path class="' . $cls . '" d="' . self::edge_path($xy[$a], $xy[$b], $horizontal, $grid, $a, $b) . '"/>';
        }
        // Stations + labels.
        foreach ($graph->nodes as $id => $node) {
            $svg[] = self::station($node, $states[$id], $labels[$id], $xy[$id], $horizontal);
        }

        $aria = get_string('mapaccessible', 'lernpfad', count($graph->nodes));
        $out = '<svg class="lernpfad-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . round($w) . ' ' . round($h)
            . '" style="max-width:' . round($w) . 'px" role="group" aria-label="' . s($aria) . '">'
            . implode('', $svg) . '</svg>';
        return \html_writer::div($out . self::legend(), 'lernpfad', ['id' => $domid]);
    }

    /**
     * State per station: done | partial | next | open | neutral | locked.
     *
     * @param graph $graph
     * @return array<string, string>
     */
    private static function states(graph $graph): array {
        $preds = array_fill_keys(array_keys($graph->nodes), []);
        foreach ($graph->edges as [$a, $b]) {
            $preds[$b][] = $a;
        }
        // A predecessor counts as passed when it is done, or untracked and its own
        // predecessors are passed (walk back through pages/files without completion).
        $passed = function(string $id, array $seen = []) use (&$passed, $graph, $preds): bool {
            $n = $graph->nodes[$id];
            if ($n['tracked']) {
                return $n['done'];
            }
            $seen[$id] = true;
            foreach ($preds[$id] as $p) {
                if (!isset($seen[$p]) && !$passed($p, $seen)) {
                    return false;
                }
            }
            return true;
        };
        $out = [];
        foreach ($graph->nodes as $id => $n) {
            if (!$n['available']) {
                $out[$id] = 'locked';
            } else if ($n['done']) {
                $out[$id] = 'done';
            } else if (!$n['tracked']) {
                $out[$id] = 'neutral';
            } else {
                $ready = true;
                foreach ($preds[$id] as $p) {
                    $ready = $ready && $passed($p);
                }
                $out[$id] = $ready ? 'next' : ($n['completed'] > 0 ? 'partial' : 'open');
            }
        }
        return $out;
    }

    /**
     * Track between two stations: straight on one lane, otherwise an S-curve. A track that
     * skips ranks on its own lane is bent aside so it does not run through other stations.
     *
     * @param float[] $p
     * @param float[] $q
     * @param bool $horizontal
     * @param array $grid
     * @param string $a
     * @param string $b
     * @return string
     */
    private static function edge_path(array $p, array $q, bool $horizontal, array $grid, string $a, string $b): string {
        [$x1, $y1, $x2, $y2] = [$p[0], $p[1], $q[0], $q[1]];
        $f = fn($v) => round($v, 1);
        $skip = abs($grid[$b]['rank'] - $grid[$a]['rank']) > 1;
        if ($grid[$a]['lane'] == $grid[$b]['lane']) {
            $blocked = false;
            if ($skip) {
                [$lo, $hi] = [min($grid[$a]['rank'], $grid[$b]['rank']), max($grid[$a]['rank'], $grid[$b]['rank'])];
                foreach ($grid as $g) {
                    $blocked = $blocked || ($g['lane'] == $grid[$a]['lane'] && $g['rank'] > $lo && $g['rank'] < $hi);
                }
            }
            if (!$blocked) {
                return "M {$f($x1)} {$f($y1)} L {$f($x2)} {$f($y2)}";
            }
            $off = ($horizontal ? self::LANEH : self::LANEW) * 0.45;
            return $horizontal
                ? "M {$f($x1)} {$f($y1)} C {$f($x1)} {$f($y1 - $off)}, {$f($x2)} {$f($y2 - $off)}, {$f($x2)} {$f($y2)}"
                : "M {$f($x1)} {$f($y1)} C {$f($x1 + $off)} {$f($y1)}, {$f($x2 + $off)} {$f($y2)}, {$f($x2)} {$f($y2)}";
        }
        if ($horizontal) {
            $m = ($x1 + $x2) / 2;
            return "M {$f($x1)} {$f($y1)} C {$f($m)} {$f($y1)}, {$f($m)} {$f($y2)}, {$f($x2)} {$f($y2)}";
        }
        $m = ($y1 + $y2) / 2;
        return "M {$f($x1)} {$f($y1)} C {$f($x1)} {$f($m)}, {$f($x2)} {$f($m)}, {$f($x2)} {$f($y2)}";
    }

    /**
     * One station with label, as a link when the user may open it.
     *
     * @param array $node
     * @param string $state
     * @param array $label [code, lines[]]
     * @param float[] $xy
     * @param bool $horizontal
     * @return string
     */
    private static function station(array $node, string $state, array $label, array $xy, bool $horizontal): string {
        [$x, $y] = $xy;
        $r = self::R;
        $g = '';
        if ($state === 'next') {
            $g .= '<circle class="lp-pulse" cx="' . $x . '" cy="' . $y . '" r="' . ($r + 9) . '"/>';
        }
        $g .= '<circle class="lp-station lp-' . $state . '" cx="' . $x . '" cy="' . $y . '" r="'
            . ($state === 'neutral' ? $r - 4 : $r) . '"/>';
        if ($state === 'partial' || ($state === 'next' && $node['completed'] > 0)) {
            $pct = round(100 * $node['completed'] / max(1, $node['total']));
            $g .= '<circle class="lp-progress-track" cx="' . $x . '" cy="' . $y . '" r="' . ($r + 5) . '"/>'
                . '<circle class="lp-progress" cx="' . $x . '" cy="' . $y . '" r="' . ($r + 5) . '" pathLength="100"'
                . ' stroke-dasharray="' . $pct . ' 100" transform="rotate(-90 ' . $x . ' ' . $y . ')"/>';
        }
        if ($state === 'done') {
            $g .= '<path class="lp-check" d="M ' . ($x - 5.5) . ' ' . $y . ' l 3.8 4 l 7.2 -8"/>';
        } else if ($state === 'locked') {
            $g .= '<rect class="lp-lock" x="' . ($x - 4.5) . '" y="' . ($y - 1) . '" width="9" height="7" rx="1.5"/>'
                . '<path class="lp-lock-bow" d="M ' . ($x - 2.6) . ' ' . ($y - 1) . ' v -2.2 a 2.6 2.6 0 0 1 5.2 0 v 2.2"/>';
        }
        // Label.
        [$code, $lines] = $label;
        $all = $code !== '' ? array_merge([$code], $lines) : $lines;
        if ($horizontal) {
            $tx = $x;
            $ty = $y + $r + 18;
            $anchor = 'middle';
        } else {
            $tx = $x + $r + 12;
            $ty = $y - (count($all) - 1) * self::LINE / 2 + 4.5;
            $anchor = 'start';
        }
        $t = '<text class="lp-label' . ($state === 'locked' ? ' lp-label-locked' : '') . '" x="' . $tx . '" y="'
            . $ty . '" text-anchor="' . $anchor . '">';
        foreach ($all as $i => $line) {
            $cls = ($i === 0 && $code !== '') ? ' class="lp-code"' : '';
            $t .= '<tspan x="' . $tx . '"' . ($i ? ' dy="' . self::LINE . '"' : '') . $cls . '>' . s($line) . '</tspan>';
        }
        $t .= '</text>';
        $statetext = get_string(match ($state) {
            'done' => 'done', 'locked' => 'locked', 'next' => 'next', default => 'open',
        }, 'lernpfad');
        if ($node['total'] > 1) {
            $statetext .= ', ' . get_string('progress', 'lernpfad', ['done' => $node['completed'], 'total' => $node['total']]);
        }
        $title = '<title>' . s($node['name'] . ' – ' . $statetext) . '</title>';
        $inner = $title . $g . $t;
        if (!empty($node['url'])) {
            return '<a class="lp-node" href="' . s($node['url']) . '">' . $inner . '</a>';
        }
        return '<g class="lp-node lp-node-nolink" tabindex="0">' . $inner . '</g>';
    }

    /**
     * Short labels: common leading word dropped, "Code · Titel" / "Typ: Titel" split, wrapped.
     *
     * @param array<string, string> $names id => name
     * @param float $width label width in px
     * @return array<string, array{0:string, 1:string[]}>
     */
    public static function labels(array $names, float $width = self::COLW - 14): array {
        $names = array_map(fn($n) => trim(preg_replace('/\s+/u', ' ', strip_tags($n))), $names);
        // Drop a first word shared by all names (e.g. "PA1 Einstieg", "PA1 Übung" -> "Einstieg", "Übung").
        if (count($names) > 1) {
            $first = array_unique(array_map(fn($n) => explode(' ', $n, 2)[0], $names));
            if (count($first) == 1 && !array_filter($names, fn($n) => !str_contains($n, ' '))) {
                $names = array_map(fn($n) => explode(' ', $n, 2)[1], $names);
            }
        }
        $out = [];
        foreach ($names as $id => $n) {
            $code = '';
            $title = $n;
            if (preg_match('/^(\S{1,12})\s+[·•|–—-]\s+(.+)$/u', $n, $m)
                    || preg_match('/^([^:]{1,16}):\s*(.+)$/u', $n, $m)
                    || preg_match('/^([A-ZÄÖÜ][A-ZÄÖÜ0-9]{0,5})\s+(.+)$/u', $n, $m)) {
                [$code, $title] = [$m[1], $m[2]];
            }
            $out[$id] = [$code, self::wrap($title, 3, $width)];
        }
        return $out;
    }

    /**
     * Wrap a title to the label width (estimated glyph widths), at most $max lines.
     *
     * @param string $text
     * @param int $max
     * @param float $width
     * @return string[]
     */
    private static function wrap(string $text, int $max, float $width): array {
        $limit = $width / (self::FONT * 0.56);
        $words = preg_split('/(?<=[\s\-\/])/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $lines = [''];
        foreach ($words as $w) {
            $cur = end($lines);
            if ($cur !== '' && \core_text::strlen(rtrim($cur . $w)) > $limit) {
                $lines[] = $w;
            } else {
                $lines[count($lines) - 1] .= $w;
            }
        }
        $lines = array_map('rtrim', $lines);
        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] = rtrim(\core_text::substr($lines[$max - 1], 0, (int)$limit - 1)) . '…';
        }
        return $lines;
    }

    /**
     * Legend under the map.
     *
     * @return string
     */
    private static function legend(): string {
        $items = '';
        foreach (['done', 'next', 'open', 'locked'] as $state) {
            $dot = '<svg class="lernpfad-svg lp-legend-dot" viewBox="0 0 20 20" aria-hidden="true">'
                . '<circle class="lp-station lp-' . $state . '" cx="10" cy="10" r="7"/></svg>';
            $items .= \html_writer::span($dot . get_string($state, 'lernpfad'), 'lp-legend-item');
        }
        return \html_writer::div($items, 'lp-legend small text-muted');
    }
}
