<?php

namespace Tests;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\CorrespondenceValues;
use App\Domain\Observables\LocalMeasurements;
use App\Enums\PhaseKind;
use App\Enums\RegisterPosition;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\ReceiptConsumption;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Services\AcquisitionAttemptService;
use App\Services\ContextHorizonProjector;
use App\Services\Evidence\ArtifactStore;
use App\Services\Evidence\HyperlinkIdentity;
use App\Services\Evidence\HyperlinkMetadataExtractor;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\PublicResourceRetriever;
use App\Services\EvidenceExecutionService;
use App\Services\HorizonReceiptService;
use App\Services\LedgerService;
use App\Services\Observables\ClosureBirthService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\OperationalInstrumentService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionStateService;
use App\Services\ReceiptContextService;
use App\Services\TragedyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\Fixtures\PropositionBirth;

abstract class TestCase extends BaseTestCase
{
    /** Frozen numerical outputs of explicitly controlled Commit fixtures only. */
    private array $controlledCanonicalClosure = [];

    /** Controlled observable output; persistence and canonical field projection stay real. */
    protected function resolveExpressiveFieldFixture(float $value): void
    {
        $this->controlledBirthClosureFixture($value);
        $this->mock(LocalMeasurementService::class, function ($mock) use ($value): void {
            $mock->shouldReceive('chiralityComponents')->andReturn(new ChiralityValues($value, $value));
            $mock->shouldReceive('calculate')->andReturn(new LocalMeasurements($value, $value, $value, $value,
                chiralityComponents: new ChiralityValues($value, $value),
                correspondenceComponents: new CorrespondenceValues($value, $value)));
        });
    }

    /** Routing fixtures author their fields; they do not model committed Closure runs. */
    protected function controlledClosureFixture(float $closure): void
    {
        $this->controlledBirthClosureFixture($closure);
        $calculator = app(LocalMeasurementService::class);
        $this->partialMock(LocalMeasurementService::class, function ($mock) use ($calculator, $closure): void {
            $mock->shouldReceive('calculate')->andReturnUsing(function ($node, $comparison = null, $includeClosure = true) use ($calculator, $closure) {
                if ($node->tier === 'committal') {
                    return $calculator->calculate($node, $comparison, $includeClosure);
                }
                $raw = $calculator->calculate($node, $comparison, false);
                $value = $node->commitmentSnapshot?->closure ?? $closure;

                return new LocalMeasurements($raw->correspondence, $raw->chirality, $value, $value,
                    chiralityComponents: $raw->chiralityComponents, correspondenceComponents: $raw->correspondenceComponents);
            });
            $mock->shouldReceive('chiralityComponents')->andReturnUsing(fn ($node) => $calculator->chiralityComponents($node));
        });
    }

