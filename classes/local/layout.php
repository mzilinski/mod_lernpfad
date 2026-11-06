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

/**
 * Layered layout for small directed graphs (no external tools).
 *
 * Ranks = longest path from the sources (cycles are broken at back edges), order within a
 * rank by barycenter sweeps, then the rank is centred on a common axis. The result is a
 * grid position per node: rank (along the reading direction) and lane (across it, may be
 * fractional, e.g. -0.5/+0.5 for a fork).
 *
 * @package    mod_lernpfad
 * @copyright  2026 Malte Zilinski
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout {
    /**
     * Compute rank and lane per node.
     *
     * @param string[] $ids node ids in course order
     * @param array $edges [[from, to], ...]
     * @return array<string, array{rank:int, lane:float}>
     */
    public static function compute(array $ids, array $edges): array {
        $succ = $pred = array_fill_keys($ids, []);
        foreach (self::acyclic($ids, $edges) as [$a, $b]) {
            $succ[$a][] = $b;
            $pred[$b][] = $a;
        }
        // Longest path ranks in topological order (Kahn).
        $rank = array_fill_keys($ids, 0);
        $indeg = array_map('count', $pred);
        $queue = array_values(array_filter($ids, fn($id) => $indeg[$id] == 0));
        while ($queue) {
            $n = array_shift($queue);
            foreach ($succ[$n] as $m) {
                $rank[$m] = max($rank[$m], $rank[$n] + 1);
                if (--$indeg[$m] == 0) {
                    $queue[] = $m;
                }
            }
        }
        // Rank buckets, initial order = course order.
        $layers = [];
        foreach ($ids as $id) {
            $layers[$rank[$id]][] = $id;
        }
        ksort($layers);
        $pos = [];
        $assign = function () use (&$layers, &$pos) {
            foreach ($layers as $layer) {
                $n = count($layer);
                foreach (array_values($layer) as $i => $id) {
                    $pos[$id] = $i - ($n - 1) / 2;
                }
            }
        };
        $assign();
        // Barycenter sweeps: down using predecessors, up using successors.
        for ($sweep = 0; $sweep < 4; $sweep++) {
            $keys = array_keys($layers);
            if ($sweep % 2) {
                $keys = array_reverse($keys);
            }
            foreach ($keys as $r) {
                $nb = $sweep % 2 ? $succ : $pred;
                $bary = [];
                foreach ($layers[$r] as $i => $id) {
                    $vals = array_map(fn($o) => $pos[$o], $nb[$id]);
                    $bary[$id] = $vals ? array_sum($vals) / count($vals) : $pos[$id];
                    $bary[$id] += $i * 1e-6; // Stable.
                }
                usort($layers[$r], fn($a, $b) => $bary[$a] <=> $bary[$b]);
                $assign();
            }
        }
        // A single node with exactly one predecessor takes over its lane (chains stay straight
        // after a fork); rank order so the lane propagates along the chain.
        foreach ($layers as $layer) {
            if (count($layer) == 1 && count($pred[$layer[0]]) == 1) {
                $pos[$layer[0]] = $pos[$pred[$layer[0]][0]];
            }
        }
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['rank' => $rank[$id], 'lane' => $pos[$id]];
        }
        return $out;
    }

    /**
     * Drop edges that close a cycle (DFS in course order).
     *
     * @param string[] $ids
     * @param array $edges
     * @return array
     */
    private static function acyclic(array $ids, array $edges): array {
        $adj = array_fill_keys($ids, []);
        foreach ($edges as [$a, $b]) {
            if (isset($adj[$a], $adj[$b])) {
                $adj[$a][] = $b;
            }
        }
        $state = array_fill_keys($ids, 0);
        $keep = [];
        $visit = function ($n) use (&$visit, &$adj, &$state, &$keep) {
            $state[$n] = 1;
            foreach ($adj[$n] as $m) {
                if ($state[$m] == 1) {
                    continue; // Back edge.
                }
                $keep[] = [$n, $m];
                if ($state[$m] == 0) {
                    $visit($m);
                }
            }
            $state[$n] = 2;
        };
        foreach ($ids as $id) {
            if ($state[$id] == 0) {
                $visit($id);
            }
        }
        return $keep;
    }
}
