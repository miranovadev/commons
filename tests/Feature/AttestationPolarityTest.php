<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection as Direction;
use App\Enums\PhaseKind;
use App\Enums\RegisterPosition;
use App\Models\{Attestation, Cycle, Participant, Phase, Proposition, Substrate, Supersession};
use App\Services\{AttestationEvidenceService, BiasGateService, CommitRegionPolarityService, ContributionDispatcher, CycleEntryService, LedgerService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttestationPolarityTest extends TestCase
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
        $this->participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
        app(CycleEntryService::class)->enter($this->participant, [
            'statement' => 'Origin', 'attestation' => ['direction' => $label],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin'],
        ]);
        $this->origin = Proposition::findOrFail($this->participant->fresh()->current_proposition_id);
        $this->history = $this->origin->phase;
    }

    public static function labels(): array { return [['support', Direction::Positive], ['refute', Direction::Negative]]; }

    #[DataProvider('labels')]
    public function test_origin_participant_labels_the_fixed_positive_initial_direction(string $label, Direction $support): void
    {
        $this->bootstrap($label);
        $evidence = app(AttestationEvidenceService::class);
        $polarity = app(CommitRegionPolarityService::class);
        $this->assertSame(['governing' => Direction::Positive, 'support' => $support], $polarity->forSeed($this->origin));
        $this->assertSame(Direction::Positive, $evidence->directionFor($this->origin));
        $this->assertSame(1.0, $evidence->valueFor(collect([$this->origin]), $this->participant->substrate));
        $this->assertSame($label, $this->origin->attestations()->firstOrFail()->direction);
        // Computed bootstrap Chirality/Tension are separate from initial Attestation polarity.
        $this->assertSame(-1.0, $this->origin->commitmentSnapshot->chirality);
        $this->assertSame(-1.0, $this->origin->commitmentSnapshot->tension);
        $child = $this->node('stance', $this->origin);
        $this->assertSame(Direction::Positive, $evidence->directionFor($child));
        $this->attest($child, 'support');
        $this->assertSame($support, $evidence->directionFor($child));
    }

    public static function regions(): array
    {
        return [[Direction::Positive, 'stance', 'concord'], [Direction::Negative, 'stance', 'concord'],
            [Direction::Positive, 'frontier', 'canon'], [Direction::Negative, 'frontier', 'canon']];
    }

    #[DataProvider('regions')]
    public function test_fixed_region_polarity_and_default_do_not_follow_expressive_parent(Direction $governing, string $gateRegister, string $write): void
    {
        $this->bootstrap();
        $seed = $this->seed($governing, $write);
        $a = $this->node($gateRegister, $seed);
        $evidence = app(AttestationEvidenceService::class);
        $this->assertSame($governing, $evidence->directionFor($a));
        $this->attest($a, 'refute');
        $opposite = $governing === Direction::Positive ? Direction::Negative : Direction::Positive;
        $this->assertSame($opposite, $evidence->directionFor($a));
        $child = $this->node($gateRegister, $a);
        $this->assertSame($governing, $evidence->directionFor($child));
        $this->assertCount(0, $child->attestations);
        $this->attest($child, 'support');
        $this->assertSame($governing, $evidence->directionFor($child));
        $this->attest($a, 'support');
        $this->assertSame(0.0, $evidence->valueFor(collect([$a]), $this->participant->substrate));
        $this->assertSame($opposite, $evidence->directionFor($a));
        $this->assertSame($governing, app(CommitRegionPolarityService::class)->forSeed($seed)['support']);
        $this->attest($a, 'support');
        $this->assertSame($governing, $evidence->directionFor($a));
    }

    #[DataProvider('regions')]
    public function test_bias_aggregate_retains_its_own_direction_at_zero_and_replays_hard_removal(Direction $majority, string $register, string $write): void
    {
        $this->bootstrap();
        $seed = $this->seed($majority, $write);
        $left = $this->node('signal', $seed);
        $right = $this->node('activity', $seed);
        $major = $this->node($register, $left);
        $gate = $this->node($register, $right);
        // The traversed Gate opposes the majority. No plan is read while history evolves.
        $this->attest($major, 'support');
        $this->attest($major, 'support');
        $this->attest($gate, 'refute');
        $phase = $this->phase(PhaseKind::Bias, $write, $gate);
        $bias = app(BiasGateService::class);
        $evidence = app(AttestationEvidenceService::class);
        $this->assertSame([$major->id], $bias->forPhase($phase)->pluck('id')->all());
        $this->attest($gate, 'refute');
        $this->assertSame(0.0, $bias->leanFor($phase));
        $this->assertSame($majority, $evidence->populationDirection($seed, RegisterPosition::Second));
        $this->assertNotSame($majority, $evidence->directionFor($gate));
        $this->assertSame([$major->id], $bias->forPhase($phase)->pluck('id')->all());

        $outsider = $this->node($register, $this->seed($majority, $write));
        $wrongRegister = $this->node('signal', $seed);
        foreach ([$outsider, $wrongRegister] as $excluded) {
            foreach (range(1, 3) as $_) { $this->attest($excluded, 'refute'); }
        }
        $this->assertSame([$major->id], $bias->forPhase($phase)->pluck('id')->all());
        $counts = [Proposition::count(), $this->participant->substrate->ledgerEntries()->count()];
        $evidence->populationDirection($seed, RegisterPosition::Second);
        $this->assertSame($counts, [Proposition::count(), $this->participant->substrate->ledgerEntries()->count()]);
        $target = app(ContributionDispatcher::class)->dispatch($this->participant, $phase,
            ['action' => 'proposition.bias', 'proposition_id' => $major->id]);
        $this->assertSame($major->statement, $target->statement);
        $this->assertSame([$gate->id], $bias->populationFor($phase)->pluck('id')->all());
        $this->assertSame([$gate->id], $bias->forPhase($phase)->pluck('id')->all());
        $this->assertCount(0, $target->attestations);
    }

    #[DataProvider('regions')]
    public function test_evidence_zero_hysteresis_is_reconstructed_without_intermediate_reads(Direction $governing, string $register, string $write): void
    {
        $this->bootstrap();
        $seed = $this->seed($governing, $write);
        $position = $this->node($register, $seed);
        $this->attest($position, 'refute');
        $this->attest($position, 'support');
        $evidence = app(AttestationEvidenceService::class);
        $this->assertSame(0.0, $evidence->valueFor(collect([$position]), $this->participant->substrate));
        $this->assertNotSame($governing, $evidence->directionFor($position));
        $this->assertNotSame($governing, $evidence->populationDirection($seed, RegisterPosition::Second));
    }

    public function test_commit_polarity_backfill_uses_historical_source_direction_and_does_not_relabel_inherited_evidence(): void
    {
        $this->bootstrap();
        $source = $this->node('concord', $this->origin);
        $original = $this->attest($source, 'refute');
        $commit = $this->node('concord', $source, 'committal', 'commit');
        // Historical Commit had negative direction. Later evidence cannot change its frame.
        $this->attest($source, 'support', $original);
        $polarity = app(CommitRegionPolarityService::class);
        $polarity->backfill($commit);
        $this->assertSame(Direction::Negative, $polarity->forSeed($commit)['support']);
        $this->assertSame(1.0, app(AttestationEvidenceService::class)->valueFor(collect([$commit]), $this->participant->substrate));
        $child = $this->node('stance', $commit);
        $this->assertSame(Direction::Negative, app(AttestationEvidenceService::class)->directionFor($child));
        $this->assertSame(Direction::Positive, $polarity->forSeed($this->origin)['support']);
    }

    private function seed(Direction $direction, string $register): Proposition
    {
        $source = $this->node($register, $this->origin);
        $this->attest($source, $direction === Direction::Positive ? 'support' : 'refute');
        $seed = $this->node($register, $source, 'committal', 'commit');
        app(CommitRegionPolarityService::class)->establishCommit($seed, $direction);
        return $seed;
    }

    private function node(string $register, Proposition $parent, string $tier = 'expressive', string $type = 'variant'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->history->id, 'register' => $register, 'tier' => $tier, 'statement' => 'Node '.Proposition::count()])->save();
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
