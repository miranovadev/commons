<?php

namespace App\Services;

use App\Contracts\WebSearchProvider;
use App\Domain\Evidence\SearchObservation;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\Evidence\HyperlinkIdentity;
use App\Services\Evidence\HyperlinkVerifier;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Synchronous verified evidence; only the verifier can supply reinforcement identity. */
final readonly class OperationalInstrumentService
{
    public function __construct(private InstrumentExecutionService $executions, private HyperlinkVerifier $verifier, private HyperlinkIdentity $identity, private WebSearchProvider $search, private PropositionAvailabilityService $availability) {}

    /** @param array{kind: string, reference?: string, query?: string} $input */
    public function execute(Phase $phase, Proposition $proposition, array $input, ?Receipt $replayedFrom = null, bool $bootstrap = false): Receipt
    {
        return DB::transaction(function () use ($phase, $proposition, $input, $replayedFrom, $bootstrap): Receipt {
            $kind = $input['kind'] ?? null;
            $keys = $kind === 'web_search' ? ['kind', 'query'] : ['kind', 'reference'];
            if (array_diff(array_keys($input), $keys) !== [] || array_diff($keys, array_keys($input)) !== []) {
                throw new DomainException('The Instrument input must match its kind.');
            }
            $reference = $kind === 'web_search' ? SearchObservation::normalizeQuery($input['query']) : trim($input['reference']);
            $input = $kind === 'web_search' ? ['kind' => $kind, 'query' => $reference] : ['kind' => $kind, 'reference' => $reference];
            $substrateId = $phase->cycle->participant->substrate_id;
            if ($proposition->phase->cycle->participant->substrate_id !== $substrateId) {
                throw new DomainException('An instrument cannot cross substrate boundaries.');
            }
            Substrate::query()->lockForUpdate()->findOrFail($substrateId);
            $origin = $bootstrap && $proposition->tier === 'committal'
                && $proposition->ledgerEntry?->sequence === 1 && ! $proposition->parents()->exists()
                && ! CommitmentSnapshot::where('substrate_id', $substrateId)->exists();
            if (! $origin && ! $this->availability->mutable($proposition)) {
                throw new DomainException('Committed sources and Committal results cannot receive instruments. Develop a Variant from the Committal instead.');
            }

            $metadata = match ($input['kind']) {
                'hyperlink' => ['resource' => $this->identity->resource($reference)],
                'citation' => $this->citation($reference, $substrateId),
                'web_search' => [],
                default => throw new DomainException('This instrument is not available yet.'),
            };
            $execution = $this->executions->start(
                $phase, $proposition, $input['kind'], $reference, $input, $replayedFrom,
            );

            $observation = null;
            if ($kind === 'web_search') {
                try {
                    $observation = $this->search->search($reference);
                } catch (\Throwable) {
                    $metadata = ['validation' => 'search-failed', 'content_verified' => false,
                        'observation_error' => 'Web Search is unavailable or unconfigured.'];
                }
            }
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
                $execution, $replayedFrom?->receiptable ?? $proposition, metadata: $metadata, verification: $verification, observation: $observation,
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
