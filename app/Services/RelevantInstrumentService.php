<?php

namespace App\Services;

use App\Domain\Evidence\DownloadObservation;
use App\Domain\Evidence\PropositionCollection;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\Evidence\InstrumentInvocation;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Effective evidence, not historical existence or Participant loading, suppresses observation. */
final readonly class RelevantInstrumentService
{
    public function __construct(private PropositionStateService $state, private InstrumentInvocation $invocations) {}

    public function equivalent(
        Proposition $proposition,
        array $input,
        ?ParticipationContext $context = null,
        ?PropositionCollection $collection = null,
        ?DownloadObservation $download = null,
    ): ?Receipt {
        $normalized = $this->invocations->normalize($input);
        if ($normalized['kind'] === 'load_file') {
            // Source alone cannot identify a resource version. Recheck the observed bytes
            // under the contribution lock, using the existing effective evidence scope.
            return $download === null ? null : $this->state->receipts($proposition, $context)->first(
                fn (Receipt $receipt): bool => $receipt->type === 'load_file'
                    && ($receipt->metadata['observation_identity'] ?? null) === $download->identity(),
            );
        }
        if ($normalized['kind'] === 'proposition_search') {
            if ($collection === null) {
                return null; // Invocation alone cannot identify a changing historical observation.
            }
            $identity = $collection->identity();

            return $this->state->receipts($proposition, $context)->first(
                fn (Receipt $receipt): bool => $receipt->type === 'proposition_search'
                    && ($receipt->metadata['observation_identity'] ?? null) === $identity,
            );
        }
        $signature = $this->invocations->signature($normalized);

        return $this->state->receipts($proposition, $context)->first(function (Receipt $receipt) use ($signature): bool {
            $invocation = $this->invocations->fromReceipt($receipt);

            return $invocation !== null && $this->invocations->signature($invocation) === $signature;
        });
    }

    public function assertAvailable(
        Proposition $proposition,
        array $input,
        ?PropositionCollection $collection = null,
        ?DownloadObservation $download = null,
    ): void {
        $receipt = $this->equivalent($proposition, $input, collection: $collection, download: $download);
        if ($receipt !== null) {
            throw new ConflictHttpException("An equivalent Instrument observation is already relevant (Receipt {$receipt->id}). Use the existing Receipt or choose another resource.");
        }
    }

    /** Known exclusions for free-form controls and the model-facing schema. */
    public function exclusions(Proposition $proposition, ?ParticipationContext $context = null): Collection
    {
        return $this->state->receipts($proposition, $context)->sortBy('id')->map(function (Receipt $receipt): ?array {
            $invocation = $this->invocations->fromReceipt($receipt);

            return $invocation === null || in_array($invocation['kind'], ['proposition_search', 'load_file'], true)
                ? null : ['receipt_id' => $receipt->id, 'invocation' => $invocation];
        })->filter()->unique(fn ($item) => $this->invocations->signature($item['invocation']))->values();
    }
}