    /** Isolated field/lifecycle tests control Closure too; birth-invariant tests never use this double. */
    protected function controlledBirthClosureFixture(float $value, ?string $onlyId = null, bool $includeCommittal = false): void
    {
        // A whole-field routing double controls the new persistence boundary too.
        // It must not demand real historical measurements before returning its
        // explicitly authored number. No synthetic Closure history is persisted.
        // Proposition-specific doubles retain real birth/Cycle construction.
        $prospective = app(ProspectiveClosureService::class);
        $this->partialMock(ProspectiveClosureService::class, function ($mock) use ($prospective, $value, $onlyId, $includeCommittal): void {
            $mock->shouldReceive('initialize')->andReturnUsing(function ($node) use ($prospective, $onlyId): void {
                if ($onlyId !== null || $node->tier === 'committal') {
                    $prospective->initialize($node);
                }
            });
            $mock->shouldReceive('entered')->andReturnUsing(function ($cycle) use ($prospective, $onlyId): void {
                if ($onlyId !== null) {
                    $prospective->entered($cycle);
                }
            });
            $mock->shouldReceive('stored')->andReturnUsing(function ($node, $context = null) use ($prospective, $value, $onlyId, $includeCommittal) {
                if (isset($this->controlledCanonicalClosure[$node->id])) {
                    return $this->controlledCanonicalClosure[$node->id];
                }
                if ((! $includeCommittal && $node->tier === 'committal') || ($onlyId !== null && $node->id !== $onlyId)) {
                    return $prospective->stored($node, $context);
                }
                $observation = $onlyId !== null ? $prospective->stored($node, $context) : [
                    // Existing interval metadata is used only by snapshot plumbing;
                    // this double supplies no historical observation population.
                    'cadence_interval' => app(ClosureBirthService::class)->stored($node)['cadence_interval'],
                    'history_proposition_id' => $node->phase->cycle->entry_committal_id,
                    'historical_phase_profile' => $value, 'historical_orientation' => $value,
                    'fixture' => 'controlled_closure_output',
                ];
                foreach (['depth', 'cadence', 'historical_extent', 'historical_rhythm', 'closure'] as $key) {
                    $observation[$key] = $value;
                    $observation[$key.'_direction'] = $value > 0 ? 'positive' : 'negative';
                }
                $observation['admissible'] = true;

                return $observation;
            });
            $mock->shouldReceive('preserve')->andReturnUsing(function ($target, $source, $expected) use ($prospective, $onlyId): void {
                if (method_exists($this, 'isolateClosureOutput')) {
                    throw new \LogicException('Component fixtures cannot establish canonical Commit preservation.');
                }
                if ($onlyId !== null) {
                    $prospective->preserve($target, $source, $expected);

                    return;
                }
                $this->assertSame('expressive', $source->tier);
                // A later proposition-specific double may wrap this one. Verify
                // against that active output, not an older captured field value.
                $this->assertSame(app(ProspectiveClosureService::class)->stored($source), $expected);
                $this->controlledCanonicalClosure[$target->id] = $expected;
            });
        });
        $births = app(ClosureBirthService::class);
        $this->partialMock(ClosureBirthService::class, function ($mock) use ($births, $value, $onlyId, $includeCommittal): void {
            foreach (['stored', 'prospective'] as $method) {
                $mock->shouldReceive($method)->andReturnUsing(function ($node, $context = null) use ($births, $value, $onlyId, $method, $includeCommittal) {
                    $observation = $method === 'stored' ? $births->stored($node, $context) : $births->prospective($node);
                    if ((! $includeCommittal && $node->tier === 'committal') || ($onlyId !== null && $node->id !== $onlyId)) {
                        return $observation;
                    }
                    foreach (['depth', 'lateral_committal_position', 'committed_cardinal_position', 'cadence', 'closure'] as $key) {
                        $observation[$key] = $value;
                        $observation[$key.'_direction'] = $value > 0 ? 'positive' : 'negative';
                    }
                    $observation['admissible'] = true;

                    return $observation;
                });
            }
        });
    }

    /** Isolate ordinary authority tests from the additional Bias coherence condition. */
    protected function coherentChiralityFixture(Proposition $source): void
    {
        $service = app(PropositionMeasurementService::class);
        $source->unsetRelation('measurement');
        $service->observeMissing(collect([$source]));
        $values = $source->measurement;
        $service->project($source, $values->correspondence, $values->chirality, $values->closure,
            chiralityComponents: new ChiralityValues(.5, .5));
    }

    /** Receipt/visibility component fixture: a separate controlled branch, never a revived target. */
    protected function tragedyBesideFixture(Proposition $viable): Tragedy
    {
        $seed = Proposition::findOrFail($viable->phase->cycle->entry_committal_id);

        return $this->boundaryTragedyFixture($seed);
    }

