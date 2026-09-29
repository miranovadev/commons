<?php

namespace Tests\Unit;

use App\Domain\Observables\AttestationTimeline;
use App\Enums\ObservableDirection as Direction;
use PHPUnit\Framework\TestCase;

class AttestationTimelineTest extends TestCase
{
    public function test_equal_contributions_replay_replacement_topology_and_through_boundaries(): void
    {
        $timeline = new AttestationTimeline([
            ['id' => 'a', 'proposition_id' => 'left', 'sequence' => 1, 'replaced_at' => 4, 'sign' => -1],
            ['id' => 'b', 'proposition_id' => 'right', 'sequence' => 2, 'replaced_at' => null, 'sign' => 1],
            ['id' => 'c', 'proposition_id' => 'left', 'sequence' => 4, 'replaced_at' => null, 'sign' => 1],
        ]);
        $ids = ['left', 'right'];
        $this->assertSame([1, 4, 2], $timeline->events());
        $this->assertSame(-1.0, $timeline->value($ids, 1));
        $this->assertSame(0.0, $timeline->value($ids, 2));
        $this->assertSame(1.0, $timeline->value($ids, 4));
        $this->assertSame(Direction::Negative, $timeline->direction(Direction::Positive, fn () => $ids, through: 3));
        $this->assertSame(Direction::Positive, $timeline->direction(Direction::Negative, fn () => $ids));
        $this->assertSame(Direction::Positive, $timeline->direction(Direction::Negative,
            fn ($sequence) => $sequence < 3 ? $ids : ['right'], [3], through: 3));
    }
}
