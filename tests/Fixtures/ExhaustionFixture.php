<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContextHorizonProjector;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\EvidenceExecutionService;
use App\Services\LedgerService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use Illuminate\Support\Facades\DB;

trait ExhaustionFixture
{
    private Substrate $substrate;

    private Proposition $origin;

    private function initializeExhaustionFixture(): void
    {
        $this->controlledClosureFixture(-1);
        $this->substrate = (new Substrate)->forceFill(['slug' => 'exhaustion', 'title' => 'Exhaustion', 'visibility' => 'public', 'configuration' => []]);
        $this->substrate->save();
        $phase = $this->phase();
        $this->origin = $this->node($phase, null, 'committal');
        $this->snapshot($this->origin, .8, 'origin-bootstrap-v1');
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now()])->save();
        $this->measure($this->origin, .8);
    }

    private function explore(Proposition $node): void
    {
        $instruments = app(EvidenceExecutionService::class);
        $reference = 'https://example.test/explored/'.$node->id;
        $execution = $instruments->start($node->phase, $node, 'hyperlink', $reference);
        $instruments->complete($execution, $node, verification: app(HyperlinkVerifier::class)->verify($reference));
        $vote = (new Attestation)->forceFill(['phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => 'refute']);
        $vote->save();
        app(LedgerService::class)->record($vote);
    }

    private function rest(Phase $phase): array
    {
        return app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
    }

    private function exhaustedBranch(): array
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root);
        $head = $this->node($this->phase($commit, PhaseKind::Constrain), $commit);
        $this->explore($head);
        $first = $this->phase($head);
        $second = $this->phase($head);
        $this->measure($root, 1);
        $this->measure($head, 1);

        return [$root, $first, $second, $commit, $head];
    }

    private function commitFrom(Proposition $parent, float $tension = .5, bool $boundary = false): Proposition
    {
        $source = $this->node($this->phase($parent, PhaseKind::Bias), $parent);
        $this->measure($source, 1);
        $commit = $this->node($this->phase($source), $source, 'committal');
        $this->snapshot($commit, $tension);
        if ($boundary) {
            $snapshot = $commit->commitmentSnapshot;
            $snapshot->forceFill(['direction_after' => $snapshot->direction_before->opposite()])->save();
            app(ContextHorizonProjector::class)->apply($snapshot);
        }
        $this->publishPotentialFixture($this->substrate);

        return $commit;
    }

    private function phase(?Proposition $gate = null, PhaseKind $kind = PhaseKind::Order): Phase
    {
        $participant = (new Participant)->forceFill(['substrate_id' => $this->substrate->id, 'callsign' => 'p-'.str()->ulid(), 'current_proposition_id' => $gate?->id]);
        $participant->save();
        $horizon = $gate === null ? app(ContextHorizonProjector::class)->currentFor($this->substrate)
            : ($gate->tier === 'committal' ? app(ContextHorizonProjector::class)->forEntry($gate) : $gate->phase->cycle->contextHorizon);
        $cycle = (new Cycle)->forceFill(['participant_id' => $participant->id, 'context_horizon_id' => $horizon->id, 'sequence' => 1,
            'entry_committal_id' => $gate?->tier === 'committal' ? $gate->id : $gate?->phase->cycle->entry_committal_id]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'gate_proposition_id' => $gate?->id,
            'kind' => $kind, 'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()]);
        $phase->save();

        app(PotentialProjectionService::class)->rebuild($this->substrate->id, false);

        return $phase;
    }

    private function node(Phase $phase, ?Proposition $parent, string $tier = 'expressive'): Proposition
    {
        $node = (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register, 'tier' => $tier, 'statement' => 'Branch fixture']);
        $node->save();
        app(LedgerService::class)->record($node, $parent);
        if ($parent !== null) {
            $edge = (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id, 'target_proposition_id' => $node->id, 'type' => $tier === 'committal' ? 'commit' : 'variant']);
            $edge->save();
            app(LedgerService::class)->record($edge);
        }
        if ($tier === 'expressive') {
            PropositionBirth::complete($node);
        } elseif ($parent !== null) {
            $closure = app(ProspectiveClosureService::class);
            $closure->preserve($node, $parent, $closure->stored($parent));
        }

        return $node;
    }

    private function snapshot(Proposition $node, float $tension, string $version = 'tragedy-fixture'): void
    {
        (new CommitmentSnapshot)->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->substrate->id,
            'ledger_entry_id' => $node->ledgerEntry->id,
            'supersession_id' => Supersession::where('target_proposition_id', $node->id)->where('type', 'commit')->value('id'), 'correspondence' => $tension, 'chirality' => $tension, 'closure' => $tension,
            'prominence' => (1 + $tension) / 2, 'turbulence' => (1 - $tension) / 2, 'tension' => $tension,
            'direction_before' => $node->phase->cycle->contextHorizon->domain,
            'direction_after' => $node->phase->cycle->contextHorizon->domain,
            'cadence_interval' => $node->phase->cycle->entry_committal_id === null ? null : 1,
            'calculator_version' => $version, 'evaluated_at' => now()])->save();
    }

    private function measure(Proposition $node, float $value): void
    {
        $this->controlledBirthClosureFixture($value, $node->id);
        app(PropositionMeasurementService::class)->project($node, $value, $value, $value);
        app(PotentialProjectionService::class)->rebuild($this->substrate->id, false);
    }

    private function history(): array
    {
        return collect(['propositions', 'supersessions', 'attestations', 'receipts', 'commitment_snapshots', 'context_horizons', 'substrate_ledger_entries'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy($table === 'commitment_snapshots' ? 'proposition_id' : 'id')->get()->map(fn ($row) => (array) $row)->all()])->all();
    }
}
