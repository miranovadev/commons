<?php
namespace Diagnostics\NaturalRhythm;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\ObservableValues;
use App\Enums\ContextDomain;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\AttestationEvidenceService;
use App\Services\BiasGateService;
use App\Services\BiasReceiptAuthorityService;
use App\Services\ChiralityGateService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\CorrespondenceGateService;
use App\Services\HorizonReceiptService;
use App\Services\PhaseGenerationService;
use App\Services\PhasePositionService;
use App\Services\PropositionStateService;
use App\Services\ProjectionService;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use Illuminate\Support\Facades\DB;

final class BoundaryInspector
{
    public static function inspect(Participant $actor, array $state): array
    {
        $phase = Phase::findOrFail($state['plan']['phase']['id']);
        $gate = Proposition::findOrFail($state['plan']['gate_id']);
        $comparison = app(ContextLineageComparisonService::class)->for($gate);
        $values = app(LineageMeasurementService::class)->calculate($gate, $comparison);
        $details = app(LocalMeasurementService::class)->diagnose($comparison);
        $field = app(ProjectionService::class)->field($values->observables());
        $grounding = app(CommitmentAdmissibilityService::class)->grounding($gate);
        $chiralityGate = app(ChiralityGateService::class)->evaluate($gate);
        $correspondenceGate = app(CorrespondenceGateService::class)->evaluate($gate);
        $directions = $gate->fresh()->measurement?->toArray();
        $generation = [];
        foreach (config('phases.'.$phase->kind->value.'.actions') as $action) {
            $generation[$action] = app(PhaseGenerationService::class)->available($phase, $action);
        }
        $bias = null;
        if ($phase->kind->value === 6) {
            $bias = [
                'regional_candidates'=>app(BiasGateService::class)->populationFor($phase)->pluck('id')->all(),
                'direction_eligible'=>app(BiasGateService::class)->forPhase($phase)->pluck('id')->all(),
                'operational'=>app(BiasGateService::class)->operationalFor($phase)->pluck('id')->all(),
            ];
            $ids = collect([$gate->id])->merge($bias['operational'])->unique();
            $bias['sources'] = $ids->map(fn($id)=>self::source( Proposition::findOrFail($id), $phase))->all();
        }
        $parts = [];
        $mass = $total = $headTotal = $allTotal = 0.0;
        foreach (app(LineageMeasurementService::class)->positions($gate) as [$node,$distance]) {
            $cmp = $distance === 0 ? $comparison : app(ContextLineageComparisonService::class)->for($node);
            $local = app(LocalMeasurementService::class)->chiralityComponents($node, $cmp);
            $weight = LineageMeasurementService::attenuation($distance);
            $substituted = new ChiralityValues($local->orientation, 1.0);
            $mass += $weight;
            $total += $local->average()*$weight;
            $headTotal += ($distance === 0 ? $substituted->average() : $local->average())*$weight;
            $allTotal += ($cmp->previous !== null && (int)$cmp->previous->horizon->sequence === 1 ? $substituted->average() : $local->average())*$weight;
            $parts[] = ['id'=>$node->id,'distance'=>$distance,'weight'=>$weight,'orientation'=>$local->orientation,
                'cadence'=>$local->cadence,'local_chirality'=>$local->average()];
        }
        // Pure value objects and production projection only. Neither the observed
        // components nor the stored measurement/Phase/Gate is assigned a new value.
        $counterfactual = function(float $chi) use ($values,$phase) {
            $chi = round($chi,7);
            $f = app(ProjectionService::class)->field(new ObservableValues($values->correspondence,$chi,$values->closure()->average()));
            return ['chirality'=>$chi,...$f,'sign_implied_domain'=>ContextDomain::after($f['tension'],$phase->cycle->contextHorizon->domain)->value];
        };
        return [
            'cycle'=>$phase->cycle->sequence,'phase'=>$phase->kind->value,'phase_id'=>$phase->id,
            'domain'=>$phase->cycle->contextHorizon->domain->value,'gate_id'=>$gate->id,
            'gate_produced_at_phase'=>$gate->phase->kind->value,'gate_register'=>$gate->register,'gate_tier'=>$gate->tier,
            'field'=>['scope'=>'existing expressive/committal gate; NOT prospective Commit',
                'correspondence'=>$values->correspondence,'correspondence_components'=>$values->correspondenceComponents,
                ...$details,'local_chirality'=>$values->chiralityComponents->average(),'chirality'=>$values->chirality,
                'closure'=>$values->closure()->average(),'closure_details'=>$values->closure()->resolve(),...$field,
                'sign_implied_domain'=>ContextDomain::after($field['tension'],$phase->cycle->contextHorizon->domain)->value,
                'stored_measurement'=>$directions,'attenuation_mass'=>$mass,'lineage_parts'=>$parts],
            'lifecycle'=>['available_actions'=>array_keys($state['plan']['schema']['$defs']['actions']),
                'state_reason'=>$state['reason']??null,'plan'=>$state['plan'],'grounding'=>$grounding,
                'effective_attestations'=>app(PropositionStateService::class)->attestations(collect([$gate]))->map(fn($a)=>$a->only(['id','proposition_id','direction']))->all(),
                'evidence_receipt_ids'=>app(PropositionStateService::class)->evidenceReceipts($gate)->pluck('id')->all(),
                'ordinary_receipt_ids'=>app(HorizonReceiptService::class)->ordinaryAvailable($phase)->pluck('id')->all(),
                'generation'=>$generation,'accepts_gate'=>app(PhasePositionService::class)->acceptsGate($phase,$gate),
                'chirality_gate'=>$chiralityGate,'correspondence_gate'=>$correspondenceGate,
                'reclassification_source'=>in_array($phase->kind->value,[3,6]) ? self::source($gate,$phase) : null,
                'bias'=>$bias,
                'commit'=> $phase->kind->value===7 ? app(CommitmentAdmissibilityService::class)->evaluate($gate,$phase) : ['admissible'=>false,'reason'=>'not_order_phase']],
            'counterfactuals'=>[
                'lifecycle_satisfied_existing_gate_sign'=>ContextDomain::after($field['tension'],$phase->cycle->contextHorizon->domain)->value,
                'epsilon_tension'=>0.0000001,
                'epsilon_sign_implied_domain'=>ContextDomain::after(0.0000001,$phase->cycle->contextHorizon->domain)->value,
                'cadence_plus_one_head_only'=>$counterfactual($headTotal/$mass),
                'cadence_plus_one_all_first_settlement_nodes_sensitivity'=>$counterfactual($allTotal/$mass),
                'baseline_chirality_reconstructed'=>round($total/$mass,7),
                'note'=>'Lifecycle unchanged; sign labels do not assert Commit eligibility or forecast a new topology. +1 replaces Cadence only in value objects.'],
        ];
    }

    private static function source(Proposition $gate, Phase $phase): array
    {
        $grounding = app(CommitmentAdmissibilityService::class)->grounding($gate);
        $receipts = $phase->kind->value===6
            ? app(BiasReceiptAuthorityService::class)->receiptsFor($phase,$gate)
            : app(HorizonReceiptService::class)->ordinaryAvailable($phase);
        $existing = DB::table('reclassification_results')->where('source_proposition_id',$gate->id)->where('register',$phase->write_register)->exists();
        return ['gate_id'=>$gate->id,'grounding'=>$grounding,
            'existing_classification_in_write_register'=>$existing,'authorized_receipt_ids'=>$receipts->pluck('id')->all(),
            'accepts_gate'=>app(PhasePositionService::class)->acceptsGate($phase,$gate),
            'chirality_gate'=>app(ChiralityGateService::class)->evaluate($gate),
            'lineage_evidence_direction'=>app(AttestationEvidenceService::class)->lineageDirectionFor($gate)?->value,
            'generation_reclassify'=>app(PhaseGenerationService::class)->available($phase,'proposition.reclassify',null,$gate)];
    }
}
