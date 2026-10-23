<?php

namespace App\Services;

use App\Domain\Evidence\PreparationInput;
use App\Domain\Evidence\PreparationPresentation;
use App\Enums\PhaseKind;
use App\Exceptions\ArtifactReadingException;
use App\Models\Cycle;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\Evidence\ArtifactReaderRegistry;
use App\Services\Evidence\ArtifactStore;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/** Preparation selection, operational lifecycle and atomic structural resolution. */
final readonly class PreparationService
{
    public function __construct(private EvidenceExecutionService $executions) {}

    public function start(Participant $participant, Phase $phase, array $input): EvidenceExecution
    {
        if ($phase->cycle->participant_id !== $participant->id || $phase->cycle->participant->substrate_id !== $participant->substrate_id) {
            throw new DomainException('Preparation requires the acting Participant’s Phase.');
        }
        $input = PreparationInput::from($input);
        $readable = $this->options($phase);
        if ($readable === []) {
            throw new DomainException('No accessible Artifact has a supported reading capability.');
        }

        return DB::transaction(function () use ($participant, $phase, $input): EvidenceExecution {
            $phase = $this->lockPhase($participant, $phase->id, $phase->gate_proposition_id);
            if (EvidenceExecution::where('cycle_id', $phase->cycle_id)->where('type', 'prepare')->exists()) {
                throw new ConflictHttpException('The Preparation allowance for this Cycle is already reserved.');
            }
            if (app(HorizonReceiptService::class)->ordinaryAvailable($phase)->isNotEmpty()) {
                throw new ConflictHttpException('Available ordinary evidence enables generation instead of Preparation.');
            }
            $catalogue = app(HorizonReceiptService::class)->accessibleOrdinary($phase)->keyBy('id');
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
            if ($execution->status === 'presented' && $execution->presentation == $presentation->deliveries) {
                return; // Retry of this exact delivery acknowledges no second historical event.
            }
            if ($execution->status !== 'pending') {
                throw new ConflictHttpException('Only a pending Preparation can accept a new presentation.');
            }
            $selected = collect($execution->input['artifacts']);
            $expected = $selected->map(fn ($selection) => PreparationInput::requestId($selection))->unique()->sort()->values()->all();
            $actual = collect($presentation->deliveries)->pluck('request_id')->sort()->values()->all();
            if ($expected !== $actual) {
                throw new DomainException('Every selected representation must be delivered exactly once.');
            }
            $receipts = Receipt::whereKey($selected->pluck('receipt_id'))->get()->keyBy('id');
            foreach ($presentation->deliveries as $delivery) {
                if ($delivery['read_type'] === 'raw') {
                    $selection = $selected->first(fn ($item) => PreparationInput::requestId($item) === $delivery['request_id']);
                    $artifact = $this->artifact($receipts[$selection['receipt_id']], $selection['artifact_id']);
                    if ($delivery['payload_hash'] !== $artifact['hash'] || $delivery['payload_bytes'] !== $artifact['size']) {
                        throw new DomainException('Raw delivery must match the entire preserved Artifact byte identity.');
                    }
                }
                $capabilities = app(ArtifactReaderRegistry::class)->capabilities($delivery['source_type'], $delivery['delivery_method']);
                if ($delivery['method'] !== ArtifactReaderRegistry::METHOD || $delivery['version'] !== ArtifactReaderRegistry::VERSION
                    || $delivery['representation'] !== $delivery['read_type']
                    || ! in_array($delivery['read_type'], array_column($capabilities, 'read_type'), true)) {
                    throw new DomainException('Presentation requires an explicitly supported reader, version and representation.');
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
            $schema = $this->resolutionSchema($participant, $phase, $execution);
            $input['execution_id'] ??= $execution->id;
            app(ContributionService::class)->validateSchema($schema, $input);
            $result = app(ContributionDispatcher::class)->dispatch($participant, $phase, $input, preparation: $execution);
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
    public function fail(Participant $participant, EvidenceExecution $execution, string $method, string $version, string $stage, string $reason, array $details = []): void
    {
        if (! in_array($stage, ['unsupported', 'read', 'delivery', 'inference', 'expired', 'stale'], true)
            || trim($method) === '' || mb_strlen($method) > 160 || trim($version) === '' || mb_strlen($version) > 160
            || trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw new DomainException('Preparation failure requires bounded method, version, stage and diagnostic reason.');
        }
        DB::transaction(function () use ($participant, $execution, $method, $version, $stage, $reason, $details): void {
            Substrate::lockForUpdate()->findOrFail($participant->substrate_id);
            Participant::lockForUpdate()->findOrFail($participant->id);
            $stored = EvidenceExecution::with('phase.cycle')->findOrFail($execution->id);
            if ($stored->type !== 'prepare' || $stored->phase->cycle->participant_id !== $participant->id) {
                throw new DomainException('This Preparation belongs to another Participant.');
            }
            Cycle::lockForUpdate()->findOrFail($stored->cycle_id);
            Phase::lockForUpdate()->findOrFail($stored->phase_id);
            $stored = EvidenceExecution::lockForUpdate()->findOrFail($stored->id);
            if (! in_array($stored->status, ['pending', 'presented'], true)) {
                return;
            }
            $details = array_intersect_key($details, array_flip(['artifact_id', 'read_type', 'classification']));
            foreach ($details as $value) {
                if (! is_string($value) || strlen($value) > 160) {
                    throw new DomainException('Failure identity fields must be bounded.');
                }
            }
            $this->executions->fail($stored, $reason, ['failure' => [...compact('method', 'version', 'stage'), ...$details]]);
        });
    }

    public function active(Phase $phase): ?EvidenceExecution
    {
        return EvidenceExecution::where('cycle_id', $phase->cycle_id)->where('type', 'prepare')
            ->whereIn('status', ['pending', 'presented'])->first();
    }

    public function eligible(Phase $phase, ?ParticipationContext $context = null): bool
    {
        return $phase->kind === PhaseKind::Disperse && $phase->completed_at === null && $phase->cycle->completed_at === null
            && ! EvidenceExecution::where('cycle_id', $phase->cycle_id)->where('type', 'prepare')->exists()
            && app(HorizonReceiptService::class)->ordinaryAvailable($phase, $context)->isEmpty()
            && $this->options($phase, $context) !== [];
    }

    /** Capability discovery reads immutable bytes outside write transactions; no content is cached. */
    public function options(Phase $phase, ?ParticipationContext $context = null): array
    {
        if ($phase->kind !== PhaseKind::Disperse) {
            return [];
        }
        $result = [];
        foreach (app(HorizonReceiptService::class)->accessibleOrdinary($phase, $context) as $receipt) {
            foreach ($receipt->metadata['artifacts'] ?? [] as $artifact) {
                if (! is_array($artifact) || $this->artifact($receipt, $artifact['hash'] ?? '') === null) {
                    continue;
                }
                try {
                    $key = 'artifact-source:'.ArtifactReaderRegistry::VERSION.':'.hash('sha256', json_encode([
                        config('artifacts.disk'), config('filesystems.disks.'.config('artifacts.disk')), $artifact, config('preparation'),
                    ], JSON_THROW_ON_ERROR));
                    $source = Cache::get($key);
                    if ($source === null) {
                        $body = app(ArtifactStore::class)->read($artifact);
                        $source = app(ArtifactReaderRegistry::class)->sourceType($body, $artifact['media_type'] ?? '');
                        Cache::put($key, $source, 3600);
                    }
                    $capabilities = array_values(array_filter(app(ArtifactReaderRegistry::class)->capabilities($source, 'inline-text'),
                        fn ($capability) => $capability['read_type'] !== 'raw' || $artifact['size'] <= (int) config('preparation.selection_bytes')));
                    $result[] = ['receipt_id' => $receipt->id, 'artifact_id' => $artifact['hash'],
                        'reference' => $receipt->reference, 'source_type' => $source, 'size' => $artifact['size'], 'capabilities' => $capabilities];
                } catch (Throwable) {
                    // Unsupported/unavailable material is not advertised as readable.
                    // An actual selected execution records its own diagnostic on read.
                }
            }
        }

        return $result;
    }

    public function resolutionSchema(Participant $participant, Phase $phase, EvidenceExecution $execution): array
    {
        $revisions = app(PropositionOptionService::class)->revisionOptionsFor($phase, $participant, prepared: true)->pluck('id')->all();
        $actions = ['preparation.defer'];
        foreach (['proposition.variant', 'proposition.revise'] as $action) {
            if (app(PhaseGenerationService::class)->available($phase, $action, prepared: true)
                && ($action !== 'proposition.revise' || $revisions !== [])) {
                $actions[] = $action;
            }
        }
        $schema = app(InputSchemaService::class)->forPhase($phase->kind, $actions);
        foreach ($schema['$defs']['actions'] as $action => &$definition) {
            $definition['properties']['execution_id'] = ['type' => 'string', 'const' => $execution->id];
            $definition['required'][] = 'execution_id';
            if ($action === 'proposition.revise') {
                $definition['properties']['proposition_id']['enum'] = $revisions;
            }
        }
        unset($definition);

        return $schema;
    }

    /** The dispatcher requires this explicit execution, never an ambient bypass flag. */
    public function assertResolution(Participant $participant, Phase $phase, EvidenceExecution $execution): void
    {
        [$active, $stored] = $this->lockExecution($participant, $execution);
        $this->assertPresented($stored);
        if ($active->id !== $phase->id) {
            throw new ConflictHttpException('The prepared contribution is stale.');
        }
    }

    /** Transient material only. No content is persisted in execution, Receipt or ordinary context. */
    public function delivery(Participant $participant, EvidenceExecution $execution, string $method): array
    {
        [, $execution] = DB::transaction(fn () => $this->lockExecution($participant, $execution));
        if (! in_array($execution->status, ['pending', 'presented'], true)) {
            throw new ConflictHttpException('This Preparation is already resolved.');
        }
        $deliveries = [];
        $seen = [];
        $bytes = 0;
        $selection = [];
        try {
            foreach ($execution->input['artifacts'] as $selection) {
                $id = PreparationInput::requestId($selection);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $receipt = Receipt::findOrFail($selection['receipt_id']);
                $artifact = $this->artifact($receipt, $selection['artifact_id']);
                $body = app(ArtifactStore::class)->read($artifact);
                $representation = app(ArtifactReaderRegistry::class)->read($body, $artifact['media_type'] ?? '',
                    $selection['read_type'], $selection['parameters'] ?? [], $method);
                $bytes += $representation['payload_bytes'];
                if ($bytes > min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.delivery_bytes'))) {
                    throw new ArtifactReadingException('payload_limit', 'The combined delivery exceeds its configured budget.');
                }
                $deliveries[] = ['artifact_id' => $selection['artifact_id'], 'request_id' => $id,
                    'delivery_id' => $execution->id.':'.$id, ...$representation];
            }
            $acknowledgement = new PreparationPresentation(array_map(fn ($item) => array_diff_key($item, ['content' => true]), $deliveries));
        } catch (Throwable $exception) {
            $classification = $exception instanceof ArtifactReadingException ? $exception->classification : 'storage_failure';
            $this->fail($participant, $execution, ArtifactReaderRegistry::METHOD, ArtifactReaderRegistry::VERSION,
                $classification === 'missing_capability' ? 'unsupported' : 'read', mb_substr($exception->getMessage(), 0, 1000),
                ['classification' => $classification, ...array_intersect_key($selection, array_flip(['artifact_id', 'read_type']))]);
            throw new DomainException('Preparation reading failed: '.$exception->getMessage(), previous: $exception);
        }

        return ['execution_id' => $execution->id, 'gate_id' => $execution->proposition_id,
            'purpose' => $execution->input['purpose'], 'selections' => $execution->input['artifacts'], 'deliveries' => $deliveries,
            'presentation_token' => Crypt::encryptString(json_encode(['participant_id' => $participant->id,
                'execution_id' => $execution->id, 'deliveries' => $acknowledgement->deliveries], JSON_THROW_ON_ERROR))];
    }

    public function acknowledge(Participant $participant, EvidenceExecution $execution, string $token): void
    {
        $ack = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        if (($ack['participant_id'] ?? null) !== $participant->id || ($ack['execution_id'] ?? null) !== $execution->id) {
            throw new DomainException('Presentation acknowledgement belongs to a different Preparation.');
        }
        $this->present($participant, $execution, new PreparationPresentation($ack['deliveries']));
    }

    public function recover(Participant $participant, Phase $phase): void
    {
        $execution = $this->active($phase);
        if ($execution === null) {
            return;
        }
        $stale = $execution->phase_id !== $phase->id || $execution->proposition_id !== $phase->gate_proposition_id
            || app(ExhaustionService::class)->unavailable($phase->gate)
            || app(ExhaustedContinuationService::class)->ids($phase)->has($phase->gate_proposition_id);
        if ($stale || $execution->started_at->lt(now()->subSeconds((int) config('preparation.unresolved_seconds')))) {
            $this->fail($participant, $execution, ArtifactReaderRegistry::METHOD, ArtifactReaderRegistry::VERSION, $stale ? 'stale' : 'expired',
                $stale ? 'The initiating Phase, Gate or available branch changed. No completion was inferred.'
                    : 'The unresolved Preparation expired. No completion was inferred.');
        }
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
            || app(ExhaustionService::class)->unavailable($phase->gate)
            || app(ExhaustedContinuationService::class)->ids($phase)->has($phase->gate_proposition_id)) {
            throw new ConflictHttpException('Preparation requires a lawful Disperse Gate on an available branch.');
        }

        return $phase;
    }
}
