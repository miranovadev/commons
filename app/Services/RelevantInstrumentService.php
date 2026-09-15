<?php

namespace App\Services;

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

    public function equivalent(Proposition $proposition, array $input, ?ParticipationContext $context = null): ?Receipt
    {
        $signature = $this->invocations->signature($input);

        return $this->state->receipts($proposition, $context)->first(function (Receipt $receipt) use ($signature): bool {
            $invocation = $this->invocations->fromReceipt($receipt);

            return $invocation !== null && $this->invocations->signature($invocation) === $signature;
        });
    }

    public function assertAvailable(Proposition $proposition, array $input): void
    {
        $receipt = $this->equivalent($proposition, $input);
        if ($receipt !== null) {
            throw new ConflictHttpException("An equivalent Instrument observation is already relevant (Receipt {$receipt->id}). Use the existing Receipt or choose another resource.");
        }
    }

    /** Known exclusions for free-form controls and the model-facing schema. */
    public function exclusions(Proposition $proposition, ?ParticipationContext $context = null): Collection
    {
        return $this->state->receipts($proposition, $context)->sortBy('id')->map(function (Receipt $receipt): ?array {
            $invocation = $this->invocations->fromReceipt($receipt);

            return $invocation === null ? null : ['receipt_id' => $receipt->id, 'invocation' => $invocation];
        })->filter()->unique(fn ($item) => $this->invocations->signature($item['invocation']))->values();
    }
}
