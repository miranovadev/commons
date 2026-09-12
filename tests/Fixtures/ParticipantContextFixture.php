<?php

namespace Tests\Fixtures;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitRegionPolarityService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;

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

    public function __construct(int $depth = 25, int $branches = 6)
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
                $head = $this->node($a, 'frontier', 'reconcile');
                $this->edge($b, $head, 'reconcile');
                $this->replaced = [$a->id, $b->id];
            } else {
                $head = $this->node($head, ['activity', 'frontier', 'canon'][$i % 3]);
            }
            $this->chain[] = $head;
        }
        // The Read anchor and current Gate are operational roles, outside history.
        $anchor = $this->node($head, 'canon');
        $this->gate = $this->node($anchor, 'activity');
        for ($i = 0; $i < $branches; $i++) {
            $read = $this->node($this->seed, 'canon');
            $this->node($read, 'activity');
            $this->evidence($read);
        }
        for ($i = 0; $i < 3; $i++) {
            $write = $this->node($this->gate, 'frontier');
            $this->evidence($write);
            $this->writes[] = $write->id;
        }
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->viewer->id, 'sequence' => 2])->save();
        $disperse = new Phase;
        $disperse->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Disperse, 'write_register' => 'activity',
            'gate_proposition_id' => $anchor->id, 'started_at' => now(), 'completed_at' => now()])->save();
        $this->phase = new Phase;
        $this->phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Mediate, 'write_register' => 'frontier',
            'gate_proposition_id' => $this->gate->id, 'started_at' => now()])->save();
        $this->viewer->forceFill(['current_proposition_id' => $this->gate->id])->save();
    }

    public function node(?Proposition $parent, string $register, string $type = 'variant', string $tier = 'expressive'): Proposition
    {
        $phase = new Phase;
        $kind = $type === 'reconcile' ? PhaseKind::Mediate : ($type === 'commit' ? PhaseKind::Order : PhaseKind::Distinguish);
        $phase->forceFill(['cycle_id' => $this->history->id, 'kind' => $kind, 'write_register' => $register,
            'gate_proposition_id' => $parent?->id, 'started_at' => now(), 'completed_at' => now()])->save();
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $register, 'tier' => $tier,
            'statement' => 'Shared observations should remain open to revision as participants collect evidence. '.
                'A useful proposal states its assumptions and explains how the next observation could support or challenge them. '.
                'We preserve earlier contributions while making the current decision and its governing provenance clear.'])->save();
        app(LedgerService::class)->record($node);
        if ($parent !== null) {
            $this->edge($parent, $node, $type);
        }

        return $node;
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
        $execution = app(InstrumentExecutionService::class)->start($node->phase, $node, 'hyperlink', 'https://example.test/evidence/'.$node->id);
        app(InstrumentExecutionService::class)->complete($execution, $attestation, 'evidence-'.$node->id);
    }

    public function growUnrelatedTo(int $count): void
    {
        if ($this->unrelatedSeed === null) {
            $source = $this->node($this->seed, 'canon');
            $this->evidence($source);
            $this->unrelatedSeed = $this->node($source, 'canon', 'commit', 'committal');
            app(CommitRegionPolarityService::class)->establishCommit($this->unrelatedSeed, ObservableDirection::Positive);
        }
        while (count($this->unrelated) < $count) {
            $this->unrelated[] = $this->node($this->unrelatedSeed, ['activity', 'frontier', 'canon'][count($this->unrelated) % 3])->id;
        }
    }
}
