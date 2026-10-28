<?php

namespace App\Services;

/** Deterministic presentation only: never queries or mutates load state. */
final class ParticipantPromptLayout
{
    public const VERSION = 'commons-context-c3';

    public const FORMAT_INSTRUCTION = 'Proposition receipts are IDs in the Receipts catalogue. Every Receipt has a bounded summary. loaded records personal loading; loaded_inline_content contains a recorded observation or scoped memory. load_mode read_context includes the observation because its actual consuming Proposition is Read, without changing personal memory. Consumed observations remain historical evidence. Visibility and contextual inclusion grant no permission; use only the Actions schema to select targets and parameters.';

    /** @return array{evidence: string, user: string, support: string, loaded_receipt_count: int} */
    public function make(array $packet): array
    {
        return [
            'evidence' => '',
            'user' => $this->encode($packet, preservePlanOrder: true),
            'support' => '',
            'loaded_receipt_count' => count(array_filter($packet['receipts'], fn ($receipt) => isset($receipt['loaded_inline_content']))),
        ];
    }

    private function encode(array $value, bool $preservePlanOrder = false): string
    {
        if ($preservePlanOrder) {
            // Preserve the chosen layer order and the action schema byte/order contract.
            foreach ($value as $key => $item) {
                if ($key !== 'actions') {
                    $value[$key] = $this->canonical($item);
                }
            }
        } else {
            $value = $this->canonical($value);
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map($this->canonical(...), $value);
    }
}
