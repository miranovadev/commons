<?php

namespace Diagnostics\Maturity;

use App\Enums\ContextDomain;
use App\Enums\ObservableDirection;
use App\Enums\SupersessionStrength;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use App\Services\ChiralityGateService;
use App\Services\CommitRegionPolarityService;
use App\Services\Observables\CardinalMorphologyService;
use App\Services\Observables\ChiralityCalculator;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\ParticipationService;
use App\Services\ProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Genuine operations with the explicitly authorized candidate diagnostic services. */
class MaturityTrajectoryTest extends TestCase
{
    use RefreshDatabase;

    public static function labels(): array
    {
        return ['support' => ['support'], 'refute' => ['refute']];
    }

    #[DataProvider('labels')]
    public function test_bounded_aligned_trajectory(string $label): void
    {
        $this->app->singleton(EventOrder::class);
        $this->app->bind(LocalMeasurementService::class, CandidateLocalMeasurements::class);
        $order = app(EventOrder::class);
        Cycle::created(function ($cycle) use ($order) {
            $order->cycles[$cycle->id] = ++$order->clock;
        });
        Attestation::created(function ($act) use ($order) {
            $order->acts[$act->id] = ++$order->clock;
        });
        // The user fixes this constitutional boundary independently of the
        // numeric candidate Origin (C=1, X=0, K=-1, T=0). No ordinary transition override.
        CommitmentSnapshot::creating(function ($snapshot) {
            if ($snapshot->calculator_version === 'origin-bootstrap-v1') {
                $snapshot->direction_after = ContextDomain::Settlement;
            }
        });
        $sub = (new Substrate)->forceFill(['slug' => 'maturity-diagnostic', 'title' => 'Maturity diagnostic', 'visibility' => 'public', 'configuration' => []]);
        $sub->save();
        $actor = (new Participant)->forceFill(['substrate_id' => $sub->id, 'callsign' => 'author', 'max_read_count' => 25]);
        $actor->save();
        $service = app(ParticipationService::class);
        $state = $service->enter($actor, ['statement' => 'Origin', 'attestation' => ['direction' => $label]]);
        $origin = Proposition::findOrFail($state['current_proposition_id']);
        $originData = $origin->commitmentSnapshot->only(['correspondence', 'chirality', 'chirality_direction', 'orientation', 'orientation_direction', 'attestation_cadence', 'attestation_cadence_direction', 'closure', 'tension', 'direction_before', 'direction_after']);
        $this->assertSame(0.0, $origin->commitmentSnapshot->chirality);
        $this->assertSame('positive', $origin->commitmentSnapshot->chirality_direction);
        $this->assertNull($origin->commitmentSnapshot->attestation_cadence);
        $this->assertNull($origin->commitmentSnapshot->attestation_cadence_direction);
        $this->assertSame('settlement', $actor->fresh()->cycles()->latest('sequence')->first()->contextHorizon->domain->value);
        $rows = [];
        $actionsTaken = [];
        $bias = [];
        $commits = 0;
        $actIndex = 0;
        $stop = 'action_bound';
        $crossed = false;
        $save = function () use (&$rows, &$actionsTaken, &$bias, &$commits, &$stop, &$crossed, $originData, $label) {
            file_put_contents(__DIR__.'/result-'.$label.'.json', json_encode(['origin_label' => $label,
                'schedule' => 'prefer effective +1 in the target region frame, otherwise first legal stance; one idle entry before ordinary Attestations 2 and 4; existing action priority; up to 10 ordinary Commits or 1000 actions; analytical stop after 3-Commit verification',
                'origin' => $originData, 'stop' => $stop, 'commits' => $commits, 'crossed' => $crossed,
                'prospective_commits' => $rows, 'bias_observations' => $bias, 'actions' => $actionsTaken], JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION)."\n");
        };
        try {
            for ($step = 0; $step < 1000; $step++) {
                $state = $service->stateFor($actor);
                if ($state['status'] !== 'ready') {
                    $stop = $state['status'];
                    break;
                }
                $plan = $state['plan'];
                $phase = Phase::findOrFail($plan['phase']['id']);
                $gate = Proposition::findOrFail($plan['gate_id']);
                $definitions = $plan['schema']['$defs']['actions'];
                if ($phase->kind->value === 6) {
                    $candidate = app(LocalMeasurementService::class)->diagnose(app(ContextLineageComparisonService::class)->for($gate));
                    $gateResult = app(ChiralityGateService::class)->evaluate($gate);
                    $bias[] = ['step' => $step, 'domain' => $phase->cycle->contextHorizon->domain->value,
                        'observation' => $candidate, 'component_agreement' => $candidate['attestation_cadence_mature'] ? $gateResult['admissible'] : null,
                        'component_filter_allows' => $gateResult['admissible'], 'available_actions' => array_keys($definitions)];
                    if ($crossed && $candidate['attestation_cadence_mature']) {
                        $stop = 'mature_bias_observed';
                        break;
                    }
                }
                if ($phase->kind->value === 7) {
                    $rows[] = $this->preview($gate, $phase, $commits + 1) + ['step' => $step, 'commit_action_available' => isset($definitions['proposition.commit'])];
                }
                $action = collect(['proposition.commit', 'proposition.reclassify', 'proposition.variant', 'proposition.reconcile',
                    'proposition.traverse', 'attestation.create', 'instrument.execute', 'participant.rest'])
                    ->first(fn ($a) => isset($definitions[$a]));
                if ($action === null) {
                    $stop = 'no_action';
                    break;
                }
                $definition = $definitions[$action];
                $input = ['action' => $action];
                foreach (['proposition_id', 'other_proposition_id', 'receipt_id'] as $key) {
                    if (isset($definition['properties'][$key]['enum'])) {
                        $input[$key] = $definition['properties'][$key]['enum'][0];
                    }
                }
                if ($action === 'proposition.variant') {
                    $input['statement'] = 'Development '.$step;
                }
                if ($action === 'instrument.execute') {
                    $input['instrument'] = ['kind' => 'hyperlink', 'reference' => 'https://example.test/evidence/'.$step];
                }
                if ($action === 'attestation.create') {
                    if (in_array($actIndex, [1, 3], true)) {
                        $idle = (new Participant)->forceFill(['substrate_id' => $sub->id, 'callsign' => 'idle-'.$actIndex]);
                        $idle->save();
                        $service->enter($idle);
                    }
                    $choices = [];
                    foreach ($definition['anyOf'] as $choice) {
                        foreach ($choice['properties']['proposition_id']['enum'] as $targetId) {
                            $target = Proposition::findOrFail($targetId);
                            $polarity = app(CommitRegionPolarityService::class);
                            $seed = $polarity->seedFor($target);
                            $support = $seed === null ? 1 : ($polarity->forSeed($seed)['support'] === ObservableDirection::Positive ? 1 : -1);
                            foreach ($choice['properties']['direction']['enum'] as $direction) {
                                $choices[] = ['id' => $targetId, 'direction' => $direction, 'effective_sign' => $direction === 'support' ? $support : -$support];
                            }
                        }
                    }
                    $selected = collect($choices)->firstWhere('effective_sign', 1) ?? $choices[0];
                    $input['proposition_id'] = $selected['id'];
                    $input['direction'] = $selected['direction'];
                    $actIndex++;
                }
                $actionsTaken[] = ['step' => $step, 'cycle' => $state['cycle']['sequence'], 'phase' => $phase->kind->value, 'action' => $action,
                    'domain' => $phase->cycle->contextHorizon->domain->value, 'direction' => $input['direction'] ?? null, 'effective_sign' => $action === 'attestation.create' ? $selected['effective_sign'] : null];
                $service->contribute($actor, $phase->id, $input);
                if ($action === 'proposition.commit') {
                    $commits++;
                    $commit = $actor->fresh()->position;
                    $snapshot = $commit->commitmentSnapshot;
                    $rows[array_key_last($rows)]['actual'] = $snapshot->only(['correspondence', 'chirality', 'chirality_direction', 'orientation', 'orientation_direction',
                        'attestation_cadence', 'attestation_cadence_direction', 'closure', 'tension', 'direction_before', 'direction_after']);
                    $rows[array_key_last($rows)]['actual_proposition_id'] = $commit->id;
                    foreach (['correspondence', 'chirality', 'closure'] as $field) {
                        $this->assertSame($rows[array_key_last($rows)][$field], $snapshot->$field);
                    }
                    $this->assertNull($snapshot->attestation_cadence);
                    if ($snapshot->direction_before === ContextDomain::Settlement && $snapshot->direction_after === ContextDomain::Emergence) {
                        $crossed = true;
                    }
                    fwrite(STDERR, "\n$label Commit $commits: ".json_encode($rows[array_key_last($rows)]['actual'])."\n");
                    if ($commits >= 3 && ! $crossed) {
                        $stop = 'structural_bound_verified_three_commits';
                        $save();
                        break;
                    }
                }
                $save();
            }
        } catch (SemanticBoundary $e) {
            $stop = $e->getMessage();
        } finally {
            $save();
        }
        $this->assertGreaterThan(0, count($actionsTaken));
        // Verify observer occurrence order agrees with contribution-ledger act order.
        $last = 0;
        foreach (SubstrateLedgerEntry::where('ledgerable_type', Attestation::class)->where('substrate_id', $sub->id)->orderBy('sequence')->get() as $entry) {
            $this->assertGreaterThan($last, $order->acts[$entry->ledgerable_id]);
            $last = $order->acts[$entry->ledgerable_id];
        }
    }

