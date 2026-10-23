<?php

namespace App\Services;

use App\Contracts\PreparationReader;
use App\Domain\Evidence\PreparationInput;
use App\Domain\Evidence\PreparationPresentation;
use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Receipt;
use App\Models\Substrate;
use DomainException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Dormant domain boundary: Stage 2D owns reader adapters and participant interaction. */
final readonly class PreparationService
{
    public function __construct(private EvidenceExecutionService $executions) {}

    public function start(Participant $participant, Phase $phase, array $input): EvidenceExecution
    {
        $input = PreparationInput::from($input);

        return DB::transaction(function () use ($participant, $phase, $input): EvidenceExecution {
            $phase = $this->lockPhase($participant, $phase->id, $phase->gate_proposition_id);
            if (EvidenceExecution::where('cycle_id', $phase->cycle_id)->where('type', 'prepare')->exists()) {
                throw new ConflictHttpException('The Preparation allowance for this Cycle is already reserved.');
            }
            $catalogue = app(HorizonReceiptService::class)->catalogue($phase)->keyBy('id');
            foreach ($input->artifacts as $selection) {
                $receipt = $catalogue->get($selection['receipt_id']);
                if ($receipt === null || $this->artifact($receipt, $selection['artifact_id']) === null) {
                    throw new DomainException('Preparation requires each selected Artifact in its claimed catalogued Receipt.');
                }
            }

            return $this->executions->startPreparation($phase, $input);
        });
    }

    /** Trusted adapter acknowledgement only; no public action/route accepts this payload. */
    public function present(Participant $participant, EvidenceExecution $execution, PreparationPresentation $presentation): void
    {
        DB::transaction(function () use ($participant, $execution, $presentation): void {
            [, $execution] = $this->lockExecution($participant, $execution);
            if ($execution->status !== 'pending') {
                throw new ConflictHttpException('Only a pending Preparation can accept its presentation.');
            }
            // Selection authority was acquired at start. Consumption since then does
            // not erase that immutable observation provenance or create new access.
            $selected = collect($execution->input['artifacts']);
            $expected = $selected->pluck('artifact_id')->unique()->sort()->values()->all();
            $actual = collect($presentation->deliveries)->pluck('artifact_id')->sort()->values()->all();
            if ($expected !== $actual) {
                throw new DomainException('Every selected unique Artifact must be delivered exactly once, without omissions.');
            }
            $receipts = Receipt::whereKey($selected->pluck('receipt_id'))->get()->keyBy('id');
            foreach ($presentation->deliveries as $delivery) {
                $reader = collect(app()->tagged('preparation.readers'))->first(fn ($reader) => $reader instanceof PreparationReader
                    && $reader->method() === $delivery['method'] && $reader->version() === $delivery['version']);
                foreach ($selected->where('artifact_id', $delivery['artifact_id']) as $selection) {
                    $artifact = $this->artifact($receipts[$selection['receipt_id']], $selection['artifact_id']);
                    if ($reader === null || $artifact === null || ! $reader->supports($artifact, $delivery['representation'])) {
                        throw new DomainException('Presentation requires an explicitly supported reader, version and representation.');
                    }
                }
            }
            $this->executions->present($execution, $presentation);
        });
    }

    public function contribute(Participant $participant, EvidenceExecution $execution, array $input): Receipt
    {
        if (! in_array($input['action'] ?? null, ['proposition.variant', 'proposition.revise'], true)) {
            throw new DomainException('Preparation can contribute only a lawful Disperse Variant or Revise.');
        }

        return DB::transaction(function () use ($participant, $execution, $input): Receipt {
            [$phase, $execution] = $this->lockExecution($participant, $execution);
            $this->assertPresented($execution);
            // Reuse the ordinary schema, structural authority, birth and epoch paths.
            $schema = app(InputPlanService::class)->forPhase($participant, $phase)['schema'];
            app(ContributionService::class)->validateSchema($schema, $input);
            $result = app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
            $receipt = $this->executions->completePreparation($execution, $result, null);
            app(PhaseProgressionService::class)->advance($phase, $result);

            return $receipt;
        });
    }

    public function defer(Participant $participant, EvidenceExecution $execution, string $reason): Receipt
    {
        if (trim($reason) === '' || mb_strlen($reason) > 400 || str_contains($reason, "\0")) {
            throw new DomainException('Deferment requires a reason of 1–400 characters.');
        }

        return DB::transaction(function () use ($participant, $execution, $reason): Receipt {
            [, $execution] = $this->lockExecution($participant, $execution);
            $this->assertPresented($execution);

            return $this->executions->completePreparation($execution, null, $reason);
        });
    }

    /** Infrastructure failure is not a successful observation and creates no Receipt. */
    public function fail(Participant $participant, EvidenceExecution $execution, string $method, string $version, string $stage, string $reason): void
    {
        if (! in_array($stage, ['unsupported', 'read', 'delivery'], true)
            || trim($method) === '' || mb_strlen($method) > 160 || trim($version) === '' || mb_strlen($version) > 160
            || trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw new DomainException('Preparation failure requires bounded method, version, stage and diagnostic reason.');
        }
        DB::transaction(function () use ($participant, $execution, $method, $version, $stage, $reason): void {
            [, $execution] = $this->lockExecution($participant, $execution);
            $this->executions->fail($execution, $reason, ['failure' => compact('method', 'version', 'stage')]);
        });
    }

    private function assertPresented(EvidenceExecution $execution): void
    {
        if ($execution->status !== 'presented') {
            throw new ConflictHttpException('Preparation must be presented and unresolved before completion.');
        }
    }

    private function artifact(Receipt $receipt, string $id): ?array
    {
        $artifact = collect($receipt->metadata['artifacts'] ?? [])->firstWhere('hash', $id);

        return is_array($artifact) && is_string($artifact['path'] ?? null)
            && is_int($artifact['size'] ?? null) && $artifact['size'] >= 0 ? $artifact : null;
    }

    private function lockExecution(Participant $participant, EvidenceExecution $execution): array
    {
        $stored = EvidenceExecution::findOrFail($execution->id);
        $phase = $this->lockPhase($participant, $stored->phase_id, $stored->proposition_id);
        $stored = EvidenceExecution::lockForUpdate()->findOrFail($stored->id);
        if ($stored->type !== 'prepare' || $stored->cycle_id !== $phase->cycle_id) {
            throw new DomainException('The execution must be this Participant’s Preparation.');
        }

        return [$phase, $stored];
    }

    private function lockPhase(Participant $participant, string $phaseId, string $gateId): Phase
    {
        Substrate::lockForUpdate()->findOrFail(Participant::findOrFail($participant->id)->substrate_id);
        $participant = Participant::lockForUpdate()->findOrFail($participant->id);
        $cycle = Cycle::where('participant_id', $participant->id)->whereNull('completed_at')->latest('sequence')->lockForUpdate()->first();
        $phase = $cycle === null ? null : Phase::where('cycle_id', $cycle->id)->whereNull('completed_at')->latest('kind')->lockForUpdate()->first();
        if ($phase === null || $phase->id !== $phaseId || $phase->kind !== PhaseKind::Disperse
            || $phase->gate_proposition_id !== $gateId || $participant->current_proposition_id !== $gateId) {
            throw new ConflictHttpException('Preparation requires the Participant’s active Disperse Phase and current Gate.');
        }
        app(CausalContextService::class)->assertPhase($phase);
        if (! app(PhasePositionService::class)->acceptsGate($phase, $phase->gate)
            || app(ExhaustionService::class)->unavailable($phase->gate)) {
            throw new ConflictHttpException('Preparation requires a lawful Disperse Gate on an available branch.');
        }

        return $phase;
    }
}
