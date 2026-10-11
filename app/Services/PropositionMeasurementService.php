<?php

namespace App\Services;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\CorrespondenceValues;
use App\Enums\ObservableDirection;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizonMembership;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Services\Observables\CanonicalMeasurementService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\ObservableService;
use App\Services\Participation\ParticipationContext;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PropositionMeasurementService
{
    public function __construct(
        private readonly ObservableService $observables,
    ) {}

    public function recalculate(
        Proposition $proposition,
        ?DateTimeInterface $calculatedAt = null,
        ?ParticipationContext $context = null,
    ): PropositionMeasurement {
        $snapshot = $proposition->tier === 'committal'
            ? ($context !== null ? $context->snapshots->firstWhere('proposition_id', $proposition->id) : CommitmentSnapshot::find($proposition->id)) : null;
        if ($snapshot !== null) {
            return $this->project($proposition, $snapshot->correspondence, $snapshot->chirality, $snapshot->closure, $calculatedAt);
        }
        $components = $this->observables->components($proposition, $context);
        $values = $components->observables();

        return $this->project(
            $proposition,
            $values->correspondence,
            $values->chirality,
            $values->closure,
            $calculatedAt,
            $components->chiralityComponents,
            $components->correspondenceComponents,
        );
    }

    /** Mutation-only direction observation; never publishes disposable measurements. */
    public function observeChirality(Proposition $position, ?ParticipationContext $context = null): float
    {
        if ($canonical = app(CanonicalMeasurementService::class)->for($position, $context)) {
            return $canonical->chirality;
        }
        $snapshot = $position->tier === 'committal'
            ? ($context === null ? CommitmentSnapshot::find($position->id) : $context->snapshots->firstWhere('proposition_id', $position->id)) : null;
        if ($snapshot !== null) {
            return $snapshot->chirality;
        }
        [$value, $components] = app(LineageMeasurementService::class)->chirality($position, $context);
        $attributes = [...$this->componentAttributes($position, $components, $context),
            'chirality' => $value, 'chirality_direction' => $this->resolvedChiralityDirection($position, $value, $context)->value];
        DB::table('chirality_observations')->upsert([['proposition_id' => $position->id,
            ...$attributes]], ['proposition_id'], array_keys($attributes));

        return $value;
    }

    public function recalculateIfMeasurable(
        Proposition $proposition,
        ?DateTimeInterface $calculatedAt = null,
        ?ParticipationContext $context = null,
    ): ?PropositionMeasurement {
        if ($context !== null ? ! $context->memberships->has($proposition->id) : ! ContextHorizonMembership::query()->whereKey($proposition->getKey())->exists()) {
            return null;
        }

        return $this->recalculate($proposition, $calculatedAt, $context);
    }

    /**
     * Mutation/maintenance only: populate missing or obsolete projections.
     * Callers must hold the Substrate lock before loading calculation state.
     * Never call this from ordinary graph/detail serialization.
     * Current-version projections are left untouched, and propositions outside a
     * Context Horizon remain deliberately unmeasured.
     *
     * @param  Collection<int, Proposition>  $propositions
     */
    public function repairMissing(Collection $propositions, ?ParticipationContext $context = null): void
    {
        foreach ($propositions as $original) {
            $proposition = $context?->proposition($original) ?? $original;
            $proposition->loadMissing('measurement');

            if ($proposition->measurement === null
                || $proposition->measurement->calculator_version !== LineageMeasurementService::VERSION) {
                $this->recalculateIfMeasurable($proposition, context: $context);
            }
            $original->setRelation('measurement', $proposition->measurement);
        }
    }

    /** Derive missing/stale values for this observation without writing projections or directions. */
    public function observeMissing(Collection $propositions, ?ParticipationContext $context = null): void
    {
        foreach ($propositions as $original) {
            $proposition = $context?->proposition($original) ?? $original;
            $proposition->loadMissing('measurement');
            if ($proposition->measurement === null
                || $proposition->measurement->calculator_version !== LineageMeasurementService::VERSION) {
                $measurable = $context !== null ? $context->memberships->has($proposition->id)
                    : ContextHorizonMembership::whereKey($proposition->id)->exists();
                if ($measurable) {
                    $snapshot = $proposition->tier === 'committal'
                        ? ($context !== null ? $context->snapshots->firstWhere('proposition_id', $proposition->id) : CommitmentSnapshot::find($proposition->id)) : null;
                    $components = $snapshot === null ? $this->observables->components($proposition, $context) : null;
                    $values = $snapshot ?? $components->observables();
                    $attributes = $snapshot !== null ? $snapshot->only([
                        'chirality_direction', 'orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction',
                        'shape_topology', 'phase_profile', 'shape_topology_direction', 'phase_profile_direction',
                    ]) : ($components->chiralityComponents === null ? [] : $this->componentAttributes($proposition, $components->chiralityComponents, $context));
                    if ($snapshot === null && $components->correspondenceComponents !== null) {
                        $attributes = [...$attributes, ...$this->correspondenceComponentAttributes($proposition, $components->correspondenceComponents, $context)];
                    }
                    $measurement = new PropositionMeasurement;
                    $measurement->forceFill([
                        ...$attributes,
                        'chirality_direction' => $snapshot?->chirality_direction ?? $this->resolvedChiralityDirection($proposition, $values->chirality, $context)->value,
                        'proposition_id' => $proposition->id,
                        'correspondence' => $values->correspondence,
                        'chirality' => $values->chirality,
                        'closure' => $values->closure,
                        'calculated_at' => now(),
                        'calculator_version' => LineageMeasurementService::VERSION,
                    ]);
                    $proposition->setRelation('measurement', $measurement);
                }
            }
            $original->setRelation('measurement', $proposition->measurement);
        }
    }

    public function project(
        Proposition $proposition,
        ?float $correspondence,
        ?float $chirality,
        ?float $closure,
        ?DateTimeInterface $calculatedAt = null,
        ?ChiralityValues $chiralityComponents = null,
        ?CorrespondenceValues $correspondenceComponents = null,
    ): PropositionMeasurement {
        $this->assertMeasurement('correspondence', $correspondence);
        $this->assertMeasurement('chirality', $chirality);
        $this->assertMeasurement('closure', $closure);

        return DB::transaction(function () use ($proposition, $correspondence, $chirality, $closure, $calculatedAt, $chiralityComponents, $correspondenceComponents): PropositionMeasurement {
            // Maintenance and contribution writes use the same root-before-node order.
            Substrate::query()->lockForUpdate()->findOrFail($proposition->phase->cycle->participant->substrate_id);
            $closure = app(ProspectiveClosureService::class)->stored($proposition)['closure'];
            // Lock the Proposition so the numeric projection and retained direction move together.
            Proposition::query()->whereKey($proposition->id)->lockForUpdate()->firstOrFail();
            app(FieldMutationService::class)->touch($proposition->phase, [$proposition->id], measurementsDirty: false);
            $canonical = app(CanonicalMeasurementService::class)->for($proposition);
            if ($canonical !== null) {
                [$correspondence, $chirality] = [$canonical->correspondence, $canonical->chirality];
                $chiralityComponents = $canonical->chiralityComponents();
                $correspondenceComponents = $canonical->correspondenceComponents();
            }
            $snapshot = $proposition->tier === 'committal' ? CommitmentSnapshot::find($proposition->id) : null;
            if ($snapshot !== null) {
                [$correspondence, $chirality, $closure] = [$snapshot->correspondence, $snapshot->chirality, $snapshot->closure];
            }

            if ($correspondence !== null && $correspondence !== 0.0) {
                DB::table('propositions')->where('id', $proposition->id)->update([
                    'correspondence_direction' => ObservableDirection::after($correspondence)->value,
                ]);
            }
            $componentAttributes = $snapshot !== null ? $snapshot->only([
                'chirality_direction', 'orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction',
                'shape_topology', 'phase_profile', 'shape_topology_direction', 'phase_profile_direction',
            ]) : ($chiralityComponents === null ? [] : $this->componentAttributes($proposition, $chiralityComponents));
            if ($snapshot === null && $correspondenceComponents !== null) {
                $componentAttributes = [...$componentAttributes, ...$this->correspondenceComponentAttributes($proposition, $correspondenceComponents)];
            }
            PropositionMeasurement::query()->upsert(
                [[
                    ...$componentAttributes,
                    'chirality_direction' => $snapshot?->chirality_direction ?? $this->resolvedChiralityDirection($proposition, $chirality)->value,
                    'proposition_id' => $proposition->getKey(),
                    'correspondence' => $correspondence,
                    'chirality' => $chirality,
                    'closure' => $closure,
                    'calculated_at' => $calculatedAt ?? now(),
                    'calculator_version' => LineageMeasurementService::VERSION,
                ]],
                ['proposition_id'],
                [
                    ...array_keys(array_diff_key($componentAttributes, ['chirality_direction' => true])),
                    'chirality_direction',
                    'correspondence',
                    'chirality',
                    'closure',
                    'calculated_at',
                    'calculator_version',
                ],
            );

            $measurement = PropositionMeasurement::query()
                ->findOrFail($proposition->getKey());

            if ($snapshot === null && $chirality !== null) {
                $history = $measurement->only(['chirality', 'chirality_direction']);
                if ($chiralityComponents !== null) {
                    $history = [...$history, ...$measurement->only(['orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction'])];
                }
                DB::table('chirality_observations')->upsert([['proposition_id' => $proposition->id, ...$history]],
                    ['proposition_id'], array_keys($history));
            }
            $proposition->setRelation('measurement', $measurement);

            return $measurement;
        });
    }

    /** Read-only diagnostic attributes for the normal field publication pathway. */
    public function chiralityAttributes(Proposition $position, float $value, ?ChiralityValues $components, ?ParticipationContext $context = null): array
    {
        $attributes = $components === null ? ($position->measurement?->only([
            'orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction']) ?? [])
            : $this->componentAttributes($position, $components, $context);

        return [...array_fill_keys(['orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction'], null),
            ...$attributes, 'chirality_direction' => $this->resolvedChiralityDirection($position, $value, $context)->value];
    }

    /** Published Correspondence diagnostics, including canonical retained directions. */
    public function correspondenceAttributes(Proposition $position, ?CorrespondenceValues $components, ?ParticipationContext $context = null): array
    {
        $attributes = $components === null ? ($position->measurement?->only([
            'shape_topology', 'phase_profile', 'shape_topology_direction', 'phase_profile_direction']) ?? [])
            : $this->correspondenceComponentAttributes($position, $components, $context);

        return [...array_fill_keys(['shape_topology', 'phase_profile', 'shape_topology_direction', 'phase_profile_direction'], null), ...$attributes];
    }

    private function componentAttributes(Proposition $proposition, ChiralityValues $values, ?ParticipationContext $context = null): array
    {
        if ($canonical = app(CanonicalMeasurementService::class)->for($proposition, $context)) {
            return $canonical->metadata();
        }
        $prior = $this->priorChiralityObservation($proposition, $context)
            ?? ($context === null ? PropositionMeasurement::find($proposition->id) : $context->propositions->get($proposition->id)?->measurement);
        $entry = $proposition->phase->cycle->entry_committal_id;
        $previous = $entry === null ? null : ($context === null ? CommitmentSnapshot::find($entry) : $context->snapshots->firstWhere('proposition_id', $entry));

        return ['orientation' => $values->orientation, 'attestation_cadence' => $values->cadence,
            ...$values->resolveDirections(
                ObservableDirection::from($prior?->orientation_direction ?? $previous?->orientation_direction ?? 'negative'),
                ObservableDirection::from($prior?->attestation_cadence_direction ?? $previous?->attestation_cadence_direction ?? 'positive'),
            )];
    }

    /** Match Chirality provenance independently for each local diagnostic. */
    private function correspondenceComponentAttributes(Proposition $proposition, CorrespondenceValues $values, ?ParticipationContext $context = null): array
    {
        $prior = $context === null ? PropositionMeasurement::find($proposition->id) : $context->propositions->get($proposition->id)?->measurement;
        $entry = $proposition->phase->cycle->entry_committal_id;
        $previous = $entry === null ? null : ($context === null ? CommitmentSnapshot::find($entry) : $context->snapshots->firstWhere('proposition_id', $entry));

        return ['shape_topology' => $values->shapeTopology, 'phase_profile' => $values->phaseProfile,
            ...$values->resolveDirections(
                ObservableDirection::from($prior?->shape_topology_direction ?? $previous?->shape_topology_direction ?? 'positive'),
                ObservableDirection::from($prior?->phase_profile_direction ?? $previous?->phase_profile_direction ?? 'positive'),
            )];
    }

    /** Correspondence has its own hysteresis; Attestation polarity is unrelated. */
    public function correspondenceDirectionFor(Proposition $position, ?ParticipationContext $context = null): ObservableDirection
    {
        $seen = [];
        $substrateId = $position->phase->cycle->participant->substrate_id;
        while ($position !== null) {
            if ($canonical = app(CanonicalMeasurementService::class)->for($position, $context)) {
                return ObservableDirection::from($canonical->correspondence_direction);
            }
            if (isset($seen[$position->id]) || $position->phase->cycle->participant->substrate_id !== $substrateId) {
                throw new \DomainException('Correspondence lineage must be acyclic and within one substrate.');
            }
            $seen[$position->id] = true;
            $position = $context?->proposition($position) ?? $position;
            if ($context === null) {
                $position->load('measurement');
            }
            $value = $position->measurement?->correspondence;
            if ($value !== null && $value !== 0.0) {
                return ObservableDirection::after($value);
            }
            $retained = $context !== null ? $position->correspondence_direction : Proposition::query()->whereKey($position->id)->value('correspondence_direction');
            if ($retained !== null) {
                return ObservableDirection::from($retained);
            }
            $position = $context !== null
                ? $context->nodes($context->parents->get($position->id, collect())->sortBy([['created_at', 'asc'], ['id', 'asc']])->pluck('source_proposition_id'))->first()
                : $position->parents()->orderBy('supersessions.created_at')->orderBy('supersessions.id')->first();
        }

        return ObservableDirection::defaultFor('correspondence');
    }

    public function chiralityDirectionFor(Proposition $position, ?ParticipationContext $context = null): ObservableDirection
    {
        $position = $context?->proposition($position) ?? $position;

        return $this->resolvedChiralityDirection($position, $position->measurement?->chirality, $context);
    }

    private function resolvedChiralityDirection(Proposition $position, ?float $value, ?ParticipationContext $context = null): ObservableDirection
    {
        if ($canonical = app(CanonicalMeasurementService::class)->for($position, $context)) {
            return ObservableDirection::from($canonical->chirality_direction);
        }
        if ($value !== null && $value !== 0.0) {
            return ObservableDirection::after($value);
        }
        $prior = $this->priorChiralityObservation($position, $context)
            ?? ($context === null ? PropositionMeasurement::find($position->id) : $position->measurement);
        $entry = $position->phase->cycle->entry_committal_id;
        $snapshot = $entry === null ? null : ($context === null ? CommitmentSnapshot::find($entry) : $context->snapshots->firstWhere('proposition_id', $entry));
        $previous = $prior?->chirality_direction ?? $snapshot?->chirality_direction;

        return ObservableDirection::after($value ?? 0.0, $previous === null
            ? ObservableDirection::defaultFor('chirality') : ObservableDirection::from($previous));
    }

    private function priorChiralityObservation(Proposition $position, ?ParticipationContext $context): ?object
    {
        if ($context === null) {
            return DB::table('chirality_observations')->where('proposition_id', $position->id)->first();
        }

        return $context->remember('chirality:retained-observations', fn () => DB::table('chirality_observations')
            ->whereIn('proposition_id', $context->propositions->keys())->get()->keyBy('proposition_id'))->get($position->id);
    }

    private function assertMeasurement(string $name, ?float $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_finite($value) || $value < -1 || $value > 1) {
            throw new InvalidArgumentException(
                "{$name} must be between -1 and 1, or null."
            );
        }
    }
}
