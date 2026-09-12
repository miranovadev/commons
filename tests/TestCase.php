<?php

namespace Tests;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
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
