<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection as Direction;
use App\Enums\PhaseKind;
use App\Enums\RegisterPosition;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\AttestationEvidenceService;
use App\Services\BiasGateService;
use App\Services\CommitRegionPolarityService;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\Observables\ReinforcementService;
use App\Services\PropositionMeasurementService;
use App\Services\ReceiptContextService;
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

    public static function labels(): array
    {
        return [['support', Direction::Positive], ['refute', Direction::Negative]];
    }

    #[DataProvider('labels')]
    public function test_origin_participant_labels_the_fixed_positive_initial_direction(string $label, Direction $support): void
    {
        $this->bootstrap($label);
        $evidence = app(AttestationEvidenceService::class);
        $polarity = app(CommitRegionPolarityService::class);
        $this->assertSame(['governing' => Direction::Positive, 'support' => $support], $polarity->forSeed($this->origin));
        $this->assertSame(Direction::Positive, $evidence->directionFor($this->origin));
        $this->assertSame(1.0, $evidence->valueFor(collect([$this->origin])));
        $this->assertSame($label, $this->origin->attestations()->firstOrFail()->direction);
        // Computed bootstrap Chirality/Tension are separate from initial Attestation polarity.
        $this->assertSame(-0.5, $this->origin->commitmentSnapshot->chirality);
        $this->assertSame(1.0, $this->origin->commitmentSnapshot->correspondence);
        $this->assertEqualsWithDelta(-1 / 6, $this->origin->commitmentSnapshot->tension, 1e-7);
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
        $seed = $this->regionSeed($governing, $write);
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
        $this->assertSame(0.0, $evidence->valueFor(collect([$a])));
        $this->assertSame($opposite, $evidence->directionFor($a));
        $this->assertSame($governing, app(CommitRegionPolarityService::class)->forSeed($seed)['support']);
        $this->attest($a, 'support');
        $this->assertSame($governing, $evidence->directionFor($a));
    }

    #[DataProvider('regions')]
    public function test_bias_aggregate_retains_its_own_direction_at_zero_and_replays_hard_removal(Direction $majority, string $register, string $write): void
    {
        $this->bootstrap();
        $seed = $this->regionSeed($majority, $write);
        $first = $seed->phase->cycle->contextHorizon->domain->registerAt(RegisterPosition::First);
        $left = $this->node($first, $seed);
        $right = $this->node($first, $seed);
        $major = $this->node($register, $left, position: PhaseKind::Mediate);
        $gate = $this->node($register, $right, position: PhaseKind::Mediate);
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
        $this->assertSame($majority, $evidence->populationDirection($seed, $register));
        $this->assertNotSame($majority, $evidence->directionFor($gate));
        $this->assertSame([$major->id], $bias->forPhase($phase)->pluck('id')->all());

        $outsider = $this->node($register, $this->regionSeed($majority, $write), position: PhaseKind::Mediate);
        $wrongRegister = $this->node('signal', $seed);
        foreach ([$outsider, $wrongRegister] as $excluded) {
            foreach (range(1, 3) as $_) {
                $this->attest($excluded, 'refute');
            }
        }
        $this->assertSame([$major->id], $bias->forPhase($phase)->pluck('id')->all());
        $counts = [Proposition::count(), $this->participant->substrate->ledgerEntries()->count()];
        $evidence->populationDirection($seed, $register);
        $this->assertSame($counts, [Proposition::count(), $this->participant->substrate->ledgerEntries()->count()]);
        $this->receiptFixture($major);
        // Reclassification is exercised from the candidate's own causal Gate.
        $this->coherentChiralityFixture($major);
        $phase = $this->phase(PhaseKind::Bias, $write, $major);
        $target = app(ContributionDispatcher::class)->dispatch($this->participant, $phase,
            $this->reclassificationInputFixture($phase, $major));
        $this->assertSame($major->statement, $target->statement);
        $this->assertSame([$gate->id], $bias->populationFor($phase)->pluck('id')->all());
        $this->assertSame([], $bias->forPhase($phase)->pluck('id')->all()); // Removing a sibling does not change this head's lineage.
        $this->assertCount(0, $target->attestations);
    }

    #[DataProvider('regions')]
    public function test_evidence_zero_hysteresis_is_reconstructed_without_intermediate_reads(Direction $governing, string $register, string $write): void
    {
        $this->bootstrap();
        $seed = $this->regionSeed($governing, $write);
        $position = $this->node($register, $seed);
        $this->attest($position, 'refute');
        $this->attest($position, 'support');
        $evidence = app(AttestationEvidenceService::class);
        $this->assertSame(0.0, $evidence->valueFor(collect([$position])));
        $this->assertNotSame($governing, $evidence->directionFor($position));
        $this->assertNotSame($governing, $evidence->populationDirection($seed, $register));
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
        $this->assertSame(1.0, app(AttestationEvidenceService::class)->valueFor(collect([$commit])));
        $child = $this->node('stance', $commit);
        $this->assertSame(Direction::Negative, app(AttestationEvidenceService::class)->directionFor($child));
        $this->assertSame(Direction::Positive, $polarity->forSeed($this->origin)['support']);
    }

    #[DataProvider('regions')]
    public function test_receipt_load_and_clear_cannot_change_neutral_lean_or_direction(Direction $governing, string $register, string $write): void
    {
        $this->bootstrap();
        $seed = $this->regionSeed($governing, $write);
        $position = $this->node($register, $seed);
        $this->attest($position, 'support');
        $refute = $this->attest($position, 'refute');
        $evidence = app(AttestationEvidenceService::class);
        $count = app(ReinforcementService::class);
        $assertNeutral = function () use ($evidence, $position, $seed, $register, $governing): void {
            $this->assertSame(0.0, $evidence->valueFor(collect([$position])));
            $this->assertSame($governing, $evidence->directionFor($position));
            $this->assertSame($governing, $evidence->populationDirection($seed, $register));
        };
        $assertNeutral();
        $this->assertSame(0, $count->count($position));
        // Reinforcing only Refute used to reverse the retained Support direction.
        $receipt = $this->loadedReceiptFixture($position, $refute);
        $this->assertSame(1, $count->count($position));
        $assertNeutral();
        $phase = $this->phase(PhaseKind::Order, $register, $position, $refute->phase->cycle->participant);
        app(ReceiptContextService::class)->clear($phase->cycle->participant, $phase, $receipt->id);
        $this->assertSame(0, $count->count($position));
        $assertNeutral();
    }

    public function test_upgrade_reconstructs_polarity_and_keeps_immutable_measurement_snapshots(): void
    {
        $this->bootstrap('refute');
        $source = $this->node('concord', $this->origin);
        $old = $this->attest($source, 'support'); // Negative in this Origin's frame.
        $commit = $this->node('concord', $source, 'committal', 'commit');
        $this->attest($source, 'refute', $old); // Positive only after the Commit.
        app(PropositionMeasurementService::class)->project($source, 0.5, 0, 0);
        $snapshot = $this->origin->commitmentSnapshot->getAttributes();
        $migration = require database_path('migrations/2026_09_12_000001_retain_region_polarity_and_correspondence.php');
        $migration->down();
        $migration->up();
        $frames = app(CommitRegionPolarityService::class);
        $this->assertSame(['governing' => Direction::Positive, 'support' => Direction::Negative], $frames->forSeed($this->origin));
        $this->assertSame(['governing' => Direction::Negative, 'support' => Direction::Negative], $frames->forSeed($commit));
        $this->assertSame('positive', $source->fresh()->correspondence_direction);
        $this->assertNull($source->fresh()->measurement->calculator_version);
        $this->assertSame($snapshot, $this->origin->commitmentSnapshot->fresh()->getAttributes());
    }

    private function regionSeed(Direction $direction, string $register): Proposition
    {
        $source = $this->node($register, $this->origin);
        $this->attest($source, $direction === Direction::Positive ? 'support' : 'refute');
        $seed = $this->node($register, $source, 'committal', 'commit');
        app(CommitRegionPolarityService::class)->establishCommit($seed, $direction);

        return $seed;
    }

    private function node(string $register, Proposition $parent, string $tier = 'expressive', string $type = 'variant', ?PhaseKind $position = null): Proposition
    {
        $governor = $parent->tier === 'committal' ? $parent : $parent->phase->cycle->entryCommittal;
        $horizon = app(ContextHorizonProjector::class)->forEntry($governor);
        if ($horizon->domain->registerAt(RegisterPosition::forRegister($register)) !== $register) {
            $horizon = $this->laterHorizonFixture($horizon, 1);
            $governor = Proposition::findOrFail($horizon->opened_by_proposition_id);
            $parent = $governor;
        }
        $base = $this->history->replicate();
        $base->kind = $position ?? $this->history->kind;
        $base->write_register = $register;
        $base = $this->governedPhaseFixture($base, $governor, $parent);
        $node = new Proposition;
        $node->forceFill(['phase_id' => $base->id, 'register' => $register, 'tier' => $tier, 'statement' => 'Node '.Proposition::count()])->save();
        app(LedgerService::class)->record($node, $parent);
        if ($tier === 'committal') {
            $this->freezeRegionFixture($node);
        }
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $this->history->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $node->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
        if ($tier === 'expressive') {
            \Tests\Fixtures\PropositionBirth::complete($node);
        }

        return $node;
    }

    private function phase(PhaseKind $kind, string $register, ?Proposition $gate = null, ?Participant $participant = null): Phase
    {
        $participant ??= $this->participant;
        $cycle = new Cycle;
        $cycle->context_horizon_id = $gate?->phase->cycle->context_horizon_id
            ?? $this->horizonForRegisterFixture($participant, $register)->id;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1,
            'entry_committal_id' => $gate?->tier === 'committal' ? $gate->id : $gate?->phase->cycle->entry_committal_id])->save();
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
