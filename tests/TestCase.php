<?php

namespace Tests;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContextHorizonProjector;
use App\Services\Evidence\HyperlinkIdentity;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\PublicResourceAddress;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\PropositionStateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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

    /** Qualifying later verification for fixtures that exercise other semantic services. */
    protected function verifiedReceiptFixture(Proposition $node, ?Attestation $subject = null): Receipt
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $node->phase->cycle->participant->substrate_id,
            'callsign' => 'verifier-'.str()->ulid()])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1, 'completed_at' => now()])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
            'write_register' => $node->register, 'started_at' => now(), 'completed_at' => now()])->save();
        $reference = 'https://example.test/verified/'.$node->id;
        $instruments = app(InstrumentExecutionService::class);
        $execution = $instruments->start($phase, $node, 'hyperlink', $reference);

        return $instruments->complete($execution, $subject ?? $node,
            verification: app(HyperlinkVerifier::class)->verify($reference));
    }

    /** A structurally valid later region for tests that isolate option scope from projection labels. */
    protected function laterHorizonFixture(ContextHorizon $horizon): ContextHorizon
    {
        // Two causal boundaries return to the original register vocabulary.
        foreach (range(1, 2) as $step) {
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

    protected function groundFixture(Proposition $node): void
    {
        if (app(PropositionStateService::class)->attestations(collect([$node]))->isEmpty()) {
            $attestation = new Attestation;
            $attestation->forceFill(['phase_id' => $node->phase_id, 'proposition_id' => $node->id, 'direction' => 'support'])->save();
            app(LedgerService::class)->record($attestation);
        }
        $this->verifiedReceiptFixture($node);
    }
}
