<?php

namespace App\Services;

/** Pure region algebra shared by maintenance and observer-specific local exclusion. */
final class PotentialRegionCalculator
{
    public function distances(array $local, string $from): array
    {
        $adjacency = [];
        foreach ($local['edges'] as [$source, $target]) {
            $adjacency[$source][] = $target;
            $adjacency[$target][] = $source;
        }
        $distances = [$from => 0];
        $queue = [$from];
        for ($i = 0; $i < count($queue); $i++) {
            $id = $queue[$i];
            foreach ($adjacency[$id] ?? [] as $next) {
                if (! isset($distances[$next])) {
                    $distances[$next] = $distances[$id] + 1;
                    $queue[] = $next;
                }
            }
        }

        return $distances;
    }

    public function candidates(array $local, ?string $excluded = null, ?string $branch = null, ?string $retained = null, ?array $lineage = null, bool $shadowCompletedRegions = true): array
    {
        $children = [];
        $commits = [];
        foreach ($local['edges'] as $edge) {
            [$source, $target] = $edge;
            $type = $edge[2] ?? null;
            $children[$source][] = $target;
            if ($type === 'commit') {
                $commits[$target] = true;
            }
        }
        $eligible = array_filter($local['entries'], fn ($entry, $id) => $id !== $excluded
            && ($lineage === null || isset($lineage[$id]))
            && ($branch === null || $this->inBranch($local['nodes'][$id], $branch)), ARRAY_FILTER_USE_BOTH);
        $entryIds = array_fill_keys(array_keys($eligible), true);
        $result = [];
        foreach ($eligible as $id => $entry) {
            // Initial-entry/numeric summaries retain their downstream frontier.
            // Recovery supplies only the nearest eligible ancestral Committal;
            // off-path downstream Commits cannot shadow that destination.
            $search = [$id];
            $searched = [$id => true];
            $shadowed = false;
            for ($i = 0; $shadowCompletedRegions && $lineage === null && $i < count($search) && ! $shadowed; $i++) {
                foreach ($children[$search[$i]] ?? [] as $target) {
                    if (isset($searched[$target])) {
                        continue;
                    }
                    $searched[$target] = true;
                    if (isset($entryIds[$target]) && isset($commits[$target])) {
                        $shadowed = true;
                        break;
                    }
                    $node = $local['nodes'][$target] ?? null;
                    if ($node !== null && $node['active'] && $node['tier'] !== 'committal') {
                        $search[] = $target;
                    }
                }
            }
            if ($shadowed && $id !== $retained) {
                continue;
            }
            $seen = [$id => true];
            if ($excluded !== null) {
                $seen[$excluded] = true;
            }
            $queue = [$id];
            $potential = 0.0;
            $complete = true;
            for ($i = 0; $i < count($queue); $i++) {
                foreach ($children[$queue[$i]] ?? [] as $target) {
                    if (isset($seen[$target])) {
                        continue;
                    }
                    $seen[$target] = true;
                    $node = $local['nodes'][$target] ?? null;
                    if ($node === null || ! $node['active'] || $node['tier'] === 'committal'
                        || ($node['seed_id'] ?? null) !== $id
                        || ($branch !== null && ! $this->inBranch($node, $branch))) {
                        continue;
                    }
                    $complete = $complete && $node['complete'];
                    if ($node['tension'] !== null) {
                        $potential = max($potential, abs($entry['tension']) - abs($node['tension']));
                    }
                    $queue[] = $target;
                }
            }
            $result[] = ['proposition_id' => $id, 'context_horizon_id' => $local['id'],
                'raw_potential_charge' => round(max(0, $potential), 7),
                'raw_charge' => round(1 - abs($entry['tension']), 7), 'complete' => $complete];
        }

        return $result;
    }

    public function inBranch(array $node, string $branch): bool
    {
        return $node['origin'] || in_array($branch, $node['roots'], true);
    }

    /**
     * Descendant frontier shadows local entries. Distances are relative to this
     * region's boundary: any outside observer reaches it through that boundary.
     * Keep all distance/value tradeoffs; numeric max alone cannot preserve ranking.
     */
    public function frontier(array $local, array $children): array
    {
        $distances = $local['opener'] === null ? [] : $this->distances($local, $local['opener']);
        $frontier = [];
        foreach ($children as $child) {
            $offset = $local['opener'] === null ? 0 : ($distances[$child['opener']] ?? null);
            if ($offset === null) {
                continue;
            }
            foreach ($child['frontier'] as $entry) {
                $entry['distance'] += $offset;
                $frontier[] = $entry;
            }
        }
        if ($frontier === []) {
            foreach ($this->candidates($local) as $entry) {
                $distance = $local['opener'] === null ? 0 : ($distances[$entry['proposition_id']] ?? null);
                if ($distance !== null) {
                    $frontier[] = [...$entry, 'distance' => $distance];
                }
            }
        }
        // Remove only candidates beaten for EVERY possible upstream distance.
        // This is the early-stop boundary: changing a dominated sibling need not
        // rewrite ancestors, while a nearer/weaker tradeoff remains discoverable.
        $frontier = array_values(array_filter($frontier, function ($entry) use ($frontier): bool {
            foreach ($frontier as $other) {
                if ($other['proposition_id'] === $entry['proposition_id']) {
                    continue;
                }
                if ($other['distance'] <= $entry['distance'] && $other['raw_potential_charge'] >= $entry['raw_potential_charge']
                    && ($other['distance'] < $entry['distance'] || $other['raw_potential_charge'] > $entry['raw_potential_charge']
                        || $other['raw_charge'] > $entry['raw_charge']
                        || ($other['raw_charge'] === $entry['raw_charge'] && strcmp($other['proposition_id'], $entry['proposition_id']) < 0))) {
                    return false;
                }
            }

            return true;
        }));
        usort($frontier, fn ($a, $b) => strcmp($a['proposition_id'], $b['proposition_id']));

        return $frontier;
    }

    public function rank(array $entries): array
    {
        usort($entries, fn ($a, $b) => ($b['potential_charge'] <=> $a['potential_charge'])
            ?: ($b['charge'] <=> $a['charge']) ?: ($a['distance'] <=> $b['distance'])
            ?: strcmp($a['proposition_id'], $b['proposition_id']));

        return $entries;
    }

    public function attenuate(array $entry, ?int $distance): array
    {
        $weight = $distance === null ? 1.0 : 1 / (1 + $distance) ** 2;

        return [...$entry, 'distance' => $distance,
            'potential_charge' => $entry['raw_potential_charge'] * $weight,
            'charge' => $entry['raw_charge'] * $weight];
    }
}

