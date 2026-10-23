<?php

namespace Tests\Fixtures;

use App\Contracts\PreparationReader;
use App\Domain\Evidence\PreparationPresentation;
use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Receipt;
use App\Services\Evidence\ArtifactStore;
use App\Services\EvidenceExecutionService;
use Carbon\CarbonImmutable;

/** Domain-only preparation fixture; reader acknowledgement is explicitly test supplied. */
final class PreparationFixture
{
    public ParticipantContextFixture $context;

    public Receipt $source;

    public function __construct()
    {
        $f = $this->context = new ParticipantContextFixture(1, 0, false);
        $f->gate = $f->node($f->seed, 'canon', position: PhaseKind::Constrain);
        $f->phase->forceFill(['kind' => PhaseKind::Disperse, 'gate_proposition_id' => $f->gate->id, 'write_register' => 'activity'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $f->gate->id])->save();
        $this->source = $this->receipt('preserved artifact body');
    }

    public function receipt(string $body): Receipt
    {
        $f = $this->context;
        $artifact = app(ArtifactStore::class)->preserve($body, 'application/octet-stream');
        $reference = 'https://example.test/artifact/'.str()->ulid();
        // Immutable observation component, separate from the operational Instrument boundary.
        $execution = app(EvidenceExecutionService::class)->start($f->gate->phase, $f->gate, 'hyperlink', $reference,
            ['kind' => 'hyperlink', 'reference' => $reference]);

        return app(EvidenceExecutionService::class)->complete($execution, $f->gate,
            verification: new VerifiedHyperlink($reference, hash('sha256', $body), CarbonImmutable::now(), artifacts: [$artifact]));
    }

    public function input(?array $receipts = null): array
    {
        return ['purpose' => '  Compare the preserved observations. 💚  ', 'artifacts' => array_map(fn ($r) => [
            'receipt_id' => $r->id, 'artifact_id' => $r->metadata['artifacts'][0]['hash'],
        ], $receipts ?? [$this->source])];
    }

    public function presentation(?array $input = null): PreparationPresentation
    {
        return new PreparationPresentation(collect(($input ?? $this->input())['artifacts'])->unique('artifact_id')->values()->map(fn ($s) => [
            'artifact_id' => $s['artifact_id'], 'method' => 'test-acknowledgement', 'version' => '1',
            'representation' => 'test-extracted-text', 'payload_hash' => 'sha256:'.hash('sha256', 'delivered text'),
            'payload_bytes' => 14, 'delivery_id' => (string) str()->ulid(),
        ])->all());
    }

    public static function registerReader(): void
    {
        app()->instance('test.preparation.reader', new class implements PreparationReader
        {
            public function method(): string
            {
                return 'test-acknowledgement';
            }

            public function version(): string
            {
                return '1';
            }

            public function supports(array $artifact, string $representation): bool
            {
                // Capability recognition, not trusting the uninformative MIME header.
                return $representation === 'test-extracted-text' && str_starts_with($artifact['hash'], 'sha256:');
            }
        });
        app()->tag('test.preparation.reader', 'preparation.readers');
    }
}
