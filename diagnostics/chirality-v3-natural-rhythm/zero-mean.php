<?php
require __DIR__.'/../../vendor/autoload.php';

use App\Services\Observables\ClosureCalculator;

$rows = [];
foreach ([[],[0],[0,0],[2,2],[0,1]] as $xs) {
    $row = ['intervals' => $xs];
    try {
        $mean = array_sum($xs) / count($xs);
        $sd = sqrt(array_sum(array_map(fn ($x) => ($x-$mean)**2, $xs)) / count($xs));
        $row['unguarded_cv'] = $sd / $mean;
    } catch (Throwable $e) { $row['unguarded_error'] = get_class($e).': '.$e->getMessage(); }
    try { $row['production_closure_cv'] = ClosureCalculator::coefficientOfVariation($xs); }
    catch (Throwable $e) { $row['production_closure_guard'] = get_class($e).': '.$e->getMessage(); }
    $mean = count($xs) ? array_sum($xs)/count($xs) : null;
    $row['formation_interval_cv'] = $mean === null || $mean == 0 ? null
        : sqrt(array_sum(array_map(fn ($x) => ($x-$mean)**2, $xs))/count($xs))/$mean;
    $row['formation_outcome'] = count($xs)<2 ? 'immature; contribution 0; maturity false'
        : ($row['formation_interval_cv'] === null ? 'SemanticBoundary: zero mean; no assigned value' : 'mature');
    $rows[] = $row;
}
file_put_contents(__DIR__.'/zero-mean-result.json', json_encode(['php'=>PHP_VERSION,'rows'=>$rows], JSON_PRETTY_PRINT)."\n");
echo json_encode(['php'=>PHP_VERSION,'rows'=>$rows], JSON_PRETTY_PRINT)."\n";
