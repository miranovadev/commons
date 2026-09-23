<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Services\Observables\CommittalLineageService;
use Illuminate\Support\Collection;

/** Structural comparison only: this service grants no operational options or movement. */
final readonly class HomologousPositionService
{
    public function __construct(private CommittalLineageService $lineage, private CommitRegionService $regions) {}

    /**
     * Equal committed depth from a validated shared Origin, keyed by candidate ID.
     * Nearest shared ancestor/distance are diagnostic output, not eligibility rules.
     * A Committal's Domain is its creating Horizon's Domain, not its register label.
     * Missing, cyclic or disconnected committed histories fail closed.
     *
     * @return array<string, array{ancestor_id: string, distance: int}>
     */
    public function committedCoordinatesFor(Proposition $anchor, Collection $candidates): array
    {
        return $this->committedCoordinates($anchor, $candidates, sameCreatingDomain: true);
    }

    private function committedCoordinates(Proposition $anchor, Collection $candidates, bool $sameCreatingDomain): array
    {
        if (! $anchor->exists || $anchor->tier !== 'committal' || $candidates->isEmpty()) {
            return [];
        }
        $substrate = $anchor->phase->cycle->participant->substrate_id;
        $ids = $candidates->filter(fn ($node) => $node->exists && $node->tier === 'committal')->pluck('id');
        $records = $this->lineage->recordsForIds($substrate, $ids->push($anchor->id));
        $left = $this->distances($anchor->id, $records, $substrate);
        if ($left === null) {
            return [];
        }
        $result = [];
        foreach ($candidates as $candidate) {
            if (! $candidate->exists || $candidate->tier !== 'committal') {
                continue;
            }
            $right = $this->distances($candidate->id, $records, $substrate);
            if ($right === null || count($left) !== count($right) || array_key_last($left) !== array_key_last($right)
                || ($sameCreatingDomain && $records[$anchor->id]->creating_domain !== $records[$candidate->id]->creating_domain)) {
                continue;
            }
            foreach ($left as $ancestor => $distance) {
                if (! array_key_exists($ancestor, $right)) {
                    continue;
                }
                $result[$candidate->id] = ['ancestor_id' => $ancestor, 'distance' => $distance];
                // Equal full depths are already established; this intersection is diagnostic.
                break;
            }
        }

        return $result;
    }

    public function areCommittedHomologous(Proposition $a, Proposition $b): bool
    {
        return isset($this->committedCoordinatesFor($a, collect([$b]))[$b->id]);
    }

    /** Validate every edge to the bootstrap; never accept a shared but broken prefix. */
    private function distances(string $id, Collection $records, string $substrate): ?array
    {
        $distances = [];
        while ($id !== null) {
            $record = $records->get($id);
            if (isset($distances[$id]) || $record === null || $record->committed_tier !== 'committal'
                || $record->substrate_id !== $substrate || $record->creating_substrate_id !== $substrate
                || $record->horizon_substrate_id !== $substrate
                || $record->direction_before->value !== $record->creating_domain) {
                return null;
            }
            $distances[$id] = count($distances);
            if ($record->isClosureBoundary()) {
                if ($record->opened_horizon_id === null || $record->opened_parent_horizon_id !== $record->creating_horizon_id
                    || $record->opened_substrate_id !== $substrate || $record->opened_domain !== $record->direction_after->value) {
                    return null;
                }
            } elseif ($record->opened_horizon_id !== null) {
                return null;
            }
            if ($record->entry_committal_id === null && $record->calculator_version !== 'origin-bootstrap-v1') {
                return null;
            }
            if ($record->entry_committal_id !== null) {
                $parent = $records->get($record->entry_committal_id);
                $expectedHorizon = $parent?->isClosureBoundary() ? $parent->opened_horizon_id : $parent?->creating_horizon_id;
                if ($parent === null || $expectedHorizon === null || $record->creating_horizon_id !== $expectedHorizon
                    || $record->creating_domain !== $parent->direction_after->value) {
                    return null;
                }
            }
            $id = $record->entry_committal_id;
        }

        return $distances;
    }

    /** Batched canonical position comparison. Activity, exhaustion and action policy belong to consumers. */
    public function homologousPositionsFor(Proposition $anchor, Collection $candidates, bool $sameEntryDomain = false): Collection
    {
        if ($candidates->isEmpty()) {
            return collect();
        }
        $nodes = (new Proposition)->newCollection($candidates->concat([$anchor])->unique('id')->all());
        $nodes->loadMissing('phase.cycle.contextHorizon', 'phase.cycle.participant');
        if (! $this->canonical($anchor)) {
            return collect();
        }
        $cycle = $anchor->phase->cycle;
        $eligible = $nodes->filter(fn ($node) => $this->canonical($node)
            && $node->tier === $anchor->tier
            && $node->phase->kind === $anchor->phase->kind
            && $node->phase->cycle->participant->substrate_id === $cycle->participant->substrate_id
            && $node->phase->cycle->contextHorizon->domain === $cycle->contextHorizon->domain);
        $owners = $this->regions->seedIdsFor($eligible, $eligible);
        $ownerId = $owners[$anchor->id] ?? null;
        if ($ownerId === null) {
            return collect();
        }
        $entries = Proposition::whereKey(array_values(array_filter($owners)))->get()->keyBy('id');
        if (! $entries->has($ownerId)) {
            return collect();
        }
        // The positions' own Domain was checked above. An entry boundary may have
        // been created in the opposite Domain from expressions in its opened Horizon.
        $coordinates = $this->committedCoordinates($entries[$ownerId], $entries, sameCreatingDomain: $sameEntryDomain);

        return $candidates->filter(fn ($node) => $eligible->contains('id', $node->id)
            && isset($coordinates[$owners[$node->id] ?? '']))->unique('id')->values();
    }

    public function arePositionsHomologous(Proposition $a, Proposition $b): bool
    {
        return $this->homologousPositionsFor($a, collect([$b]))->contains('id', $b->id);
    }

    /** Filter by substrate, Domain and canonical position before expanding committed entries once. */
    public function candidatesFor(Proposition $anchor, PhaseKind $position, bool $foreignRegionsOnly = false, bool $includeAnchor = false, bool $sameEntryDomain = true): Collection
    {
        if (! $this->canonical($anchor) || $anchor->phase->kind !== $position) {
            return collect();
        }
        $cycle = $anchor->phase->cycle;
        $domain = $cycle->contextHorizon->domain;
        $register = $domain->registerFor($position);
        $candidates = Proposition::where('tier', $anchor->tier)->where('register', $register)
            ->when($foreignRegionsOnly, fn ($q) => $q->whereHas('phase.cycle', fn ($q) => $q
                ->whereNotNull('entry_committal_id')->where('entry_committal_id', '!=', $cycle->entry_committal_id)))
            ->whereHas('phase', fn ($q) => $q->where('kind', $position->value)->where('write_register', $register)
                ->whereHas('cycle', fn ($q) => $q
                    ->whereHas('participant', fn ($q) => $q->where('substrate_id', $cycle->participant->substrate_id))
                    ->whereHas('contextHorizon', fn ($q) => $q->where('domain', $domain->value))))
            ->when(! $includeAnchor, fn ($q) => $q->where('id', '!=', $anchor->id))->orderBy('id')->get();

        // Existing Mediate discovery retains its entry-Domain restriction until Stage 3.
        return $this->homologousPositionsFor($anchor, $candidates, $sameEntryDomain);
    }

    private function canonical(Proposition $node): bool
    {
        if (! $node->exists || ! in_array($node->tier, ['expressive', 'committal'], true)) {
            return false;
        }
        $phase = $node->phase;
        $cycle = $phase?->cycle;
        if ($cycle?->contextHorizon === null || $cycle->participant === null
            || $cycle->contextHorizon->substrate_id !== $cycle->participant->substrate_id
            || ($node->tier === 'committal' && $phase->kind !== PhaseKind::Order)) {
            return false;
        }
        $register = $cycle->contextHorizon->domain->registerFor($phase->kind);

        return $node->register === $register && $phase->write_register === $register;
    }
}
