<?php

namespace App\Services\Evidence;

use App\Domain\Evidence\PreparationInput;
use App\Exceptions\ArtifactReadingException;

/** Proportional explicit preferences, then automatic Unicode water filling. */
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
            $length = $size = 0;
            $weight = $parameters['max_chars'] ?? 1;
            $states[$index] = compact('tail', 'total', 'offset', 'explicit', 'length', 'size', 'weight');
        }
        // Weighted fair allocation, one complete UTF-8 character at a time.
        // Short/byte-limited representations leave the pool, redistributing their
        // unused share immediately. Equal ratios break in submitted order.
        while ($characters > 0 && $bytes > 0) {
            $chosen = null;
            $ratio = INF;
            foreach ($states as $index => $state) {
                if (! $state['explicit'] || $state['size'] === strlen($state['tail'])) {
                    continue;
                }
                $width = $this->width($state);
                $next = ($state['length'] + 1) / $state['weight'];
                if ($width <= $bytes && $perBytes >= $state['size'] + $width && $next < $ratio) {
                    $chosen = $index;
                    $ratio = $next;
                }
            }
            if ($chosen === null) {
                break;
            }
            $width = $this->width($states[$chosen]);
            $states[$chosen]['length']++;
            $states[$chosen]['size'] += $width;
            $characters--;
            $bytes -= $width;
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
                $width = $this->width($state);
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

    private function width(array $state): int
    {
        $lead = ord($state['tail'][$state['size']]);

        return $lead < 0x80 ? 1 : ($lead < 0xE0 ? 2 : ($lead < 0xF0 ? 3 : 4));
    }
}
