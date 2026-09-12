<?php

namespace App\Services;

use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\Evidence\HyperlinkIdentity;
use App\Services\Evidence\HyperlinkVerifier;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Synchronous verified evidence; only the verifier can supply reinforcement identity. */
final readonly class OperationalInstrumentService
{
    public function __construct(private InstrumentExecutionService $executions, private HyperlinkVerifier $verifier, private HyperlinkIdentity $identity) {}

    /** @param array{kind: string, reference: string} $input */
    public function execute(Phase $phase, Proposition $proposition, array $input): Receipt
    {
        return DB::transaction(function () use ($phase, $proposition, $input): Receipt {
            $reference = trim($input['reference']);
            $substrateId = $phase->cycle->participant->substrate_id;
            if ($proposition->phase->cycle->participant->substrate_id !== $substrateId) {
                throw new DomainException('An instrument cannot cross substrate boundaries.');
            }

            $metadata = match ($input['kind']) {
                'hyperlink' => ['resource' => $this->identity->resource($reference)],
                'citation' => $this->citation($reference, $substrateId),
                default => throw new DomainException('This instrument is not available yet.'),
            };
            $execution = $this->executions->start(
                $phase, $proposition, $input['kind'], $reference, $input,
            );

            $verification = null;
            if ($input['kind'] === 'hyperlink') {
                try {
                    $verification = $this->verifier->verify($reference);
                } catch (\Throwable $exception) {
                    // A failed verification is still an observable Instrument attempt.
                    // Complete a non-reinforcing Receipt, without claiming verified content.
                    $metadata = [...$metadata, 'validation' => 'verification-failed', 'content_verified' => false,
                        'verification_error' => $exception instanceof DomainException ? $exception->getMessage() : 'Resource verification failed.'];
                }
            }

            return $this->executions->complete(
                $execution, $proposition, metadata: $metadata, verification: $verification,
            );
        });
    }

    /** @return array{validation: string, target_type: string, target_id: string} */
    private function citation(string $reference, string $substrateId): array
    {
        // Bare ULIDs identify Commons Propositions or Attestations, never URLs.
        $target = Proposition::query()->find($reference)
            ?? Attestation::query()->find($reference);
        if ($target === null || $target->phase->cycle->participant->substrate_id !== $substrateId) {
            throw new DomainException('A Citation requires an existing Proposition or Attestation ID in this substrate.');
        }

        return [
            'validation' => 'commons-reference',
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
        ];
    }
}
