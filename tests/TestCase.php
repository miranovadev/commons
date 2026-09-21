<?php

namespace Tests;

use App\Domain\Evidence\VerifiedHyperlink;
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
use App\Models\Substrate;
use App\Services\ContextHorizonProjector;
use App\Services\Evidence\HyperlinkIdentity;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\PublicResourceAddress;
use App\Services\HorizonReceiptService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionStateService;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
            return new class($app->make(HyperlinkIdentity::class), $app->make(PublicResourceAddress::class)) extends HyperlinkVerifier
            {
                public function verify(string $reference): VerifiedHyperlink
                {
                    $resource = app(HyperlinkIdentity::class)->resource($reference);

                    return new VerifiedHyperlink($resource, hash('sha256', $resource), CarbonImmutable::now());
                }
            };
        });
    }

    /** Verified, explicitly loaded evidence for fixtures that exercise other semantic services. */
    protected function loadedReceiptFixture(Proposition $node, ?Attestation $subject = null): Receipt
    {
        $participant = $subject?->phase->cycle->participant ?? new Participant;
        if (! $participant->exists) {
            $participant->forceFill(['substrate_id' => $node->phase->cycle->participant->substrate_id,
                'callsign' => 'verifier-'.str()->ulid()])->save();
        }
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1,
            'context_horizon_id' => $node->phase->cycle->context_horizon_id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
            'gate_proposition_id' => $node->id, 'write_register' => $node->register, 'started_at' => now()])->save();
        $reference = 'https://example.test/verified/'.$node->id;
        $instruments = app(InstrumentExecutionService::class);
        $execution = $instruments->start($phase, $node, 'hyperlink', $reference);

        $receipt = $instruments->complete($execution, $subject ?? $node,
            verification: app(HyperlinkVerifier::class)->verify($reference));
        app(ReceiptContextService::class)->read($participant, $phase, $receipt->id);
        $phase->forceFill(['completed_at' => now()])->save();
        $cycle->forceFill(['completed_at' => now()])->save();

        return $receipt;
    }

    /** A structurally valid later region for tests that isolate option scope from projection labels. */
    protected function laterHorizonFixture(ContextHorizon $horizon, int $steps = 2): ContextHorizon
    {
        // By default, two causal boundaries return to the original vocabulary.
        foreach (range(1, $steps) as $step) {
            $participant = new Participant;
            $participant->forceFill(['substrate_id' => $horizon->substrate_id, 'callsign' => 'boundary-'.str()->ulid()])->save();
            $cycle = new Cycle;
            $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1,
                'context_horizon_id' => $horizon->id, 'completed_at' => now()])->save();
            $phase = new Phase;
            $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
                'write_register' => $horizon->domain->registerFor(PhaseKind::Order), 'completed_at' => now()])->save();
            $node = new Proposition;
            $node->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
                'tier' => 'committal', 'statement' => 'Boundary fixture'])->save();
            $entry = app(LedgerService::class)->record($node);
            $snapshot = new CommitmentSnapshot;
            $snapshot->forceFill(['proposition_id' => $node->id, 'substrate_id' => $horizon->substrate_id,
                'ledger_entry_id' => $entry->id, 'correspondence' => 0, 'chirality' => 0, 'closure' => 0,
                'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
                'direction_before' => $horizon->domain, 'direction_after' => $horizon->domain->opposite(),
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
        $available = app(HorizonReceiptService::class)->available($phase);
        $receipt = $available->first(fn ($item) => $item->instrumentExecution?->proposition_id === $source->id)
            ?? $available->firstOrFail();

        return ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $receipt->id];
    }

    protected function receiptFixture(Proposition $node): void
    {
        if (app(PropositionStateService::class)->receipts($node)->isNotEmpty()) {
            return;
        }
        $executions = app(InstrumentExecutionService::class);
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
