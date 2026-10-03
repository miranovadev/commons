<?php
namespace Diagnostics\NaturalRhythm;

use App\Domain\Observables\LineageComparison;

/** Pure alternative calculation; never registered as the application's calculator. */
final class OccupiedLocalMeasurements extends CandidateLocalMeasurements
{
    public static function occupied(array $history): array
    {
        $positions=array_map(fn($at)=>count(array_filter($history['represented_cycle_order'],fn($entry)=>$entry<$at)),$history['act_order']);
        $occupied=array_values(array_unique($positions));
        $intervals=[];
        for($i=1;$i<count($occupied);$i++)$intervals[]=$occupied[$i]-$occupied[$i-1];
        $mean=count($intervals)?array_sum($intervals)/count($intervals):null;
        $sd=$mean===null?null:sqrt(array_sum(array_map(fn($v)=>($v-$mean)**2,$intervals))/count($intervals));
        return ['act_positions'=>$positions,'occupied_positions'=>$occupied,'intervals'=>$intervals,
            'usable_interval_count'=>count($intervals),'mean'=>$mean,'stddev'=>$sd,'cv'=>$mean===null||$mean==0?null:$sd/$mean,
            'status'=>$mean===null?'no_intervals':($mean==0?'undefined_zero_mean':(count($intervals)===1?'singleton_unresolved':'empirical_cv'))];
    }

    public function diagnose(LineageComparison $comparison):array
    {
        $a=parent::diagnose($comparison);
        $a['original_history']=$a['current_history'];
        $a['current_history']=[...$a['current_history'],...self::occupied($a['current_history'])];
        if($comparison->previous!==null && (int)$comparison->previous->horizon->sequence===1){
            $h=$a['current_history'];
            $a['attestation_cadence_mature']=count($h['intervals'])>=2;
            $a['cadence_status']=$a['attestation_cadence_mature']?'formation_cv':'formation_immature';
            if($a['attestation_cadence_mature']&&$h['cv']===null)throw new SemanticBoundary('Undefined occupied-position CV');
            $a['attestation_cadence']=$a['attestation_cadence_mature']?round(max(-1,min(1,(1-$h['cv'])/(1+$h['cv']))),7):0.0;
            $a['cadence_contribution']=$a['attestation_cadence'];
            $a['component_gate']=$a['attestation_cadence_mature']?'direction_agreement':'immature_direction_hysteresis';
        }
        return $a;
    }
}
