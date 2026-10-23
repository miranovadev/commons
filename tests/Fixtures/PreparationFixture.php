<?php

namespace Tests\Fixtures;

use App\Domain\Evidence\PreparationInput;
use App\Domain\Evidence\PreparationPresentation;
use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Participant;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\Evidence\ArtifactReaderRegistry;
use App\Services\Evidence\ArtifactStore;
use App\Services\Evidence\PreparationAllocation;
use App\Services\EvidenceExecutionService;
use App\Services\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Domain-only preparation fixture; reader acknowledgement is explicitly test supplied. */
final class PreparationFixture
{
    public ParticipantContextFixture $context;

    public Receipt $source;

    public function __construct(bool $available = false)
    {
        DB::transaction(function () use ($available): void {
            $f = $this->context = new ParticipantContextFixture(1, 0, false);
            $f->gate = $f->node($f->seed, 'canon', position: PhaseKind::Constrain);
            $f->phase->forceFill(['kind' => PhaseKind::Disperse, 'gate_proposition_id' => $f->gate->id, 'write_register' => 'activity'])->save();
            $f->viewer->forceFill(['current_proposition_id' => $f->gate->id])->save();
            $this->source = $this->receipt('preserved artifact body', $available);
        });
    }

    public function receipt(string $body, bool $available = false): Receipt
    {
        $f = $this->context;
        $artifact = app(ArtifactStore::class)->preserve($body, 'application/octet-stream');
        $reference = 'https://example.test/artifact/'.str()->ulid();
        // Immutable observation component, separate from the operational Instrument boundary.
        $execution = app(EvidenceExecutionService::class)->start($f->gate->phase, $f->gate, 'hyperlink', $reference,
            ['kind' => 'hyperlink', 'reference' => $reference]);

        $receipt = app(EvidenceExecutionService::class)->complete($execution, $f->gate,
            verification: new VerifiedHyperlink($reference, hash('sha256', $body), CarbonImmutable::now(), artifacts: [$artifact]));
        if (! $available) {
            $this->consume($receipt);
        }

        return $receipt;
    }

    public function input(?array $receipts = null): array
    {
        return ['purpose' => '  Compare the preserved observations. 💚  ', 'artifacts' => array_map(fn ($r) => [
            'receipt_id' => $r->id, 'artifact_id' => $r->metadata['artifacts'][0]['hash'], 'read_type' => 'raw',
        ], $receipts ?? [$this->source])];
    }

    public function presentation(?array $input = null): PreparationPresentation
    {
        $selections = ($input ?? $this->input())['artifacts'];

        $representations = collect($selections)
            ->unique(fn ($s) => PreparationInput::requestId($s))->values()->map(function ($selection) use ($selections) {
                $receipt = Receipt::findOrFail($selection['receipt_id']);
                $artifact = collect($receipt->metadata['artifacts'])->firstWhere('hash', $selection['artifact_id']);
                $body = app(ArtifactStore::class)->read($artifact);
                $read = app(ArtifactReaderRegistry::class)->represent($body, $artifact['media_type'], $selection['read_type'], $selection['parameters'] ?? [], 'inline-text');

                return ['receipt_ids' => collect($selections)->filter(fn ($item) => PreparationInput::requestId($item) === PreparationInput::requestId($selection))->pluck('receipt_id')->values()->all(),
                    'artifact_id' => $selection['artifact_id'], 'request_id' => PreparationInput::requestId($selection),
                    'delivery_id' => 'test:'.PreparationInput::requestId($selection), ...$read];
            })->all();

        return new PreparationPresentation(array_map(fn ($item) => array_diff_key($item, ['content' => true]),
            app(PreparationAllocation::class)->allocate($representations)));
    }

    public static function registerReader(): void
    {
        // Native registry is authoritative in Stage 2D; no test capability registration.
    }

    /** Consume through the real ordinary Reclassification path before offering Prepare. */
    public function consume(Receipt $receipt): Proposition
    {
        return DB::transaction(function () use ($receipt) {
            $f = $this->context;
            $participant = (new Participant)->forceFill(['substrate_id' => $f->substrate->id,
                'callsign' => 'consumer-'.str()->ulid(), 'current_proposition_id' => $f->gate->id]);
            $participant->save();
            $cycle = $f->phase->cycle->replicate();
            $cycle->participant_id = $participant->id;
            $cycle->save();
            $phase = $f->phase->replicate();
            $phase->cycle_id = $cycle->id;
            $source = $f->node($f->seed, 'frontier', position: PhaseKind::Persist, participant: $participant);
            $vote = (new Attestation)->forceFill(['phase_id' => $source->phase_id, 'proposition_id' => $source->id, 'direction' => 'support']);
            $vote->save();
            app(LedgerService::class)->record($vote);
            $phase->forceFill(['kind' => PhaseKind::Constrain, 'gate_proposition_id' => $source->id, 'write_register' => 'canon'])->save();
            $participant->forceFill(['current_proposition_id' => $source->id])->save();

            return app(ContributionDispatcher::class)->dispatch($participant, $phase->fresh(),
                ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $receipt->id]);
        });
    }
}
