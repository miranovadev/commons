<?php

namespace Tests\Fixtures;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitRegionPolarityService;
use App\Services\EvidenceExecutionService;
use App\Services\LedgerService;
use App\Services\Observables\ProspectiveClosureService;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Persisted history, three live registers, evidence, and unrelated Horizon growth. */
final class ParticipantContextFixture
{
    public Substrate $substrate;

    public Participant $viewer;

    public Cycle $history;

    public Phase $phase;

    public Proposition $seed;

    public Proposition $gate;

    public array $chain = [];

    public array $replaced = [];

    public array $unrelated = [];

    private ?Proposition $unrelatedSeed = null;

    public array $writes = [];

    private array $governedCycles = [];

    public function __construct(int $depth = 25, int $branches = 6, bool $withEvidence = true)
    {
        $this->substrate = new Substrate;
        $this->substrate->forceFill(['slug' => 'context-'.str()->ulid(), 'title' => 'Participant context', 'visibility' => 'public', 'configuration' => []])->save();
        $this->viewer = new Participant;
        $this->viewer->forceFill(['substrate_id' => $this->substrate->id, 'callsign' => 'context-reader', 'max_read_count' => 25])->save();
        $this->history = new Cycle;
        $this->history->forceFill(['participant_id' => $this->viewer->id, 'sequence' => 1, 'completed_at' => now()])->save();
        $this->seed = $this->node(null, 'canon', 'variant', 'committal');
        app(CommitRegionPolarityService::class)->establishOrigin($this->seed, 'support');
        $head = $this->seed;
        $this->chain[] = $head;
        for ($i = 1; $i < $depth; $i++) {
            if ($i === 3) {
                $a = $this->node($head, 'activity');
                $b = $this->node($head, 'activity');
                $head = $this->node($a, 'frontier', 'reconcile', additionalParent: $b);
                $this->replaced = [$a->id, $b->id];
            } else {
                $head = $this->node($head, ['activity', 'frontier', 'canon'][$i % 3]);
            }
            $this->chain[] = $head;
        }
        // The Read anchor and current Gate are operational roles, outside history.
        $anchor = $this->node($head, 'canon', position: PhaseKind::Constrain);
        $this->gate = $this->node($anchor, 'activity', position: PhaseKind::Disperse);
        if ($withEvidence) {
            $this->evidence($anchor);
        }
        for ($i = 0; $i < $branches; $i++) {
            $read = $this->node($this->seed, 'canon', position: PhaseKind::Constrain);
            $this->node($read, 'activity', position: PhaseKind::Disperse);
            if ($withEvidence) {
                $this->evidence($read);
            }
        }
        for ($i = 0; $i < 3; $i++) {
            $write = $this->node($this->gate, 'frontier', position: PhaseKind::Mediate);
            if ($withEvidence) {
                $this->evidence($write);
            }
            $this->writes[] = $write->id;
        }
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->viewer->id, 'sequence' => (int) $this->viewer->cycles()->max('sequence') + 1,
            'context_horizon_id' => $this->history->context_horizon_id, 'entry_committal_id' => $this->seed->id])->save();
        $disperse = new Phase;
        $disperse->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Disperse, 'write_register' => 'activity',
            'gate_proposition_id' => $anchor->id, 'started_at' => now(), 'completed_at' => now()])->save();
        $this->phase = new Phase;
        $this->phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Mediate, 'write_register' => 'frontier',
            'gate_proposition_id' => $this->gate->id, 'started_at' => now()])->save();
        $this->viewer->forceFill(['current_proposition_id' => $this->gate->id])->save();
    }

    public function node(?Proposition $parent, string $register, string $type = 'variant', string $tier = 'expressive', ?PhaseKind $position = null, ?Proposition $additionalParent = null, bool $completeBirth = true, ?Participant $participant = null): Proposition
    {
        $phase = new Phase;
        $kind = $position ?? ($type === 'reconcile' ? PhaseKind::Mediate : ($type === 'commit' ? PhaseKind::Order : PhaseKind::Distinguish));
        $governor = $parent?->tier === 'committal' ? $parent : $parent?->phase->cycle->entryCommittal;
        $cycle = $governor === null ? $this->history : $this->governedCycle($governor, $participant);
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register,
            'gate_proposition_id' => $parent?->id, 'started_at' => now(), 'completed_at' => now()])->save();
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $register, 'tier' => $tier,
            'statement' => 'Shared observations should remain open to revision as participants collect evidence. '.
                'A useful proposal states its assumptions and explains how the next observation could support or challenge them. '.
                'We preserve earlier contributions while making the current decision and its governing provenance clear.'])->save();
        app(LedgerService::class)->record($node, $parent);
        if ($tier === 'committal') {
            $horizon = $node->phase->cycle->contextHorizon;
            (new CommitmentSnapshot)->forceFill(['proposition_id' => $node->id, 'substrate_id' => $horizon->substrate_id,
                'ledger_entry_id' => $node->ledgerEntry->id, 'correspondence' => 0, 'chirality' => 0, 'closure' => 0,
                'prominence' => .5, 'turbulence' => .5, 'tension' => 0, 'direction_before' => $horizon->domain,
                'direction_after' => $horizon->domain, 'calculator_version' => 'region-fixture', 'evaluated_at' => now()])->save();
        }
        if ($parent !== null) {
            $this->edge($parent, $node, $type);
        }
        if ($additionalParent !== null) {
            $this->edge($additionalParent, $node, $type);
        }
        $closure = app(ProspectiveClosureService::class);
        if ($tier === 'expressive' && $completeBirth) {
            $closure->initialize($node);
        } elseif ($tier === 'committal' && $parent !== null && $completeBirth) {
            $closure->preserve($node, $parent, $closure->stored($parent));
        }

        return $node;
    }

    /** Authored consumer input only; never an authorised Commit or frozen Closure history. */
    public function projectedCommittal(Proposition $parent, string $register): Proposition
    {
        if ($parent->tier !== 'expressive' || (app(ProspectiveClosureService::class)->stored($parent)['fixture'] ?? null) !== 'isolated_closure_output') {
            throw new LogicException('Projected Committals require explicit component isolation.');
        }

        return $this->node($parent, $register, 'commit', 'committal', completeBirth: false);
    }

    private function governedCycle(Proposition $seed, ?Participant $participant = null): Cycle
    {
        $participant ??= $this->viewer;
        $key = $seed->id.':'.$participant->id;
        if (! isset($this->governedCycles[$key])) {
            $cycle = new Cycle;
            $cycle->forceFill(['participant_id' => $participant->id,
                'sequence' => (int) $participant->cycles()->max('sequence') + 1,
                'context_horizon_id' => $seed->phase->cycle->context_horizon_id,
                'entry_committal_id' => $seed->id, 'completed_at' => now()])->save();
            $this->governedCycles[$key] = $cycle;
        }

        return $this->governedCycles[$key];
    }

    public function edge(Proposition $source, Proposition $target, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }

    public function evidence(Proposition $node): void
    {
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => 'support',
            'rationale' => 'This interpretation agrees with the recorded observation; its assumptions can be checked by another participant.'])->save();
        app(LedgerService::class)->record($attestation);
        $execution = app(EvidenceExecutionService::class)->start($node->phase, $node, 'hyperlink', 'https://example.test/evidence/'.$node->id);
        app(EvidenceExecutionService::class)->complete($execution, $attestation, 'evidence-'.$node->id);
    }

    public function growUnrelatedTo(int $count): void
    {
        if ($this->unrelatedSeed === null) {
            $source = $this->node($this->seed, 'canon');
            $this->evidence($source);
            $this->unrelatedSeed = $this->node($source, 'canon', 'commit', 'committal');
            app(CommitRegionPolarityService::class)->establishCommit($this->unrelatedSeed, ObservableDirection::Positive);
        }
        // These nodes are intentionally outside the tested operation. Bulk-load
        // complete storage history rather than replaying thousands of unrelated
        // fixture setup queries. The real context/workspace services still run.
        $unrelatedCycle = $this->governedCycle($this->unrelatedSeed);
        $phases = $nodes = $edges = $ledger = $memberships = [];
        $horizonId = ContextHorizon::where('substrate_id', $this->substrate->id)->whereNull('closed_at')->value('id');
        $sequence = (int) DB::table('substrate_ledger_entries')->where('substrate_id', $this->substrate->id)->max('sequence');
        while (count($this->unrelated) < $count) {
            $phaseId = strtolower((string) str()->ulid());
            $nodeId = strtolower((string) str()->ulid());
            $edgeId = strtolower((string) str()->ulid());
            $entryId = strtolower((string) str()->ulid());
            $register = ['activity', 'frontier', 'canon'][count($this->unrelated) % 3];
            $timestamps = ['created_at' => now(), 'updated_at' => now()];
            $phases[] = [...$timestamps, 'id' => $phaseId, 'cycle_id' => $unrelatedCycle->id, 'kind' => PhaseKind::Distinguish->value,
                'write_register' => $register, 'gate_proposition_id' => $this->unrelatedSeed->id, 'started_at' => now(), 'completed_at' => now()];
            $nodes[] = [...$timestamps, 'id' => $nodeId, 'phase_id' => $phaseId, 'register' => $register,
                'tier' => 'expressive', 'statement' => $this->unrelatedSeed->statement];
            $edges[] = [...$timestamps, 'id' => $edgeId, 'phase_id' => $phaseId, 'type' => 'variant',
                'source_proposition_id' => $this->unrelatedSeed->id, 'target_proposition_id' => $nodeId];
            $ledger[] = ['id' => $entryId, 'substrate_id' => $this->substrate->id, 'sequence' => ++$sequence,
                'kind' => 'proposition', 'ledgerable_type' => (new Proposition)->getMorphClass(), 'ledgerable_id' => $nodeId, 'recorded_at' => now()];
            $ledger[] = ['id' => strtolower((string) str()->ulid()), 'substrate_id' => $this->substrate->id, 'sequence' => ++$sequence,
                'kind' => 'supersession', 'ledgerable_type' => (new Supersession)->getMorphClass(), 'ledgerable_id' => $edgeId, 'recorded_at' => now()];
            $memberships[] = ['proposition_id' => $nodeId, 'context_horizon_id' => $horizonId, 'ledger_entry_id' => $entryId, 'projected_at' => now()];
            $this->unrelated[] = $nodeId;
        }
        foreach (['phases' => $phases, 'propositions' => $nodes, 'supersessions' => $edges,
            'substrate_ledger_entries' => $ledger, 'context_horizon_memberships' => $memberships] as $table => $rows) {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
        // Preserve Phase::created's invalidation once for the completed batch,
        // so the large-context assertion also exercises fresh measurements.
        PropositionMeasurement::whereHas('proposition.phase.cycle.participant',
            fn ($query) => $query->where('substrate_id', $this->substrate->id))
            ->update(['calculator_version' => null]);
    }
}
