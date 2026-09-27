<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContextHorizonProjector;
use App\Services\LedgerService;

trait CreatesHomologousPositions
{
    private Participant $actor;

    private function createHomologyActor(): void
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'homology', 'title' => 'Homology', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
    }

    private function position(?Proposition $entry, PhaseKind $kind = PhaseKind::Constrain, ?string $register = null): Proposition
    {
        $phase = $this->phase($entry, $entry, $kind);
        if ($register !== null) {
            $phase->forceFill(['write_register' => $register])->save();
        }
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $register ?? $phase->write_register, 'statement' => 'Structural position'])->save();
        app(LedgerService::class)->record($node);

        return $node;
    }

    private function commit(?Proposition $entry, string $after, array $measurements = []): Proposition
    {
        $phase = $this->phase($entry, $entry, PhaseKind::Order);
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'committal', 'register' => $phase->write_register, 'statement' => 'Region seed'])->save();
        $ledgerEntry = app(LedgerService::class)->record($node);
        $commit = null;
        if ($entry !== null) {
            $commit = new Supersession;
            $commit->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $phase->gate_proposition_id,
                'target_proposition_id' => $node->id, 'type' => 'commit'])->save();
            app(LedgerService::class)->record($commit);
        }
        $horizon = $phase->cycle->contextHorizon;
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->actor->substrate_id,
            'ledger_entry_id' => $ledgerEntry->id, 'supersession_id' => $commit?->id,
            'correspondence' => 0, 'chirality' => 0, 'closure' => 0,
            'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
            'direction_before' => $horizon->domain, 'direction_after' => $after,
            'calculator_version' => $entry === null ? 'origin-bootstrap-v1' : 'ownership-fixture', 'evaluated_at' => now(), ...$measurements])->save();
        app(ContextHorizonProjector::class)->apply($snapshot);

        return $node;
    }

    private function phase(?Proposition $entry, ?Proposition $gate, PhaseKind $kind, bool $malformed = false): Phase
    {
        $horizon = $entry === null ? app(ContextHorizonProjector::class)->currentFor($this->actor->substrate)
            : ($malformed ? $gate->phase->cycle->contextHorizon : app(ContextHorizonProjector::class)->forEntry($entry));
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->actor->id, 'sequence' => (int) $this->actor->cycles()->max('sequence') + 1,
            'context_horizon_id' => $horizon->id, 'entry_committal_id' => $entry?->id]);
        if ($malformed) {
            Cycle::withoutEvents(fn () => $cycle->save());
        } else {
            $cycle->save();
        }
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'gate_proposition_id' => $gate?->id,
            'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()])->save();

        return $phase;
    }
}
