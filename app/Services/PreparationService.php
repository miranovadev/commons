<?php

namespace App\Services;

use App\Domain\Evidence\PreparationInput;
use App\Domain\Evidence\PreparationPresentation;
use App\Enums\PhaseKind;
use App\Exceptions\ArtifactReadingException;
use App\Exceptions\ArtifactStorageException;
use App\Models\Cycle;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\Evidence\ArtifactReaderRegistry;
use App\Services\Evidence\ArtifactStore;
use App\Services\Evidence\PreparationAllocation;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/** Preparation selection, operational lifecycle and atomic structural resolution. */
final class PreparationService
{
    /** Request-local immutable descriptor capabilities; never cache catalogue membership. */
    private array $readingCapabilities = [];

    public function __construct(private readonly EvidenceExecutionService $executions) {}

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
        foreach ($input->artifacts as $selection) {
            $option = array_find($readable, fn ($item) => $item['receipt_id'] === $selection['receipt_id']
                && $item['artifact_id'] === $selection['artifact_id']);
            if ($option === null || ! in_array($selection['read_type'], array_column($option['capabilities'], 'read_type'), true)) {
                throw new DomainException('Each selection requires an accessible Artifact and supported Read Type.');
            }
        }

        return DB::transaction(function () use ($participant, $phase, $input): EvidenceExecution {
            $phase = $this->lockPhase($participant, $phase->id, $phase->gate_proposition_id);
            if (! app(AcquisitionAttemptService::class)->allows($phase, 'preparation.start')) {
                throw new ConflictHttpException('Personal acquisition at this Gate is already resolved, reserved or unavailable.');
            }
            $catalogue = $this->causalReceipts($phase)->keyBy('id');
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
        // Runtime capability/version probing stays outside structural write transactions.
        $reader = app(ArtifactReaderRegistry::class);
        foreach ($presentation->deliveries as $delivery) {
            $capability = array_find($reader->capabilities($delivery['source_type'], $delivery['delivery_method']),
                fn ($item) => $item['read_type'] === $delivery['read_type']);
            if ($delivery['method'] !== ($capability['method'] ?? null) || $delivery['version'] !== ($capability['version'] ?? null)
                || $delivery['representation'] !== $delivery['read_type']) {
                throw new DomainException('Presentation requires an explicitly supported reader, version and representation.');
            }
        }
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
                    $whole = $delivery['boundaries']['offset'] === 0 && $delivery['boundaries']['end'] === $delivery['boundaries']['total'];
                    if ($delivery['representation_hash'] !== $artifact['hash']
                        || ($whole && ($delivery['payload_hash'] !== $artifact['hash'] || $delivery['payload_bytes'] !== $artifact['size']))) {
                        throw new DomainException('Raw representation must retain the preserved Artifact byte identity.');
                    }
                }
                $associations = $selected->filter(fn ($item) => PreparationInput::requestId($item) === $delivery['request_id'])->pluck('receipt_id')->unique()->values()->all();
                if ($delivery['receipt_ids'] !== $associations) {
                    throw new DomainException('Presentation must preserve every selected Receipt association.');
                }
            }
            $this->executions->present($execution, $presentation);
        });
    }

    public function contribute(Participant $participant, EvidenceExecution $execution, array $input): Receipt
    {
        if (! in_array($input['action'] ?? null, ['proposition.variant'], true)) {
            throw new DomainException('Preparation can contribute only its admitted lawful Variant.');
        }

        return DB::transaction(function () use ($participant, $execution, $input): Receipt {
            $hash = $this->resolutionHash($execution, $input);
            if ($receipt = $this->resolvedRetry($participant, $execution, $hash)) {
                return $receipt;
            }
            [$phase, $execution] = $this->lockExecution($participant, $execution);
            $this->assertPresented($execution);
            // Reuse the ordinary schema, structural authority, birth and epoch paths.
            $schema = $this->resolutionSchema($participant, $phase, $execution);
            $input['execution_id'] ??= $execution->id;
            app(ContributionService::class)->validateSchema($schema, $input);
            $result = app(ContributionDispatcher::class)->dispatch($participant, $phase, $input, preparation: $execution);
            $receipt = $this->executions->completePreparation($execution, $result, null, $hash);
            app(AcquisitionAttemptService::class)->finish($execution, $result, null);
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
            $hash = $this->resolutionHash($execution, ['action' => 'preparation.defer', 'reason' => $reason]);
            if ($receipt = $this->resolvedRetry($participant, $execution, $hash)) {
                return $receipt;
            }
            [, $execution] = $this->lockExecution($participant, $execution);
            $this->assertPresented($execution);

            $receipt = $this->executions->completePreparation($execution, null, $reason, $hash);
            app(AcquisitionAttemptService::class)->finish($execution, null, $reason);

            return $receipt;
        });
    }

    /** Trusted adapter only. No action or HTTP endpoint accepts failure diagnostics. */
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
            $details = array_intersect_key($details, array_flip(['artifact_id', 'read_type', 'source_type', 'classification']));
            foreach ($details as $value) {
                if (! is_string($value) || strlen($value) > 160 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                    throw new DomainException('Failure identity fields must be bounded.');
                }
            }
            $reason = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $reason));
            $details['classification'] ??= match ($stage) {
                'unsupported' => 'missing_capability', 'read' => 'reader_failure',
                'delivery' => 'delivery_failure', 'inference' => 'inference_failure', default => $stage,
            };
            $diagnostic = ['failure' => [...compact('method', 'version', 'stage'), ...$details], 'failure_reason' => $reason];
            // A reservation alone does not prove an attempt. Acknowledged presentation
            // does: expiry after that real delivery records its unsuccessful resolution.
            if ($stage === 'stale' || ($stage === 'expired' && $stored->presentation === null)
                || $details['classification'] === 'invalid_parameters') {
                $this->executions->fail($stored, $reason, $diagnostic);
            } else {
                $this->executions->recordPreparationFailure($stored, $reason, $diagnostic);
                // Failure can resolve outside ParticipationService (reader or Wake).
                // Its Receipt changes the field just like a successful resolution.
                // Publish only after commit, outside the evidence write locks.
                DB::afterCommit(fn () => app(PotentialProjectionService::class)->refresh($participant->substrate_id));
            }
        });
    }

    public function active(Phase $phase): ?EvidenceExecution
    {
        return EvidenceExecution::where('cycle_id', $phase->cycle_id)->where('type', 'prepare')
            ->whereIn('status', ['pending', 'presented'])->first();
    }

    public function eligible(Phase $phase, ?ParticipationContext $context = null): bool
    {
        return in_array($phase->kind, [PhaseKind::Persist, PhaseKind::Disperse], true)
            && $phase->completed_at === null && $phase->cycle->completed_at === null
            && app(AcquisitionAttemptService::class)->allows($phase, 'preparation.start', $context)
            && $this->options($phase, $context) !== [];
    }

    /** Independent of the Preparation allowance: spending it must not enable fallback. */
    public function hasReadableArtifacts(Phase $phase, ?ParticipationContext $context = null): bool
    {
        foreach ($this->accessibleArtifacts($phase, $context) as [, $artifact]) {
            // A newly accessible, unprobed descriptor under a write lock is unknown,
            // not proof of absence. Withhold fallback and require a fresh preflight.
            if ($this->readingCapability($artifact) !== []) {
                return true;
            }
        }

        return false;
    }

    /** Capability discovery reads immutable bytes outside write transactions; no content is cached. */
    public function options(Phase $phase, ?ParticipationContext $context = null): array
    {
        $result = [];
        foreach ($this->accessibleArtifacts($phase, $context) as [$receipt, $artifact]) {
            $reading = $this->readingCapability($artifact);
            if ($reading === null || $reading === []) {
                continue;
            }
            $result[] = ['receipt_id' => $receipt->id, 'artifact_id' => $artifact['hash'],
                'reference' => $receipt->reference, 'source_type' => $reading['source_type'],
                'size' => $artifact['size'], 'capabilities' => $reading['capabilities']];
        }

        return $result;
    }

    private function causalReceipts(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $select = function () use ($phase, $context) {
            $plan = ['gate_id' => $phase->gate_proposition_id,
                'phase' => ['kind' => config("phases.{$phase->kind->value}.name")]];
            $ids = app(CognitiveEvidenceHorizon::class)->propositionIds($plan)
                ->merge(app(CausalWriteService::class)->forPhase($phase, $context)->pluck('id'));
            $references = app(ParticipantReceiptProjection::class)->referencesFor($ids->unique())->flatten(1)->pluck('id')->unique();

            return Receipt::whereKey($references)->whereHas('evidenceExecution.phase.cycle.participant',
                fn ($q) => $q->where('substrate_id', $phase->cycle->participant->substrate_id))->get();
        };

        return $context === null ? $select() : $context->remember('preparation-causal-receipts:'.$phase->id, $select);
    }

    private function accessibleArtifacts(Phase $phase, ?ParticipationContext $context): iterable
    {
        if (! in_array($phase->kind, [PhaseKind::Persist, PhaseKind::Disperse], true)) {
            return;
        }
        foreach ($this->causalReceipts($phase, $context) as $receipt) {
            foreach ($receipt->metadata['artifacts'] ?? [] as $artifact) {
                if (is_array($artifact) && $this->artifact($receipt, $artifact['hash'] ?? '') !== null) {
                    yield [$receipt, $artifact];
                }
            }
        }
    }

    /** null means unprobed under a write lock; [] means no supported capability. */
    private function readingCapability(array $artifact): ?array
    {
        $key = 'artifact-source:'.ArtifactReaderRegistry::VERSION.':'.hash('sha256', json_encode([
            config('artifacts.disk'), config('filesystems.disks.'.config('artifacts.disk')), $artifact, config('preparation'),
        ], JSON_THROW_ON_ERROR));
        if (array_key_exists($key, $this->readingCapabilities)) {
            return $this->readingCapabilities[$key];
        }
        if (DB::transactionLevel() > 0) {
            return null;
        }
        try {
            $source = Cache::get($key);
            if ($source === null) {
                $body = app(ArtifactStore::class)->read($artifact);
                $source = app(ArtifactReaderRegistry::class)->sourceType($body, $artifact['media_type'] ?? '');
                Cache::put($key, $source, 3600);
            }
            $capabilities = app(ArtifactReaderRegistry::class)->capabilities($source, 'inline-text');

            return $this->readingCapabilities[$key] = $capabilities === [] ? []
                : ['source_type' => $source, 'capabilities' => $capabilities];
        } catch (Throwable) {
            // Only this request remembers failed discovery; later requests may retry.
            // A selected execution records its own diagnostic on actual reading.
            return $this->readingCapabilities[$key] = [];
        }
    }

    public function resolutionSchema(Participant $participant, Phase $phase, EvidenceExecution $execution): array
    {
        if ($execution->status !== 'presented') {
            return app(InputSchemaService::class)->forPhase($phase->kind, []);
        }
        $actions = ['preparation.defer'];
        if (app(PhaseGenerationService::class)->available($phase, 'proposition.variant', prepared: true)) {
            $actions[] = 'proposition.variant';
        }
        $schema = app(InputSchemaService::class)->forPhase($phase->kind, $actions);
        foreach ($schema['$defs']['actions'] as $action => &$definition) {
            $definition['properties']['execution_id'] = ['type' => 'string', 'const' => $execution->id];
            $definition['required'][] = 'execution_id';
        }
        unset($definition);

        return app(InputSchemaService::class)->withReceiptOptions($schema, $phase, null);
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
        $materials = [];
        $bodies = [];
        $selection = [];
        $attempt = [];
        $reader = app(ArtifactReaderRegistry::class);
        try {
            foreach ($execution->input['artifacts'] as $selection) {
                $attempt = [];
                $id = PreparationInput::requestId($selection);
                if (isset($seen[$id])) {
                    $deliveries[$seen[$id]]['receipt_ids'][] = $selection['receipt_id'];

                    continue;
                }
                $seen[$id] = count($deliveries);
                $receipt = Receipt::findOrFail($selection['receipt_id']);
                $artifact = $this->artifact($receipt, $selection['artifact_id']);
                $body = $bodies[$selection['artifact_id']] ??= app(ArtifactStore::class)->read($artifact);
                $source = $reader->sourceType($body, $artifact['media_type'] ?? '');
                $attempt = ['source_type' => $source];
                $capability = array_find($reader->capabilities($source, $method), fn ($item) => $item['read_type'] === $selection['read_type']);
                $attempt += array_intersect_key($capability ?? [], array_flip(['method', 'version']));
                $parameters = $selection['parameters'] ?? [];
                $reader->validateParameters($parameters);
                // Same bytes/read type/source version are parsed once, even with different ranges.
                $key = $selection['artifact_id'].':'.$source.':'.$selection['read_type'];
                $representation = $materials[$key] ??= $reader->represent($body, $artifact['media_type'] ?? '',
                    $selection['read_type'], [], $method);
                $deliveries[] = ['artifact_id' => $selection['artifact_id'], 'request_id' => $id,
                    'receipt_ids' => [$selection['receipt_id']],
                    'delivery_id' => $execution->id.':'.$id, ...$representation, 'parameters' => $parameters];
            }
            $deliveries = app(PreparationAllocation::class)->allocate($deliveries);
            $acknowledgement = new PreparationPresentation(array_map(fn ($item) => array_diff_key($item, ['content' => true]), $deliveries));
        } catch (Throwable $exception) {
            $classification = $exception instanceof ArtifactReadingException ? $exception->classification
                : ($exception instanceof ArtifactStorageException ? 'storage_failure' : 'reader_failure');
            if ($exception instanceof ArtifactReadingException) {
                $attempt = [...$attempt, ...$exception->details];
                $selection = [...$selection, ...array_intersect_key($exception->details, array_flip(['artifact_id', 'read_type']))];
            }
            $this->fail($participant, $execution, $attempt['method'] ?? ArtifactReaderRegistry::METHOD, $attempt['version'] ?? ArtifactReaderRegistry::VERSION,
                $classification === 'missing_capability' ? 'unsupported' : 'read',
                $exception instanceof ArtifactReadingException || $exception instanceof ArtifactStorageException
                    ? mb_substr($exception->getMessage(), 0, 1000) : 'The reading adapter could not complete. No presentation was acknowledged.',
                ['classification' => $classification, ...array_intersect_key($attempt, ['source_type' => true]), ...array_intersect_key($selection, array_flip(['artifact_id', 'read_type']))]);
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

    private function resolutionHash(EvidenceExecution $execution, array $input): string
    {
        $input['execution_id'] = $execution->id;
        ksort($input);

        return hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
    }

    /** Exact terminal retries return the original Receipt without advancing again. */
    private function resolvedRetry(Participant $participant, EvidenceExecution $execution, string $hash): ?Receipt
    {
        Substrate::lockForUpdate()->findOrFail($participant->substrate_id);
        Participant::lockForUpdate()->findOrFail($participant->id);
        $stored = EvidenceExecution::with('phase.cycle', 'receipt')->findOrFail($execution->id);
        if ($stored->type !== 'prepare' || $stored->phase->cycle->participant_id !== $participant->id) {
            throw new ConflictHttpException('This Preparation belongs to another Participant.');
        }
        if ($stored->status !== 'completed') {
            return null;
        }
        if (($stored->receipt?->metadata['resolution_hash'] ?? null) !== $hash) {
            throw new ConflictHttpException('This Preparation already has a different recorded resolution.');
        }

        return $stored->receipt;
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
        if ($phase === null || $phase->id !== $phaseId || ! in_array($phase->kind, [PhaseKind::Persist, PhaseKind::Disperse], true)
            || $phase->gate_proposition_id !== $gateId || $participant->current_proposition_id !== $gateId) {
            throw new ConflictHttpException('Preparation requires the Participant’s active Persist or Disperse Phase and current Gate.');
        }
        app(CausalContextService::class)->assertPhase($phase);
        if (! app(PhasePositionService::class)->acceptsGate($phase, $phase->gate)
            || app(ExhaustionService::class)->unavailable($phase->gate)
            || app(ExhaustedContinuationService::class)->ids($phase)->has($phase->gate_proposition_id)) {
            throw new ConflictHttpException('Preparation requires a lawful acquisition Gate on an available branch.');
        }

        return $phase;
    }
}
