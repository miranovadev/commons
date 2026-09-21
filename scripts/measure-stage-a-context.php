<?php

/** Offline projection of the existing replay captures; no inference/provider calls. */
require __DIR__.'/../vendor/autoload.php';

use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptLayout;

$captures = array_slice($argv, 1) ?: [
    __DIR__.'/../tests/Replay/Golden/solo-domain-natural.jsonl.xz',
    __DIR__.'/../tests/Replay/Golden/collective-domain-natural.jsonl.xz',
];
$encode = fn ($value) => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$bytes = fn ($value) => strlen($encode($value));
$serializer = new ParticipantContextSerializer;
$layout = new ParticipantPromptLayout;
$rows = [];
foreach ($captures as $capture) {
    $process = proc_open(['xz', '-dc', $capture], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot open capture.');
    }
    while (($line = fgets($pipes[1])) !== false) {
        $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        $state = $row['before'] ?? null;
        if (($state['status'] ?? null) !== 'ready') {
            continue;
        }
        // Captures already contain the canonical pre-Stage-A receipt representation.
        $before = $serializer->inference($state);
        $before['plan']['receipts'] = $state['plan']['receipts'] ?? [];
        $after = $serializer->inference($state);
        $mediate = $state['plan']['phase']['kind'] === 'mediate';
        if ($mediate) {
            // Capture contract is anchor-first. Live anchor selection is tested in DB fixtures.
            $after['plan']['read_options'] = array_slice($after['plan']['read_options'], 0, 1);
        }
        $old = $layout->make($before);
        $new = $layout->make($after);
        if ($old['evidence'] !== $new['evidence'] || $before['plan']['schema'] !== $after['plan']['schema']) {
            throw new RuntimeException('Loaded evidence or action schema changed.');
        }
        foreach ($after['plan']['receipts'] as $receipt) {
            if ($receipt['status'] !== 'loaded' && array_key_exists('evidence', $receipt)) {
                throw new RuntimeException('Unread evidence leaked.');
            }
        }
        $unloaded = fn ($packet) => array_values(array_filter($packet['plan']['receipts'], fn ($r) => $r['status'] !== 'loaded'));
        $rows[] = [
            'capture' => basename($capture), 'step' => $row['step'] ?? null, 'phase' => $state['plan']['phase']['kind'],
            'catalogue_before' => $bytes($unloaded($before)), 'catalogue_after' => $bytes($unloaded($after)),
            'loaded_evidence' => strlen($new['evidence']),
            'packet_before' => strlen($old['evidence']) + strlen($old['user']),
            'packet_after' => strlen($new['evidence']) + strlen($new['user']),
            'reads_before' => count($before['plan']['read_options']), 'reads_after' => count($after['plan']['read_options']),
            'counterparts' => $mediate ? count($state['plan']['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum'] ?? []) : null,
        ];
    }
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Capture decompression failed.');
    }
}
$means = [];
foreach (['catalogue_before', 'catalogue_after', 'loaded_evidence', 'packet_before', 'packet_after'] as $key) {
    $means[$key] = round(array_sum(array_column($rows, $key)) / max(1, count($rows)), 2);
}
$mediate = array_values(array_filter($rows, fn ($r) => $r['phase'] === 'mediate'));
$representative = $mediate;
usort($representative, fn ($a, $b) => $b['catalogue_before'] <=> $a['catalogue_before']);
echo json_encode([
    'method' => 'Offline projection of canonical replay captures, not fresh production dumps. Catalogue excludes personal loaded state. Packet bytes are evidence+dynamic JSON, excluding system/schema transport overhead. Capture data cannot reconstruct new attachment references; database tests cover those.',
    'packets' => count($rows), 'means_bytes' => $means,
    'mediate_packets' => count($mediate),
    'mediate_read_totals' => ['before' => array_sum(array_column($mediate, 'reads_before')), 'after' => array_sum(array_column($mediate, 'reads_after'))],
    'loaded_evidence_identical' => true, 'schemas_and_counterparts_identical' => true, 'unread_evidence_present_after' => false,
    'representative_mediate' => $representative[0] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
