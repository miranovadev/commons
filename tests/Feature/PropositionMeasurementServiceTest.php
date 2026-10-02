<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Services\PropositionMeasurementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PropositionMeasurementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_measurements_are_replaceable_but_closure_comes_from_birth(): void
    {
        $proposition = $this->proposition();
        $propositionUpdatedAt = $proposition->getRawOriginal('updated_at');
        $calculatedAt = CarbonImmutable::parse('2026-09-05 12:00:00 UTC');
        $service = app(PropositionMeasurementService::class);

        $measurement = $service->project(
            $proposition,
            correspondence: 0.75,
            chirality: -0.25,
            closure: null,
            calculatedAt: $calculatedAt,
        );

        $this->assertSame(0.75, $measurement->correspondence);
        $this->assertSame(-0.25, $measurement->chirality);
        $this->assertSame(-1.0, $measurement->closure);
        $this->assertTrue($measurement->calculated_at->equalTo($calculatedAt));
        $this->assertTrue($proposition->measurement->is($measurement));

        $replacement = $service->project(
            $proposition,
            correspondence: -1,
            chirality: 0,
            closure: 1,
        );

        $this->assertSame(1, PropositionMeasurement::query()->count());
        $this->assertSame(-1.0, $replacement->correspondence);
        $this->assertSame(0.0, $replacement->chirality);
        $this->assertSame(-1.0, $replacement->closure);
        $this->assertSame(
            $propositionUpdatedAt,
            $proposition->fresh()->getRawOriginal('updated_at'),
        );
    }

    public function test_measurements_must_stay_within_their_signed_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'correspondence must be between -1 and 1, or null.'
        );

        app(PropositionMeasurementService::class)->project(
            new Proposition,
            correspondence: 1.01,
            chirality: null,
            closure: null,
        );
    }

    public function test_correspondence_retains_both_directions_through_zero_and_projection_deletion(): void
    {
        $position = $this->proposition();
        $service = app(PropositionMeasurementService::class);
        foreach ([1.0, -1.0] as $sign) {
            $service->project($position, $sign, 0, 0);
            foreach ([0.0, null] as $value) {
                $service->project($position, $value, 0, 0);
                $this->assertSame(ObservableDirection::after($sign), $service->correspondenceDirectionFor($position));
            }
            PropositionMeasurement::query()->whereKey($position->id)->delete();
            $this->assertSame(ObservableDirection::after($sign), $service->correspondenceDirectionFor($position));
        }
    }

    private function proposition(): Proposition
    {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'measurement-tests',
            'title' => 'Measurement tests',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $substrate->save();

        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => 'observer',
        ]);
        $participant->save();

        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'sequence' => 1,
        ]);
        $cycle->save();

        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'kind' => PhaseKind::Distinguish,
            'write_register' => 'activity',
            'started_at' => now(),
        ]);
        $phase->save();

        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => 'activity',
            'tier' => 'expressive',
            'statement' => 'A measurable proposition.',
        ]);
        $proposition->save();

        return $proposition;
    }
}
