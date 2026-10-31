<?php

namespace App\Services;

use App\Contracts\WebSearchProvider;
use App\Domain\Evidence\InstrumentObservation;
use App\Domain\Evidence\PropositionCollection;
use App\Domain\Evidence\SearchObservation;
use App\Enums\PhaseKind;
use App\Exceptions\ArtifactStorageException;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\EvidenceExecution;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use App\Services\Evidence\DownloadAcquisition;
use App\Services\Evidence\HyperlinkIdentity;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\InstrumentInvocation;
use DomainException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Synchronous Instrument execution; evidence verification and cognitive memory remain distinct. */
final readonly class OperationalInstrumentService
{
    public function __construct(
        private EvidenceExecutionService $executions,
        private HyperlinkVerifier $verifier,
        private HyperlinkIdentity $identity,
        private WebSearchProvider $search,
        private PropositionAvailabilityService $availability,
        private PropositionSearchService $propositionSearch,
    ) {}

    /** @param array{kind: string, reference?: string, query?: string, limit?: int} $input */
    public function execute(Phase $phase, Proposition $proposition, array $input, ?Receipt $replayedFrom = null, bool $bootstrap = false, ?InstrumentObservation $observed = null): Receipt
    {
        $observed ??= $this->observeFor($phase, $proposition, $input, $bootstrap, $replayedFrom);
        if ($observed->input !== $this->normalize($input)) {
            throw new DomainException('The observed Instrument must match the contribution invocation.');
        }

        return DB::transaction(function () use ($phase, $proposition, $input, $replayedFrom, $bootstrap, $observed): Receipt {
            $input = $observed->input;
            $kind = $input['kind'];
            $reference = $this->reference($input);
            $substrateId = $phase->cycle->participant->substrate_id;
            if ($proposition->phase->cycle->participant->substrate_id !== $substrateId) {
                throw new DomainException('An instrument cannot cross substrate boundaries.');
            }
            Substrate::query()->lockForUpdate()->findOrFail($substrateId);
            if ($kind === 'proposition_search'
                && ($observed->metadata['observed_ledger_sequence'] ?? null) !== $this->ledgerSequence($substrateId)) {
                throw new ConflictHttpException('The Proposition Search history changed during observation. Retry from fresh state.');
            }
            $origin = $bootstrap && $proposition->tier === 'committal'
                && $proposition->ledgerEntry?->sequence === 1 && ! $proposition->parents()->exists()
                && ! CommitmentSnapshot::where('substrate_id', $substrateId)->exists();
            if (! $origin) {
                $phase = $phase->fresh(['cycle.participant']);
                try {
                    $current = app(PhaseService::class)->currentFor($phase->cycle->participant);
                } catch (DomainException $exception) {
                    throw new ConflictHttpException('The acquisition Phase is no longer active.', $exception);
                }
                if ($current->id !== $phase->id) {
                    throw new ConflictHttpException('The acquisition Phase is no longer active.');
                }
            }
            if (! $origin && $phase->kind !== PhaseKind::Distinguish && ! $this->availability->mutable($proposition)) {
                throw new DomainException('Committed sources and Committal results cannot receive instruments. Develop a Variant from the Committal instead.');
            }
            // Trusted pre-observed service calls still require admission under the root lock.
            if (! $origin && app(AcquisitionAttemptService::class)->execution($phase) === null) {
                $this->reserve($phase, $proposition, $input, $replayedFrom);
            }
            if (! $origin && ! app(AcquisitionAttemptService::class)->admitted($phase)) {
                throw new ConflictHttpException('Instrumentation requires the exact active Gate and an admitted current-Phase acquisition attempt.');
            }

            $metadata = match ($input['kind']) {
                'hyperlink' => ['resource' => $this->identity->resource($reference)],
                'citation' => $this->citation($reference, $substrateId),
                'web_search', 'proposition_search', 'load_file' => [],
                default => throw new DomainException('This instrument is not available yet.'),
            };
            // The world may have changed during observation. Recheck before persisting anything.
            app(RelevantInstrumentService::class)->assertAvailable($proposition, $input, $observed->propositions, $observed->download);

            $execution = $origin ? $this->executions->start($phase, $proposition, $input['kind'], $reference, $input, $replayedFrom)
                : app(AcquisitionAttemptService::class)->execution($phase);
            if ($execution->status !== 'pending' || $this->normalize($execution->input) !== $input) {
                throw new ConflictHttpException('This acquisition is already observed or has a different invocation.');
            }
            if (! $origin && ! app(PhaseGenerationService::class)->available($phase, 'proposition.variant', prepared: true)) {
                throw new ConflictHttpException('The admitted acquisition Gate is no longer safe.');
            }

            return $this->executions->complete(
                $execution, $proposition, metadata: [...$metadata, ...$observed->metadata],
                verification: $observed->verification, observation: $observed->search,
                propositionCollection: $observed->propositions, download: $observed->download,
            );
        });
    }

    public function observeFor(Phase $phase, Proposition $proposition, array $input, bool $bootstrap = false, ?Receipt $replayedFrom = null): InstrumentObservation
    {
        if ($phase->cycle->participant->substrate_id !== $proposition->phase->cycle->participant->substrate_id) {
            throw new DomainException('An instrument cannot cross substrate boundaries.');
        }
        if (! $bootstrap && $phase->kind !== PhaseKind::Distinguish && ! $this->availability->mutable($proposition)) {
            throw new DomainException('Committed sources and Committal results cannot receive instruments. Develop a Variant from the Committal instead.');
        }
        if (! $bootstrap && ! app(PropositionOptionService::class)->instrumentOptionsFor($phase->fresh())->contains('id', $proposition->id)) {
            throw new ConflictHttpException('Instrumentation requires the exact active Gate and an admitted current-Phase acquisition attempt.');
        }
        app(RelevantInstrumentService::class)->assertAvailable($proposition, $input);
        $normalized = $this->normalize($input);
        if (! $bootstrap) {
            $this->reserve($phase, $proposition, $normalized, $replayedFrom);
        }
        if ($normalized['kind'] === 'proposition_search') {
            $substrateId = $phase->cycle->participant->substrate_id;
            $before = $this->ledgerSequence($substrateId);
            $collection = $this->propositionSearch->search($phase, $proposition, $normalized);
            $after = $this->ledgerSequence($substrateId);
            if ($before !== $after) {
                throw new ConflictHttpException('The Proposition Search history changed during observation. Retry from fresh state.');
            }
            app(RelevantInstrumentService::class)->assertAvailable($proposition, $normalized, $collection);

            return new InstrumentObservation($normalized, ['observed_ledger_sequence' => $after], propositions: $collection);
        }

        return $this->observe($normalized);
    }

    private function reserve(Phase $phase, Proposition $proposition, array $normalized, ?Receipt $replayedFrom): void
    {
        DB::transaction(function () use ($phase, $proposition, $normalized, $replayedFrom): void {
            Substrate::lockForUpdate()->findOrFail($phase->cycle->participant->substrate_id);
            $phase = $phase->fresh(['cycle.participant']);
            if (app(PhaseService::class)->currentFor($phase->cycle->participant)->id !== $phase->id) {
                throw new ConflictHttpException('The acquisition Phase is no longer active.');
            }
            if (! app(AcquisitionAttemptService::class)->allows($phase, 'instrument.execute')) {
                throw new ConflictHttpException('This Phase already has an acquisition attempt or exploration is outstanding.');
            }
            $execution = new EvidenceExecution;
            $execution->forceFill(['phase_id' => $phase->id, 'proposition_id' => $proposition->id,
                'kind' => $normalized['kind'], 'reference' => $this->reference($normalized),
                'replayed_from_receipt_id' => $replayedFrom?->id, 'input' => $normalized, 'started_at' => now(), 'attempt_admitted_at' => now()])->save();
        });
    }

    /** External I/O only: callers composing transactions must prepare this first. */
    public function observe(array $input): InstrumentObservation
    {
        $input = $this->normalize($input);
        $kind = $input['kind'];
        if ($kind === 'proposition_search') {
            throw new DomainException('Proposition Search requires a Phase and Proposition execution context.');
        }
        $reference = $this->reference($input);
        if ($kind === 'load_file') {
            return new InstrumentObservation($input, download: app(DownloadAcquisition::class)->acquire($reference));
        }
        $metadata = [];
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
            } catch (ArtifactStorageException $exception) {
                // Infrastructure failure is not a failed observation of the resource.
                // Admission is retained without a Receipt; expiry permits explicit Defer. No write lock is held.
                throw $exception;
            } catch (\Throwable $exception) {
                // A failed verification is still an observable Instrument attempt.
                // Complete a non-reinforcing Receipt, without claiming verified content.
                $metadata = [...$metadata, 'validation' => 'verification-failed', 'content_verified' => false,
                    'verification_error' => $exception instanceof DomainException ? $exception->getMessage() : 'Resource verification failed.'];
            }
        }

        return new InstrumentObservation($input, $metadata, $verification, $observation);
    }

    private function normalize(array $input): array
    {
        $kind = $input['kind'] ?? null;
        if ($kind === 'proposition_search') {
            return PropositionCollection::normalizeInput($input);
        }
        if ($kind === 'load_file') {
            return app(InstrumentInvocation::class)->normalize($input);
        }
        $keys = $kind === 'web_search' ? ['kind', 'query'] : ['kind', 'reference'];
        if (array_diff(array_keys($input), $keys) !== [] || array_diff($keys, array_keys($input)) !== []) {
            throw new DomainException('The Instrument input must match its kind.');
        }
        if (! in_array($kind, ['web_search', 'hyperlink', 'citation'], true)) {
            throw new DomainException('This instrument is not available yet.');
        }

        return $kind === 'web_search'
            ? ['kind' => $kind, 'query' => SearchObservation::normalizeQuery($input['query'])]
            : ['kind' => $kind, 'reference' => trim($input['reference'])];
    }

    private function ledgerSequence(string $substrateId): int
    {
        return (int) SubstrateLedgerEntry::where('substrate_id', $substrateId)->max('sequence');
    }

    private function reference(array $input): string
    {
        return match ($input['kind']) {
            'web_search' => $input['query'],
            'proposition_search' => $input['query'] ?? 'nearest-history',
            default => $input['reference'],
        };
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
