<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection as Direction;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitRegionPolarityService;
use App\Services\CycleEntryService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PropositionPresentationTest extends TestCase
{
    use RefreshDatabase;

    private Participant $participant;

    private Phase $history;

    private Proposition $origin;

    private function bootstrap(string $label = 'support'): void
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'polarity', 'title' => 'Polarity', 'visibility' => 'private', 'configuration' => []])->save();
        $this->participant = new Participant;
        $this->participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer', 'max_read_count' => 25])->save();
        app(CycleEntryService::class)->enter($this->participant, [
            'statement' => 'Origin', 'attestation' => ['direction' => $label],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin'],
        ]);
        $this->origin = Proposition::findOrFail($this->participant->fresh()->current_proposition_id);
        $this->history = $this->origin->phase;
    }

    public static function labels(): array
    {
        return [['support', Direction::Positive], ['refute', Direction::Negative]];
    }

    #[DataProvider('labels')]
    public function test_participation_exposes_the_origin_frame_independently_of_numeric_measurements(string $label, Direction $support): void
    {
        $this->bootstrap($label);
        $plan = app(InputPlanService::class)->forParticipant($this->participant);
        $this->assertSame(['support_direction' => $support->value, 'positive' => 1, 'negative' => 0, 'retained_direction' => 'positive'], $plan['gate']['attestation_summary']);
        $this->assertSame(1, $plan['gate']['attestation_counts'][$label]);
        $this->assertEquals(-1, $plan['gate']['observables']['chirality']);
    }

    public function test_display_counts_keep_inherited_evidence_in_its_original_frame_and_refresh_after_replacement(): void
    {
        $this->bootstrap();
        $source = $this->node('concord', $this->origin);
        $original = $this->attest($source, 'refute'); // Negative in the original positive-support region.
        $commit = $this->node('concord', $source, 'committal', 'commit');
        app(CommitRegionPolarityService::class)->establishCommit($commit, Direction::Negative);
        $this->attest($commit, 'refute'); // Positive in the new negative-support region.
        $phase = $this->phase(PhaseKind::Distinguish, 'signal', $commit);
        $presentation = app(PropositionPresentationService::class);
        $context = new ParticipationContext($phase);
        $expected = ['support_direction' => 'negative', 'positive' => 1, 'negative' => 1, 'retained_direction' => 'negative'];
        $this->assertSame($expected, $presentation->attestations($commit));
        $this->assertSame($expected, $presentation->attestations($commit, $context));
        $this->attest($source, 'support', $original);
        $fresh = new ParticipationContext($phase);
        $this->assertSame(['support_direction' => 'negative', 'positive' => 2, 'negative' => 0, 'retained_direction' => 'positive'], $presentation->attestations($commit, $fresh));
        $this->assertSame($expected, $presentation->attestations($commit, $context));
    }

    public function test_zero_and_tied_counts_expose_the_existing_retained_direction_without_context_queries(): void
    {
        $this->bootstrap();
        $source = $this->node('stance', $this->origin);
        $presentation = app(PropositionPresentationService::class);
        $this->assertSame('positive', $presentation->attestations($source)['retained_direction']);
        $this->attest($source, 'refute');
        $this->attest($source, 'support');
        $expected = ['support_direction' => 'positive', 'positive' => 1, 'negative' => 1, 'retained_direction' => 'negative'];
        $this->assertSame($expected, $presentation->attestations($source));
        $context = new ParticipationContext($this->phase(PhaseKind::Constrain, 'concord', $source));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($expected, $presentation->attestations($source, $context));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(0, $queries);
    }

    public function test_reconciliation_display_source_is_stable_when_hydration_order_changes(): void
    {
        $this->freezeTime();
        $this->bootstrap();
        $older = $this->node('concord', $this->origin);
        $primary = $this->node('concord', $this->origin);
        $target = $this->node('concord', $primary, 'expressive', 'reconcile');
        $first = Supersession::where('target_proposition_id', $target->id)->firstOrFail();
        $first->forceFill(['created_at' => now()->subMinute()])->save();
        $second = new Supersession;
        $second->forceFill(['phase_id' => $this->history->id, 'source_proposition_id' => $older->id,
            'target_proposition_id' => $target->id, 'type' => 'reconcile'])->save();
        $context = new ParticipationContext($this->phase(PhaseKind::Mediate, 'stance', $target));
        $context->parents->put($target->id, collect([$second, $first]));
        $target->setRelation('parents', $target->parents()->get()->reverse());
        $presentation = app(PropositionPresentationService::class);
        $expected = ['type' => 'reconcile', 'from' => 'concord', 'to' => 'concord', 'source_id' => $primary->id];
        $this->assertSame($expected, $presentation->transition($target));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($expected, $presentation->transition($target, $context));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(0, $queries);
    }

    private function node(string $register, Proposition $parent, string $tier = 'expressive', string $type = 'variant'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->phaseForRegisterFixture($this->history, $register)->id, 'register' => $register, 'tier' => $tier, 'statement' => 'Node '.Proposition::count()])->save();
        app(LedgerService::class)->record($node);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $this->history->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $node->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);

        return $node;
    }

    private function phase(PhaseKind $kind, string $register, ?Proposition $gate = null, ?Participant $participant = null): Phase
    {
        $participant ??= $this->participant;
        $cycle = new Cycle;
        $cycle->context_horizon_id = $gate?->phase->cycle->context_horizon_id
            ?? $this->horizonForRegisterFixture($participant, $register)->id;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register,
            'gate_proposition_id' => $gate?->id, 'started_at' => now()])->save();

        return $phase;
    }

    private function attest(Proposition $position, string $label, ?Attestation $supersedes = null): Attestation
    {
        $witness = $supersedes?->phase->cycle->participant;
        if ($witness === null) {
            $witness = new Participant;
            $witness->forceFill(['substrate_id' => $this->participant->substrate_id, 'callsign' => 'witness-'.Attestation::count()])->save();
        }
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $this->phase(PhaseKind::Persist, $position->register, participant: $witness)->id,
            'proposition_id' => $position->id, 'direction' => $label,
            'supersedes_attestation_id' => $supersedes?->id])->save();
        app(LedgerService::class)->record($attestation);

        return $attestation;
    }
}
