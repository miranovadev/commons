<?php

namespace App\Services;

use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Synchronous reference instruments; no network fetch or content verification. */
final readonly class OperationalInstrumentService
{
    public function __construct(private InstrumentExecutionService $executions) {}

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
                'hyperlink' => $this->hyperlink($reference),
                'citation' => $this->citation($reference, $substrateId),
                default => throw new DomainException('This instrument is not available yet.'),
            };
            $execution = $this->executions->start(
                $phase, $proposition, $input['kind'], $reference, $input,
            );

            return $this->executions->complete(
                $execution, $proposition, metadata: $metadata,
            );
        });
    }

    /** @return array{validation: string, content_verified: bool} */
    private function hyperlink(string $reference): array
    {
        $parts = parse_url($reference);
        if (filter_var($reference, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])) {
            throw new DomainException('A Hyperlink requires an HTTP(S) URL without credentials.');
        }

        return ['validation' => 'url-reference-only', 'content_verified' => false];
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
