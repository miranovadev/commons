<?php

namespace Tests\Unit;

use App\Domain\Observables\ObservableValues;
use App\Services\ProjectionService;
use PHPUnit\Framework\TestCase;

class ProjectionServiceTest extends TestCase
{
    public function test_signed_measurements_split_into_symmetric_projections(): void
    {
        $service = new ProjectionService;

        $this->assertSame(
            ['prominence' => 0.0, 'turbulence' => 1.0],
            $service->from(new ObservableValues(-1, -1, -1)),
        );
        $this->assertSame(
            ['prominence' => 0.6666667, 'turbulence' => 0.3333333],
            $service->from(new ObservableValues(1, 1, -1)),
        );
        $this->assertSame(
            ['prominence' => 0.5, 'turbulence' => 0.5],
            $service->from(new ObservableValues(0, 0, 0)),
        );
    }
}
