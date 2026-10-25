<?php

namespace App\Services;

use App\Models\EvidenceExecution;
use App\Models\Receipt;
use Illuminate\Support\Arr;

/** Persisted historical metadata; independent of today's Receipt availability. */
final class PreparationHistoryService
{
    public function summary(EvidenceExecution $execution): array
    {
        return [
            'id' => $execution->id,
            'participant' => $execution->cycle->participant->only(['id', 'callsign']),
            'originating_gate_id' => $execution->proposition_id,
            'cycle' => $execution->cycle->only(['id', 'sequence']),
            'phase' => ['id' => $execution->phase_id, 'number' => $execution->phase->kind->value,
                'name' => strtolower($execution->phase->kind->name)],
            'purpose' => $execution->input['purpose'] ?? null,
            'status' => $execution->status,
            'outcome' => $execution->outcome,
            'resulting_proposition_id' => $execution->resulting_proposition_id,
            'receipt_id' => $execution->receipt?->id,
            'created_at' => $execution->created_at?->toISOString(),
            'started_at' => $execution->started_at?->toISOString(),
            'presented_at' => $execution->presented_at?->toISOString(),
            'resolved_at' => ($execution->completed_at ?? $execution->failed_at)?->toISOString(),
        ];
    }

    public function detail(EvidenceExecution $execution): array
    {
        $selections = $execution->input['artifacts'] ?? [];
        $receipts = Receipt::whereIn('id', array_column($selections, 'receipt_id'))
            ->whereHas('evidenceExecution.cycle.participant', fn ($q) => $q->where('substrate_id', $execution->cycle->participant->substrate_id))
            ->get()->keyBy('id');
        $metadata = $execution->metadata ?? [];

        return [...$this->summary($execution),
            'selections' => array_map(function ($selection) use ($receipts) {
                $receipt = $receipts->get($selection['receipt_id']);
                $artifact = collect($receipt?->metadata['artifacts'] ?? [])->firstWhere('hash', $selection['artifact_id']);

                return [...Arr::only($selection, ['receipt_id', 'artifact_id', 'read_type', 'parameters']),
                    'source' => $receipt === null ? null : [
                        'receipt_type' => $receipt->type,
                        'reference' => $this->text($receipt->reference, 2048),
                        'filename' => $this->text($receipt->metadata['filename'] ?? null, 255),
                        'media_type' => $artifact['media_type'] ?? null,
                        'size' => $artifact['size'] ?? null,
                    ],
                ];
            }, $selections),
            // A stored selection or successful extraction is not an acknowledgement.
            'deliveries' => array_map(fn ($delivery) => Arr::only($delivery, [
                'request_id', 'artifact_id', 'receipt_ids', 'read_type', 'source_type', 'method', 'version',
                'representation', 'representation_hash', 'delivery_method', 'delivery_id', 'payload_hash', 'payload_bytes', 'boundaries',
            ]), $execution->presented_at === null ? [] : ($execution->presentation ?? [])),
            'deferment_reason' => $execution->outcome === 'deferred' ? $this->text($metadata['outcome']['reason'] ?? null, 400) : null,
            'failure' => $execution->status !== 'failed' ? null : [
                ...array_map(fn ($value) => $this->text($value, 160), Arr::only($metadata['failure'] ?? [], ['stage', 'classification', 'method', 'version', 'source_type', 'read_type', 'artifact_id'])),
                'reason' => $this->text($execution->failure_reason, 1000),
            ],
        ];
    }

    private function text(?string $value, int $limit): ?string
    {
        return $value === null ? null : mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value), 0, $limit);
    }
}
