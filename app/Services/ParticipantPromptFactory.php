<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Connection;
use DomainException;

final class ParticipantPromptFactory
{
    public function __construct(
        private readonly ParticipantContextSerializer $context,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     * @return array{system: string, evidence: string, user: string, loaded_receipt_count: int, instruction_family: string, schema: array<string, mixed>}
     */
    public function make(Connection $connection, array $state): array
    {
        $schema = data_get($state, 'plan.schema');

        if (! is_array($schema)) {
            throw new DomainException(
                'A ready participation state with an input schema is required.'
            );
        }

        $phase = array_find(PhaseKind::cases(), fn (PhaseKind $kind): bool => strtolower($kind->name) === data_get($state, 'plan.phase.kind'));
        $number = data_get($state, 'plan.phase.number');
        if ($phase === null || ($number !== null && $number !== $phase->value)) {
            throw new DomainException('A known, consistent participation Phase is required.');
        }

        $system = ParticipantPromptCore::TEXT."\n\n".ParticipantPromptLayout::FORMAT_INSTRUCTION;

        $instructions = trim((string) data_get(
            $connection->configuration,
            'instructions',
            '',
        ));

        if ($instructions !== '') {
            $system .= "\n\nConnection instructions:\n{$instructions}";
        }

        return [
            'system' => $system,
            // One family across Phase changes, invalidated by any Commons or custom instruction edit.
            // Only the current contract is sent; this fingerprint contains no additional model context.
            'instruction_family' => hash('sha256', json_encode([
                ParticipantPromptCore::TEXT, ParticipantPromptLayout::FORMAT_INSTRUCTION,
                $instructions,
            ], JSON_THROW_ON_ERROR)),
            ...(new ParticipantPromptLayout)->make($this->context->inference($state),
                $this->context->receipts($state['plan']['receipts'] ?? []), $state['plan']['read_options'] ?? []),
            'schema' => $schema,
        ];
    }
}
