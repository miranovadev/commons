<?php

namespace App\Domain\Observables;

use App\Enums\ObservableDirection as Direction;

/** Read-only ledger replay of signed evidence, not a fourth measurement. */
final readonly class AttestationTimeline
{
    /** @param list<array{id: string, proposition_id: string, sequence: int, replaced_at: ?int, sign: int, loads: list<array{id: string, participant_id: string, sequence: int, action: string}>}> $evidence */
    public function __construct(private array $evidence, private float $receiptIncrement) {}

    public function events(): array
    {
        $events = [];
        foreach ($this->evidence as $row) {
            $events[] = $row['sequence'];
            if ($row['replaced_at'] !== null) {
                $events[] = $row['replaced_at'];
            }
            array_push($events, ...array_column($row['loads'], 'sequence'));
        }

        return array_values(array_unique($events));
    }

    /** Effective evidence, deduplicated by original Attestation ID at each ledger prefix. */
    public function value(array $sourceIds, int $through = PHP_INT_MAX): float
    {
        $ids = array_fill_keys($sourceIds, true);
        $net = $mass = 0.0;
        $active = [];
        foreach ($this->evidence as $row) {
            if (! isset($ids[$row['proposition_id']]) || $row['sequence'] > $through
                || ($row['replaced_at'] !== null && $row['replaced_at'] <= $through)) {
                continue;
            }
            $events = $row['loads'];
            usort($events, fn ($a, $b) => $a['sequence'] <=> $b['sequence']);
            $states = [];
            foreach ($events as $event) {
                if ($event['sequence'] <= $through) {
                    if ($event['action'] === 'loaded') {
                        $states[$event['id']] = $event;
                    } else {
                        unset($states[$event['id']]);
                    }
                }
            }
            foreach ($states as $event) {
                $key = $row['proposition_id'].':'.$event['participant_id'];
                if (! isset($active[$key]) || $event['sequence'] < $active[$key]['sequence']) {
                    $active[$key] = ['attestation_id' => $row['id'], 'sequence' => $event['sequence']];
                }
            }
        }
        foreach ($this->evidence as $row) {
            if (! isset($ids[$row['proposition_id']]) || $row['sequence'] > $through
                || ($row['replaced_at'] !== null && $row['replaced_at'] <= $through)) {
                continue;
            }
            $weight = 1 + count(array_filter($active, fn ($load) => $load['attestation_id'] === $row['id'])) * $this->receiptIncrement;
            $mass += $weight;
            $net += $row['sign'] * $weight;
        }

        return $mass === 0.0 ? 0.0 : $net / $mass;
    }

    /** The population function may change at topology events, including hard removal. */
    public function direction(Direction $initial, callable $population, array $topologyEvents = [], int $through = PHP_INT_MAX): Direction
    {
        $events = array_unique([...$this->events(), ...$topologyEvents, $through]);
        sort($events, SORT_NUMERIC);
        $direction = $initial;
        foreach ($events as $sequence) {
            if ($sequence <= $through) {
                $direction = Direction::after($this->value($population($sequence), $sequence), $direction);
            }
        }

        return $direction;
    }
}
