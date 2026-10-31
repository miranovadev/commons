<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** One admitted generative opportunity per Cycle, independent of observation and Receipt consumption. */
final class AcquisitionAttemptService
{
    public function role(Phase $phase): ?string
    {
        return match ($phase->kind) {
            PhaseKind::Distinguish => 'distinguish_discovery',
            PhaseKind::Persist => 'persist_examination',
            PhaseKind::Disperse => 'disperse_acquisition',
            default => null,
        };
    }

    public function execution(Phase $phase, ?ParticipationContext $context = null): ?EvidenceExecution
    {
        $read = fn () => EvidenceExecution::where('cycle_id', $phase->cycle_id)->whereNotNull('attempt_admitted_at')->first();

        return $context === null ? $read() : $context->remember('acquisition-execution:'.$phase->cycle_id, $read);
    }

    public function state(Phase $phase, ?ParticipationContext $context = null): array
    {
        $read = function () use ($phase, $context): array {
            $execution = $this->execution($phase, $context);
            $status = 'not_required';
            if ($execution !== null) {
                $status = $execution->attempt_resolution !== null || $execution->phase_id !== $phase->id ? 'resolved'
                    : ($execution->observationRecorded() || $execution->status === 'presented' ? 'awaiting_resolution' : 'pending');
            } elseif ($this->role($phase) !== null && app(PhaseGenerationService::class)->available($phase, 'proposition.variant', $context, prepared: true)) {
                $status = 'required';
            }

            return ['role' => $this->role($phase), 'gate_id' => $phase->gate_proposition_id,
                'status' => $status, 'execution_id' => $execution?->id, 'outcome' => $execution?->attempt_resolution];
        };

        return $context === null ? $read() : $context->remember('acquisition-attempt:'.$phase->id, $read);
    }

    public function admitted(Phase $phase, ?ParticipationContext $context = null): bool
    {
        $execution = $this->execution($phase, $context);

        return $execution !== null && $execution->phase_id === $phase->id
            && $execution->proposition_id === $phase->gate_proposition_id && $execution->attempt_resolution === null;
    }

    public function ready(Phase $phase, ?ParticipationContext $context = null): bool
    {
        return $this->admitted($phase, $context) && ($this->execution($phase, $context)->observationRecorded()
            || $this->execution($phase, $context)->status === 'presented');
    }

    public function schema(Phase $phase, EvidenceExecution $execution): array
    {
        $actions = [];
        if ($this->ready($phase)) {
            $actions[] = 'acquisition.defer';
            if (app(PhaseGenerationService::class)->available($phase, 'proposition.variant')) {
                $actions[] = 'proposition.variant';
            }
        }
        $schema = app(InputSchemaService::class)->forPhase($phase->kind, $actions);
        foreach ($schema['$defs']['actions'] as &$definition) {
            $definition['properties']['execution_id'] = ['type' => 'string', 'const' => $execution->id];
            $definition['required'][] = 'execution_id';
        }

        return $schema;
    }

    public function finish(EvidenceExecution $execution, ?Proposition $result, ?string $reason): void
    {
        $execution->refresh();
        if ($execution->attempt_resolution !== null) {
            throw new ConflictHttpException('The acquisition attempt is already resolved.');
        }
        $execution->forceFill(['attempt_resolution' => ['action' => $result === null ? 'defer' : 'variant',
            'proposition_id' => $result?->id, 'reason' => $reason]])->save();
        if ($result === null) {
            $execution->phase->forceFill(['completed_at' => now()])->save();
            $execution->cycle->forceFill(['completed_at' => now(), 'deferred_at' => now()])->save();
        }
    }

    public function resolve(Participant $participant, EvidenceExecution $execution, array $input): void
    {
        DB::transaction(function () use ($participant, $execution, $input): void {
            Substrate::lockForUpdate()->findOrFail($participant->substrate_id);
            Participant::lockForUpdate()->findOrFail($participant->id);
            $execution = EvidenceExecution::lockForUpdate()->findOrFail($execution->id);
            $phase = $execution->phase;
            if ($phase->cycle->participant_id !== $participant->id || $execution->attempt_admitted_at === null) {
                throw new \DomainException('The attempt belongs to another Participant.');
            }
            $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
            if ($execution->attempt_resolution !== null) {
                if (($execution->attempt_resolution['request_hash'] ?? null) === $hash) {
                    return;
                }
                throw new \DomainException('The attempt already has a different resolution.');
            }
            if ($phase->completed_at !== null || $phase->cycle->completed_at !== null
                || app(PhaseService::class)->currentFor($participant)->id !== $phase->id) {
                throw new \DomainException('The acquisition attempt is no longer current.');
            }
            app(CausalContextService::class)->assertPhase($phase);
            app(ContributionService::class)->validateSchema($this->schema($phase, $execution), $input);
            $result = null;
            if ($input['action'] === 'proposition.variant') {
                unset($input['execution_id']);
                $result = app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
                app(PhaseProgressionService::class)->advance($phase, $result);
            }
            if ($result !== null) {
                return;
            }
            $execution->forceFill(['attempt_resolution' => ['action' => $result === null ? 'defer' : 'variant',
                'proposition_id' => $result?->id, 'reason' => $input['reason'] ?? null, 'request_hash' => $hash]])->save();
            if ($result === null) {
                $phase->forceFill(['completed_at' => now()])->save();
                $phase->cycle->forceFill(['completed_at' => now(), 'deferred_at' => now()])->save();
            }
        });
    }

    public function allows(Phase $phase, string $action, ?ParticipationContext $context = null): bool
    {
        if ($phase->completed_at !== null || $phase->cycle->completed_at !== null || $this->state($phase, $context)['status'] !== 'required') {
            return false;
        }

        return match ($action) {
            'instrument.execute' => $phase->kind !== PhaseKind::Persist
                || ! app(PreparationService::class)->hasReadableArtifacts($phase, $context),
            'preparation.start' => in_array($phase->kind, [PhaseKind::Persist, PhaseKind::Disperse], true)
                && app(PreparationService::class)->hasReadableArtifacts($phase, $context),
            default => false,
        };
    }
}