    /** Explicit controlled birth fixture; natural acceptance uses real contributions instead. */
    protected function boundaryTragedyFixture(Proposition $terminal): Tragedy
    {
        if ($terminal->tier === 'committal') {
            $seed = $terminal;
            $horizon = app(ContextHorizonProjector::class)->forEntry($seed);
            $participant = $seed->phase->cycle->participant;
            $cycle = (new Cycle)->forceFill(['participant_id' => $participant->id,
                'sequence' => (int) $participant->cycles()->max('sequence') + 1,
                'context_horizon_id' => $horizon->id, 'entry_committal_id' => $seed->id]);
            $cycle->save();
            $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish,
                'gate_proposition_id' => $seed->id, 'started_at' => now(),
                'write_register' => $horizon->domain->registerFor(PhaseKind::Distinguish)]);
            $phase->save();
            $terminal = (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
                'tier' => 'expressive', 'statement' => 'Explicit Charge boundary fixture']);
            $terminal->save();
            app(LedgerService::class)->record($terminal, $seed);
            $edge = (new Supersession)->forceFill(['phase_id' => $phase->id,
                'source_proposition_id' => $seed->id, 'target_proposition_id' => $terminal->id, 'type' => 'variant']);
            $edge->save();
            app(LedgerService::class)->record($edge);
            PropositionBirth::complete($terminal);
        }
        $this->controlledBirthClosureFixture(1, $terminal->id);
        $terminal->wasRecentlyCreated = true; // The fixture explicitly supplies the birth event.
        $tragedy = DB::transaction(function () use ($terminal) {
            app(PropositionMeasurementService::class)->project($terminal, 1, 1, 1);

            return app(TragedyService::class)->record($terminal, $terminal->phase, 'proposition.variant');
        });
        $this->assertNotNull($tragedy);
        $this->publishPotentialFixture($terminal->phase->cycle->participant);

        return $tragedy;
    }

    /** Existing isolated ownership fixtures; complete Commit histories use the strict helper below. */
    protected function freezeRegionFixture(Proposition $seed, float $correspondence = 0): void
    {
        $this->freezeRegionSnapshotFixture($seed, $correspondence, null);
    }

    /** Freeze a complete authored Commit history, without repairing immutable snapshots. */
    protected function freezeCommittedRegionFixture(Proposition $seed, float $correspondence = 0): void
    {
        $commit = $this->commitSupersessionFixture($seed);
        $existing = $seed->commitmentSnapshot()->first();
        if ($existing !== null && $existing->supersession_id !== $commit?->id) {
            throw new \DomainException('Fixture snapshot does not identify its genuine Commit Supersession: '.$seed->id);
        }
        $this->freezeRegionSnapshotFixture($seed, $correspondence, $commit?->id);
    }

    /** The unique parentless Origin is the only nullable Commit source. */
    protected function commitSupersessionFixture(Proposition $seed): ?Supersession
    {
        if ($seed->tier !== 'committal') {
            throw new \DomainException('A committed fixture snapshot requires a Committal.');
        }
        $parents = Supersession::where('target_proposition_id', $seed->id)->get();
        $commits = $parents->where('type', 'commit');
        $horizon = $seed->phase->cycle->contextHorizon;
        $originId = SubstrateLedgerEntry::where('substrate_id', $horizon->substrate_id)
            ->whereHasMorph('ledgerable', [Proposition::class], fn ($q) => $q->where('tier', 'committal'))
            ->orderBy('sequence')->value('ledgerable_id');
        $isOrigin = $seed->id === $originId && $horizon->parent_context_horizon_id === null
            && $seed->phase->cycle->entry_committal_id === null;
        if ($isOrigin && $parents->isEmpty()) {
            return null;
        }
        if ($isOrigin || $commits->count() !== 1 || $parents->count() !== 1) {
            throw new \DomainException('An ordinary fixture Committal requires exactly one genuine Commit parent: '.$seed->id);
        }
        $commit = $commits->sole();
        if ($commit->phase_id !== $seed->phase_id
            || $commit->source_proposition_id !== $seed->phase->fresh()->gate_proposition_id) {
            throw new \DomainException('The fixture Commit must identify its producing Phase and selected source: '.$seed->id);
        }

        return $commit;
    }

    /** Explicit frozen non-boundary seed for isolated ownership fixtures. */
    private function freezeRegionSnapshotFixture(Proposition $seed, float $correspondence, ?string $supersessionId): void
    {
        if ($seed->commitmentSnapshot()->exists()) {
            return;
        }
        $horizon = $seed->phase->cycle->contextHorizon;
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill(['proposition_id' => $seed->id, 'substrate_id' => $horizon->substrate_id,
            'ledger_entry_id' => $seed->ledgerEntry->id, 'supersession_id' => $supersessionId, 'correspondence' => $correspondence, 'chirality' => 0, 'closure' => 0,
            'cadence_interval' => 1,
            'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
            'direction_before' => $horizon->domain, 'direction_after' => $horizon->domain,
            'calculator_version' => 'region-fixture', 'evaluated_at' => now()])->save();
        $seed->unsetRelation('commitmentSnapshot');
    }

    /** Record the real selected source when a fixture authors an ordinary Commit. */
    protected function commitAncestryFixture(Proposition $seed, Proposition $source): Supersession
    {
        $seed->phase->forceFill(['gate_proposition_id' => $source->id])->save();
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $seed->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $seed->id, 'type' => 'commit'])->save();
        app(LedgerService::class)->record($edge);

        return $edge;
    }

    /** Complete an isolated fixture's acquisition through the real Instrument boundary. */
    protected function instrumentGateFixture(Phase $phase): void
    {
        if (app(AcquisitionAttemptService::class)->state($phase)['status'] !== 'required') {
            return;
        }
        app(OperationalInstrumentService::class)->execute($phase, $phase->gate,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/personal-acquisition/'.str()->ulid()]);
        $this->publishPotentialFixture($phase->cycle->participant);
    }

    /** Fixture authors supply the governor before creating immutable history. */
    protected function governedPhaseFixture(Phase $base, Proposition $governor, ?Proposition $gate = null): Phase
    {
        $participant = $base->cycle->participant;
        $horizon = app(ContextHorizonProjector::class)->forEntry($governor);
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1,
            'context_horizon_id' => $horizon->id, 'entry_committal_id' => $governor->id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $base->kind, 'write_register' => $base->write_register,
            'gate_proposition_id' => $gate?->id, 'started_at' => now(), 'completed_at' => $base->completed_at])->save();

        return $phase;
    }

    /** Finish direct fixture writes before exercising an ordinary read or contribution. */
    protected function publishPotentialFixture(Participant|Substrate $subject, bool $recalculate = false): void
    {
        $id = $subject instanceof Substrate ? $subject->id : $subject->substrate_id;
        $this->assertTrue(app(PotentialProjectionService::class)->rebuild($id, $recalculate));
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Semantic fixtures use deterministic external evidence and never the network.
        // HyperlinkVerificationTest explicitly restores the real verifier/HTTP boundary.
        $this->app->bind(HyperlinkVerifier::class, function ($app) {
            return new class($app->make(HyperlinkIdentity::class), $app->make(PublicResourceRetriever::class), $app->make(ArtifactStore::class), $app->make(HyperlinkMetadataExtractor::class)) extends HyperlinkVerifier
            {
                public function verify(string $reference): VerifiedHyperlink
                {
                    $resource = app(HyperlinkIdentity::class)->resource($reference);

                    return new VerifiedHyperlink($resource, hash('sha256', $resource), CarbonImmutable::now());
                }
            };
        });
    }

    /** Archived pre-Stage-5 consumption only; never a live contribution allowance.
     * Keep the Stage 6 recovery/exhaustion proofs readable without authorizing
     * Constrain to consume a Tragedy in the current dispatcher.
     */
    protected function legacyTragedyClassificationFixture(Phase $phase, Proposition $source, string $receiptId): Proposition
    {
        return DB::transaction(function () use ($phase, $source, $receiptId): Proposition {
            $receipt = Receipt::findOrFail($receiptId);
            $this->assertSame('tragedy', $receipt->type);
            $target = (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
                'tier' => 'expressive', 'statement' => $source->statement]);
            $target->save();
            app(LedgerService::class)->record($target, $source);
            $edge = (new Supersession)->forceFill(['phase_id' => $phase->id,
                'source_proposition_id' => $source->id, 'target_proposition_id' => $target->id, 'type' => 'reclassify']);
            $edge->save();
            app(LedgerService::class)->record($edge);
            DB::table('reclassification_results')->insert([
                'source_proposition_id' => $source->id, 'register' => $phase->write_register, 'target_proposition_id' => $target->id]);
            (new ReceiptConsumption)->forceFill(['receipt_id' => $receiptId,
                'context_horizon_id' => $receipt->context_horizon_id, 'phase_id' => $phase->id,
                'source_proposition_id' => $source->id, 'proposition_id' => $target->id, 'consumed_at' => now()])->save();
            DB::table('proposition_receipt_links')->insert([
                'proposition_id' => $target->id, 'receipt_id' => $receiptId, 'created_at' => now()]);
            PropositionBirth::complete($target);

            return $target;
        });
    }

    /** Verified, explicitly loaded ordinary evidence for semantic fixtures. */
    protected function loadedReceiptFixture(Proposition $node, ?Attestation $subject = null): Receipt
    {
        $participant = $subject?->phase->cycle->participant ?? new Participant;
        if (! $participant->exists) {
            $participant->forceFill(['substrate_id' => $node->phase->cycle->participant->substrate_id,
                'callsign' => 'verifier-'.str()->ulid()])->save();
        }
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1,
            'context_horizon_id' => $node->phase->cycle->context_horizon_id,
            'entry_committal_id' => $node->phase->cycle->entry_committal_id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
            'gate_proposition_id' => $node->id, 'write_register' => $node->register, 'started_at' => now()])->save();
        $reference = 'https://example.test/verified/'.$node->id;
        $instruments = app(EvidenceExecutionService::class);
        $execution = $instruments->start($phase, $node, 'hyperlink', $reference);

        $receipt = $instruments->complete($execution, $subject ?? $node,
            verification: app(HyperlinkVerifier::class)->verify($reference));
        app(ReceiptContextService::class)->read($participant, $phase, $receipt->id);
        $phase->forceFill(['completed_at' => now()])->save();
        $cycle->forceFill(['completed_at' => now()])->save();

        return $receipt;
    }

    /** Authored read-model input for component tests only; this is not a lawful Domain-transition lifecycle. */
    protected function laterHorizonFixture(ContextHorizon $horizon, int $steps = 2): ContextHorizon
    {
        // By default, two causal boundaries return to the original vocabulary.
        foreach (range(1, $steps) as $step) {
            // Select the parent coordinate of this authored projection.
            // No action, Closure boundary or canonical source is being proven.
            $gate = $horizon->opened_by_proposition_id !== null
                ? Proposition::findOrFail($horizon->opened_by_proposition_id)
                : Proposition::whereHas('contextHorizonMembership', fn ($q) => $q->where('context_horizon_id', $horizon->id))
                    ->where('tier', 'committal')->oldest('id')->first();
            $participant = new Participant;
            $participant->forceFill(['substrate_id' => $horizon->substrate_id, 'callsign' => 'boundary-'.str()->ulid()])->save();
            $cycle = new Cycle;
            $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1,
                'context_horizon_id' => $horizon->id, 'entry_committal_id' => $gate?->id,
                'completed_at' => now()])->save();
            $phase = new Phase;
            $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
                'gate_proposition_id' => $gate?->id,
                'write_register' => $horizon->domain->registerFor(PhaseKind::Order), 'completed_at' => now()])->save();
            $node = new Proposition;
            $node->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
                'tier' => 'committal', 'statement' => 'Boundary fixture'])->save();
            $entry = app(LedgerService::class)->record($node);
            // A component projection is not a Commit event. Its entry field
            // describes the supplied coordinate; there is no fabricated edge.
            $snapshot = new CommitmentSnapshot;
            $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $horizon->substrate_id,
                'ledger_entry_id' => $entry->id, 'supersession_id' => null,
                'correspondence' => 0, 'chirality' => 0, 'closure' => 0,
                'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
                'direction_before' => $horizon->domain, 'direction_after' => $horizon->domain->opposite(),
                'cadence_interval' => $gate === null ? null : 1,
                'calculator_version' => 'scope-fixture', 'evaluated_at' => now()])->save();
            $horizon = app(ContextHorizonProjector::class)->apply($snapshot);
        }

        return $horizon;
    }

    /** Operational fixtures must own a Horizon matching their register vocabulary. */
    protected function horizonForRegisterFixture(Participant $participant, string $register): ContextHorizon
    {
        $horizon = app(ContextHorizonProjector::class)->currentFor($participant->substrate);
        $position = RegisterPosition::forRegister($register);

        return $horizon->domain->registerAt($position) === $register ? $horizon
            : (ContextHorizon::where('substrate_id', $participant->substrate_id)
                ->where('domain', $horizon->domain->opposite())->oldest('sequence')->first()
                ?? $this->laterHorizonFixture($horizon, 1));
    }

    /** Mixed-vocabulary topology fixtures still need a real creating Domain for every node. */
    protected function phaseForRegisterFixture(Phase $base, string $register, ?PhaseKind $position = null): Phase
    {
        if ($position !== null) {
            $base = $this->phaseAtPositionFixture($base, $position);
        }
        if ($base->cycle->contextHorizon->domain->registerAt(RegisterPosition::forRegister($register)) === $register) {
            return $base;
        }
        $participant = $base->cycle->participant;
        $horizon = $this->horizonForRegisterFixture($participant, $register);
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'context_horizon_id' => $horizon->id,
            'entry_committal_id' => $horizon->opened_by_proposition_id,
            'sequence' => (int) $participant->cycles()->max('sequence') + 1, 'completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $base->kind, 'write_register' => $register,
            'started_at' => now(), 'completed_at' => now()])->save();

        return $phase;
    }

    /** Explicit producing position for semantic fixtures; never inferred from register equality. */
    protected function phaseAtPositionFixture(Phase $base, PhaseKind $position): Phase
    {
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $base->cycle_id, 'kind' => $position,
            'write_register' => $base->cycle->contextHorizon->domain->registerFor($position),
            'started_at' => now(), 'completed_at' => now()])->save();

        return $phase;
    }

    /** Evidence-only setup for tests of navigation/judgment, without a vote or load. */
    /** Explicit eligibility setup; no loaded reinforcement or copied judgments. */
    protected function classificationEvidenceFixture(Proposition $node): void
    {
        $this->receiptFixture($node);
        if ($node->effectiveAttestations()->doesntExist()) {
            $attestation = new Attestation;
            $attestation->forceFill(['phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => 'support'])->save();
            app(LedgerService::class)->record($attestation);
        }
    }

    /** Select evidence explicitly; fixture authors still supply a valid Phase and source. */
    protected function reclassificationInputFixture(Phase $phase, ?Proposition $source = null): array
    {
        $source ??= $phase->gate;
        $available = app(HorizonReceiptService::class)->ordinaryAvailable($phase);
        $receipt = $available->first(fn ($item) => $item->evidenceExecution?->proposition_id === $source->id)
            ?? $available->firstOrFail();

        return ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $receipt->id];
    }

    /**
     * Observation-record fixture for catalogue, provenance and measurement tests.
     * This deliberately uses the persistence primitive, not a contribution API:
     * constructing history must not assert that acquisition is currently offered.
     * Operational/lifecycle tests must use OperationalInstrumentService instead.
     */
    protected function observationFixture(Phase $phase, Proposition $node, array $input): Receipt
    {
        $observed = app(OperationalInstrumentService::class)->observe($input);
        $reference = $observed->input['reference'] ?? $observed->input['query'];
        $metadata = $observed->metadata;
        if ($input['kind'] === 'citation') {
            $target = Proposition::find($reference) ?? Attestation::findOrFail($reference);
            $this->assertSame($phase->cycle->participant->substrate_id, $target->phase->cycle->participant->substrate_id);
            $metadata = [...$metadata, 'validation' => 'commons-reference',
                'target_type' => $target->getMorphClass(), 'target_id' => $target->id];
        }
        $executions = app(EvidenceExecutionService::class);

        return DB::transaction(function () use ($executions, $phase, $node, $observed, $reference, $metadata): Receipt {
            $execution = $executions->start($phase, $node, $observed->input['kind'], $reference, $observed->input);

            return $executions->complete($execution, $node, metadata: $metadata,
                verification: $observed->verification, observation: $observed->search, download: $observed->download);
        });
    }

    /** Attach a catalogue observation through the existing one-use consumption operation. */
    protected function consumeObservationFixture(ParticipantContextFixture $fixture, Receipt $receipt): void
    {
        $source = $fixture->node($fixture->phase->gate, 'frontier', position: PhaseKind::Persist);
        $target = $fixture->node($source, 'canon', 'reclassify', position: PhaseKind::Constrain);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($target->phase, $source, $target, $receipt->id));
    }

    protected function receiptFixture(Proposition $node): void
    {
        if (app(PropositionStateService::class)->receipts($node)->isNotEmpty()) {
            return;
        }
        $executions = app(EvidenceExecutionService::class);
        $execution = $executions->start($node->phase, $node, 'citation', $node->id, ['kind' => 'citation', 'reference' => $node->id]);
        $executions->complete($execution, $node);
    }

    protected function groundFixture(Proposition $node): void
    {
        if (app(PropositionStateService::class)->attestations(collect([$node]))->isEmpty()) {
            $attestation = new Attestation;
            $attestation->forceFill(['phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => 'support'])->save();
            app(LedgerService::class)->record($attestation);
        }
        $this->loadedReceiptFixture($node);
    }
}
