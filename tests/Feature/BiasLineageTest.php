<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection as Direction;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\AttestationEvidenceService;
use App\Services\BiasGateService;
use App\Services\LedgerService;
use App\Services\Participation\ParticipationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class BiasLineageTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_head_uses_its_own_ancestry_and_excludes_itself_and_unrelated_majorities(): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $positive = $f->node($f->seed, 'activity');
        $negative = $f->node($f->seed, 'activity');
        $this->vote($f, $positive, 'support');
        $this->vote($f, $negative, 'refute');
        $a = $f->node($positive, 'frontier', position: PhaseKind::Mediate);
        $b = $f->node($negative, 'frontier', position: PhaseKind::Mediate);
        $this->vote($f, $a, 'support');
        $this->vote($f, $b, 'refute');
        $f->phase->forceFill(['kind' => PhaseKind::Bias, 'gate_proposition_id' => $a->id, 'write_register' => 'canon'])->save();
        foreach (range(1, 4) as $_) {
            $unrelated = $f->node($f->seed, 'frontier', position: PhaseKind::Mediate);
            $this->vote($f, $unrelated, 'refute');
        }
        foreach ([null, new ParticipationContext($f->phase->fresh())] as $context) {
            $evidence = app(AttestationEvidenceService::class);
            $this->assertSame(Direction::Positive, $evidence->lineageDirectionFor($a, $context));
            $this->assertSame(Direction::Negative, $evidence->lineageDirectionFor($b, $context));
            $ids = app(BiasGateService::class)->forPhase($f->phase->fresh(), $context)->pluck('id');
            $this->assertContains($a->id, $ids);
            $this->assertContains($b->id, $ids);
        }
        // The candidate cannot bring its own comparison over to its new majority.
        $this->vote($f, $a, 'refute');
        $this->vote($f, $a, 'refute');
        $this->assertSame(Direction::Positive, app(AttestationEvidenceService::class)->lineageDirectionFor($a));
        $this->assertNotContains($a->id, app(BiasGateService::class)->forPhase($f->phase->fresh())->pluck('id'));
    }

    public function test_reconciled_lineage_deduplicates_shared_ancestry_and_retains_direction_at_zero(): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $shared = $f->node($f->seed, 'activity');
        $left = $f->node($shared, 'activity');
        $right = $f->node($shared, 'activity');
        $head = $f->node($left, 'frontier', 'reconcile');
        $f->edge($right, $head, 'reconcile');
        $this->vote($f, $left, 'refute');
        $this->vote($f, $right, 'refute');
        $this->vote($f, $shared, 'support');
        $service = app(AttestationEvidenceService::class);
        $this->assertEqualsCanonicalizing([$shared->id, $left->id, $right->id], $service->lineageSourceIds($head)->all());
        $this->assertSame(Direction::Negative, $service->lineageDirectionFor($head));
        $this->vote($f, $shared, 'support'); // net zero, retain the negative history
        $this->assertSame(Direction::Negative, $service->lineageDirectionFor($head));
        $this->vote($f, $head, 'support');
        $this->assertSame(Direction::Negative, $service->lineageDirectionFor($head));
        $context = new ParticipationContext($f->phase->fresh());
        $this->assertSame(Direction::Negative, $service->lineageDirectionFor($head, $context));
    }

    private function vote(ParticipantContextFixture $f, Proposition $node, string $direction): void
    {
        $this->receiptFixture($node);
        $actor = new Participant;
        $actor->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'witness-'.str()->ulid()])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $actor->id, 'sequence' => 1, 'completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Persist, 'write_register' => $node->register, 'started_at' => now(), 'completed_at' => now()])->save();
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $phase->id, 'proposition_id' => $node->id, 'direction' => $direction])->save();
        app(LedgerService::class)->record($attestation);
    }
}