    private function preview(Proposition $gate, Phase $phase, int $ordinal): array
    {
        $comparison = app(ContextLineageComparisonService::class)->prospectiveCommit($gate, $phase);
        $candidate = $comparison->current->propositions->last();
        $values = app(LineageMeasurementService::class)->calculate($candidate, $comparison);
        $details = app(LocalMeasurementService::class)->diagnose($comparison);
        $closure = $values->closure()->resolve();
        $entry = $phase->cycle->entryCommittal->commitmentSnapshot;
        $directions = $values->chiralityComponents->resolveDirections(ObservableDirection::from($entry->orientation_direction ?? 'negative'), ObservableDirection::from($entry->attestation_cadence_direction ?? 'positive'));
        $chiralityDirection = ObservableDirection::after($values->chirality, ObservableDirection::from($entry->chirality_direction ?? 'positive'))->value;
        $field = app(ProjectionService::class)->field($values->observables());
        $v2Total = $mass = 0.0;
        foreach (app(LineageMeasurementService::class)->positions($candidate) as [$node,$distance]) {
            $cmp = $distance === 0 ? $comparison : app(ContextLineageComparisonService::class)->for($node);
            $v2 = app(ChiralityCalculator::class)->components($cmp);
            $weight = LineageMeasurementService::attenuation($distance);
            $v2Total += $v2->average() * $weight;
            $mass += $weight;
        }
        $v2 = app(ChiralityCalculator::class)->components($comparison);
        $morphology = app(CardinalMorphologyService::class)->current($candidate, $comparison->current->horizon);
        $singleParent = $morphology->supersessions->groupBy('target_proposition_id')->every(fn ($edges) => $edges->pluck('source_proposition_id')->unique()->count() === 1);
        $required = -($values->chirality + $values->closure()->average());

        return ['ordinal' => $ordinal, 'current_domain' => $phase->cycle->contextHorizon->domain->value,
            'morphology' => ['head' => $candidate->id, 'positions' => collect($morphology->positions)->map(fn ($p) => ['depth' => $p->depth, 'lateral' => $p->lateral])->all(),
                'nodes' => $morphology->propositions->map(fn ($p) => ['id' => $p->id, 'register' => $p->register, 'tier' => $p->tier])->values()->all(),
                'edges' => $morphology->supersessions->map(fn ($e) => ['source' => $e->source_proposition_id, 'target' => $e->target_proposition_id, 'strength' => SupersessionStrength::for($e->type)->value])->values()->all()],
            'morphology_single_parent' => $singleParent, 'morphology_nodes' => $morphology->propositions->count(),
            'morphology_edges' => $morphology->supersessions->count(), 'morphology_primary_depth' => $morphology->primaryDepth,
            'entry_committal_id' => $phase->cycle->entry_committal_id, 'correspondence' => $values->correspondence,
            'shape_topology' => $values->correspondenceComponents->shapeTopology, 'cardinal_position' => $values->correspondenceComponents->cardinalPosition,
            ...$details, ...$directions, 'local_chirality' => $values->chiralityComponents->average(),
            'chirality' => $values->chirality, 'chirality_direction' => $chiralityDirection,
            'closure' => $closure['closure'], 'closure_observation' => $closure, 'tension' => $field['tension'],
            'required_correspondence' => $required, 'correspondence_margin' => round($values->correspondence - $required, 7),
            'resulting_domain' => ContextDomain::after($field['tension'], $phase->cycle->contextHorizon->domain)->value,
            'v2' => ['orientation' => $v2->orientation, 'attestation_cadence' => $v2->cadence, 'local_chirality' => $v2->average(), 'attenuated_chirality' => round($v2Total / $mass,7)]];
    }
}
