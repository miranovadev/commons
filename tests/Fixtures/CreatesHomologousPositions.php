<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ContextHorizonProjector;
use App\Services\LedgerService;

/** Authored committed-coordinate read models; never an operational Commit builder. */
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

    private function committedCoordinate(?Proposition $entry, string $after, array $measurements = []): Proposition
    {
        $phase = $this->phase($entry, $entry, PhaseKind::Order);
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'committal', 'register' => $phase->write_register, 'statement' => 'Region seed'])->save();
        $ledgerEntry = app(LedgerService::class)->record($node);
        // Governance links supply the coordinate projection. Do not fabricate
        // a Committal-to-Committal Commit Supersession to justify that input.
        $horizon = $phase->cycle->contextHorizon;
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->actor->substrate_id,
            'ledger_entry_id' => $ledgerEntry->id, 'supersession_id' => null,
            'correspondence' => 0, 'chirality' => 0, 'closure' => 0,
            'depth' => 0, 'cadence' => 0, 'cadence_interval' => $entry === null ? null : 1,
            'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
            'direction_before' => $horizon->domain, 'direction_after' => $after,
            'calculator_version' => $entry === null ? 'origin-bootstrap-v1' : 'ownership-fixture', 'evaluated_at' => now(), ...$measurements])->save();
        app(ContextHorizonProjector::class)->apply($snapshot);
        if ($entry !== null) {
            // A supplied coordinate is not a causal birth in the creating
            // Domain. Keep it out of that Domain's expressive geometry.
            ContextHorizonMembership::where('proposition_id', $node->id)
                ->where('context_horizon_id', $horizon->id)->delete();
        }

        return $node;
    }

    private function phase(?Proposition $entry, ?Proposition $gate, PhaseKind $kind, bool $malformed = false): Phase
    {
        $horizon = $entry === null ? (ContextHorizon::where('substrate_id', $this->actor->substrate_id)->whereNull('parent_context_horizon_id')->first()
            ?? app(ContextHorizonProjector::class)->currentFor($this->actor->substrate))
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
