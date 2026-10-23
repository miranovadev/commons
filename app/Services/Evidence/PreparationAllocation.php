<?php

namespace App\Services\Evidence;

use App\Domain\Evidence\PreparationInput;
use App\Exceptions\ArtifactReadingException;

/** Unicode water filling in submitted order, after reserving explicit selections. */
final class PreparationAllocation
{
    public function allocate(array $representations): array
    {
        $characters = min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.text_budget'));
        $bytes = min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.delivery_bytes'));
        $perBytes = min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.selection_bytes'));
        $perChars = min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.max_chars'));
        if (min($characters, $bytes, $perBytes, $perChars) < 1) {
            throw new ArtifactReadingException('invalid_parameters', 'Reading budgets must be positive.');
        }
        $states = [];
        foreach ($representations as $index => $representation) {
            $parameters = $representation['parameters'];
            $total = mb_strlen($representation['content'], 'UTF-8');
            $offset = $parameters['offset'] ?? 0;
            if ($offset > $total) {
                throw new ArtifactReadingException('invalid_parameters', 'The requested offset is beyond the representation character length.',
                    array_intersect_key($representation, array_flip(['artifact_id', 'read_type', 'source_type', 'method', 'version'])));
            }
            $explicit = array_key_exists('max_chars', $parameters);
            $tail = mb_substr($representation['content'], $offset, min($perChars, $parameters['max_chars'] ?? $perChars), 'UTF-8');
            $length = $explicit ? mb_strlen($tail, 'UTF-8') : 0;
            $size = $explicit ? strlen($tail) : 0;
            if ($explicit && ($size > $perBytes || $size > $bytes || $length > $characters)) {
                throw new ArtifactReadingException('payload_limit', 'Explicit reading selections exceed the character or byte budget. Reduce the requested ranges.',
                    array_intersect_key($representation, array_flip(['artifact_id', 'read_type', 'source_type', 'method', 'version'])));
            }
            $characters -= $length;
            $bytes -= $size;
            $states[$index] = compact('tail', 'total', 'offset', 'explicit', 'length', 'size');
        }
        // One Unicode character per active selection is bounded water filling. Short or
        // byte-limited selections leave the pool; remainders follow authored order.
        do {
            $progress = false;
            foreach ($states as &$state) {
                if ($characters <= 0 || $bytes <= 0) {
                    break;
                }
                if ($state['explicit'] || $state['size'] === strlen($state['tail'])) {
                    continue;
                }
                $lead = ord($state['tail'][$state['size']]);
                $width = $lead < 0x80 ? 1 : ($lead < 0xE0 ? 2 : ($lead < 0xF0 ? 3 : 4));
                if ($width > $bytes || $perBytes < $state['size'] + $width) {
                    continue;
                }
                $state['length']++;
                $state['size'] += $width;
                $characters--;
                $bytes -= $width;
                $progress = true;
            }
            unset($state);
        } while ($progress && $characters > 0 && $bytes > 0);

        foreach ($representations as $index => &$representation) {
            $state = $states[$index];
            $content = substr($state['tail'], 0, $state['size']);
            $end = $state['offset'] + $state['length'];
            $representation['representation_hash'] = 'sha256:'.hash('sha256', $representation['content']);
            $representation['content'] = $content;
            $representation['payload_hash'] = 'sha256:'.hash('sha256', $content);
            $representation['payload_bytes'] = strlen($content);
            $representation['boundaries'] = ['unit' => 'unicode-characters',
                'requested_offset' => $representation['parameters']['offset'] ?? 0,
                'requested_max_chars' => $representation['parameters']['max_chars'] ?? null,
                'offset' => $state['offset'], 'end' => $end, 'length' => $state['length'],
                'total' => $state['total'], 'has_more' => $end < $state['total']];
        }
        unset($representation);

        return $representations;
    }
}
