<?php

namespace App\Services;

use App\Enums\LedgerEntryKind;
use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\ReceiptContextEvent;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use App\Models\Supersession;
use App\Services\Observables\AttestationBeatCursorService;
use App\Services\Observables\LocalChiralityStateService;
use App\Services\Observables\PhaseProfileObservationService;
use App\Services\Observables\ProspectiveClosureService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class LedgerService
{
    public function __construct(
        private ContextHorizonProjector $horizons,
    ) {}

    public function record(Model $ledgerable, ?Proposition $phaseProfileSource = null): SubstrateLedgerEntry
    {
        if (! $ledgerable->exists) {
            throw new DomainException(
                'Only persisted contributions can enter the substrate ledger.'
            );
        }

        [$kind, $substrate] = $this->describe($ledgerable);

        return DB::transaction(function () use (
            $ledgerable,
            $kind,
            $substrate,
            $phaseProfileSource,
        ): SubstrateLedgerEntry {
            $locked = Substrate::query()
                ->lockForUpdate()
                ->findOrFail($substrate->getKey());

            $entry = SubstrateLedgerEntry::query()
                ->where('ledgerable_type', $ledgerable->getMorphClass())
                ->where('ledgerable_id', $ledgerable->getKey())
                ->first();
            $wasRecorded = $entry !== null;

            if ($entry === null) {
                $sequence = (int) SubstrateLedgerEntry::query()
                    ->whereBelongsTo($locked)
                    ->max('sequence') + 1;

                $entry = new SubstrateLedgerEntry;
                $entry->forceFill([
                    'substrate_id' => $locked->getKey(),
                    'sequence' => $sequence,
                    'kind' => $kind,
                    'recorded_at' => now(),
                ]);
                $entry->ledgerable()->associate($ledgerable);
                $entry->save();
            }

            if ($entry->substrate_id !== $locked->getKey()) {
                throw new DomainException(
                    'A contribution cannot be recorded in multiple substrates.'
                );
            }

            if (
                ! $wasRecorded
                && $kind === LedgerEntryKind::Proposition
            ) {
                $this->horizons->include($entry);
                app(PhaseProfileObservationService::class)->recordBirth($ledgerable, $entry, $phaseProfileSource);
                app(LocalChiralityStateService::class)->initialize($ledgerable);
                if ($ledgerable->tier === 'committal' && $ledgerable->phase->cycle->entry_committal_id === null) {
                    app(ProspectiveClosureService::class)->initialize($ledgerable);
                }
            }

            if (! $wasRecorded) {
                if ($ledgerable instanceof Attestation) {
                    app(LocalChiralityStateService::class)->attest($ledgerable, $entry->sequence);
                    app(AttestationBeatCursorService::class)->attest($ledgerable, $entry->sequence);
                }
                $affected = match (true) {
                    $ledgerable instanceof Proposition => [$ledgerable->id],
                    $ledgerable instanceof Supersession => [$ledgerable->source_proposition_id, $ledgerable->target_proposition_id],
                    $ledgerable instanceof Attestation => [$ledgerable->proposition_id],
                    $ledgerable instanceof ReceiptContextEvent => [$ledgerable->context->proposition_id],
                    $ledgerable instanceof Receipt => [$ledgerable->evidenceExecution?->proposition_id ?? $ledgerable->phase->gate_proposition_id],
                    default => [],
                };
                $phase = $ledgerable->phase;
                // Cognitive memory is auditable history, not field/evidence mutation.
                $memoryOnly = ($ledgerable instanceof ReceiptContextEvent && $ledgerable->context->receipt->isSpecial());
                if (! $memoryOnly) {
                    app(FieldMutationService::class)->touch($phase, $affected);
                }
                if ($ledgerable instanceof Supersession) {
                    app(AttestationBeatCursorService::class)->inherit($ledgerable);
                    app(OriginBranchService::class)->include($ledgerable);
                }
                $realtime = app(RealtimePublisher::class);
                if ($ledgerable instanceof Proposition) {
                    $realtime->proposition($ledgerable->getKey(), 'proposition.created');
                } elseif ($ledgerable instanceof Attestation) {
                    $realtime->proposition($ledgerable->proposition_id, 'attestation.created');
                } elseif ($ledgerable instanceof Supersession) {
                    $realtime->proposition($ledgerable->source_proposition_id);
                    $realtime->proposition($ledgerable->target_proposition_id);
                } elseif ($ledgerable instanceof Receipt) {
                    $realtime->horizon($phase->cycle->context_horizon_id, $substrate->id,
                        ['type' => 'receipt.created', 'receipt_id' => $ledgerable->id]);
                }
            }

            return $entry;
        });
    }

    /**
     * @return array{
     *     meta: array{limit: int, before: ?int, count: int},
     *     items: list<array<string, mixed>>
     * }
     */
    public function fetch(
        Substrate $substrate,
        int $limit = 25,
        ?int $before = null,
    ): array {
        $entries = SubstrateLedgerEntry::query()
            ->whereBelongsTo($substrate)
            ->when(
                $before !== null,
                fn ($query) => $query->where('sequence', '<', $before),
            )
            ->latest('sequence')
            ->limit($limit)
            ->get();

        return [
            'meta' => [
                'limit' => $limit,
                'before' => $before,
                'count' => $entries->count(),
            ],
            'items' => $entries->map(fn (SubstrateLedgerEntry $entry) => [
                'id' => $entry->getKey(),
                'sequence' => $entry->sequence,
                'kind' => $entry->kind->value,
                'subject_id' => $entry->ledgerable_id,
                'recorded_at' => $entry->recorded_at,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{LedgerEntryKind, Substrate}
     */
    private function describe(Model $ledgerable): array
    {
        if ($ledgerable instanceof Proposition) {
            return [
                LedgerEntryKind::Proposition,
                $this->substrateForPhase($ledgerable->phase),
            ];
        }

        if ($ledgerable instanceof Supersession) {
            $substrate = $this->substrateForPhase($ledgerable->phase);
            $this->assertSameSubstrate(
                $substrate,
                $this->substrateForPhase($ledgerable->source->phase),
                $this->substrateForPhase($ledgerable->target->phase),
            );

            return [LedgerEntryKind::Supersession, $substrate];
        }

        if ($ledgerable instanceof Attestation) {
            $substrate = $this->substrateForPhase($ledgerable->phase);
            $this->assertSameSubstrate(
                $substrate,
                $this->substrateForPhase($ledgerable->proposition->phase),
            );

            return [LedgerEntryKind::Attestation, $substrate];
        }

        if ($ledgerable instanceof ReceiptContextEvent) {
            $substrate = $this->substrateForPhase($ledgerable->phase);
            $this->assertSameSubstrate($substrate, $this->substrateForPhase($ledgerable->context->receipt->phase));

            return [LedgerEntryKind::ReceiptContext, $substrate];
        }

        if ($ledgerable instanceof Receipt) {
            $execution = $ledgerable->evidenceExecution;
            $substrate = $this->substrateForPhase($ledgerable->phase);
            $receiptable = $ledgerable->receiptable;
            $receiptableSubstrate = match (true) {
                $receiptable instanceof Proposition => $this->substrateForPhase(
                    $receiptable->phase,
                ),
                $receiptable instanceof Attestation => $this->substrateForPhase(
                    $receiptable->phase,
                ),
                default => throw new DomainException(
                    'A Receipt must reference a Proposition or Attestation.'
                ),
            };
            $this->assertSameSubstrate(
                $substrate,
                $this->substrateForPhase($execution?->proposition->phase ?? $ledgerable->phase),
                $receiptableSubstrate,
            );

            return [LedgerEntryKind::Receipt, $substrate];
        }

        throw new DomainException(
            sprintf(
                '%s is not a ledgerable Commons contribution.',
                $ledgerable::class,
            ),
        );
    }

    private function substrateForPhase(Phase $phase): Substrate
    {
        return $phase->cycle->participant->substrate;
    }

    private function assertSameSubstrate(
        Substrate $expected,
        Substrate ...$related,
    ): void {
        foreach ($related as $substrate) {
            if ($substrate->getKey() !== $expected->getKey()) {
                throw new DomainException(
                    'A contribution cannot cross substrate boundaries.'
                );
            }
        }
    }
}
