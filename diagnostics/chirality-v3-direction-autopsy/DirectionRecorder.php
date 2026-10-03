<?php
namespace Diagnostics\NaturalRhythm;

use App\Models\Proposition;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LocalMeasurementService;
use Illuminate\Support\Facades\DB;

/** Observes ordinary persistence; never calls a measurement write or changes state. */
final class DirectionRecorder
{
    public static string $stage='origin_and_entry';
    private static array $writes=[];
    private static array $changes=[];
    private static array $previous=[];
    public static function start():void
    {
        self::$writes=[]; self::$changes=[]; self::$previous=[];
        DB::listen(function($query) {
            if(preg_match('/^\s*(insert|update)/i',$query->sql) && preg_match('/chirality_observations|proposition_measurements|commitment_snapshots/',$query->sql))
                self::$writes[]=['stage'=>self::$stage,'sql'=>$query->sql,'bindings'=>$query->bindings];
        });
    }
    public static function capture():void
    {
        $count=count(self::$writes);
        foreach(['chirality_observations','proposition_measurements','commitment_snapshots'] as $table) {
            foreach(DB::table($table)->get() as $record) {
                $row=(array)$record;
                $id=$row['proposition_id'];
                $current=array_intersect_key($row,array_flip(['proposition_id','orientation','orientation_direction','attestation_cadence','attestation_cadence_direction','chirality','chirality_direction']));
                $key=$table.':'.$id;
                if((self::$previous[$key]??null)===$current)continue;
                $p=Proposition::findOrFail($id);
                $details=app(LocalMeasurementService::class)->diagnose(app(ContextLineageComparisonService::class)->for($p));
                self::$changes[]=['stage'=>self::$stage,'table'=>$table,'proposition_id'=>$id,'producing_phase'=>$p->phase->kind->value,'statement'=>$p->statement,'previous'=>self::$previous[$key]??null,'current'=>$current,'observation'=>$details];
                self::$previous[$key]=$current;
            }
        }
        if(count(self::$writes)!==$count)throw new \RuntimeException('Direction capture unexpectedly wrote measurements');
    }
    public static function save():void
    {
        file_put_contents(__DIR__.'/direction-records.json',json_encode(['changes'=>self::$changes,'writes'=>self::$writes],JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION));
    }
}
