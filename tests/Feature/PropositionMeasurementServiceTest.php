<?php

namespace Tests\Feature;

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

    public function test_measurements_are_a_replaceable_projection(): void
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
        $this->assertNull($measurement->closure);
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
        $this->assertSame(1.0, $replacement->closure);
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

    public function test_all_observable_directions_survive_zero_null_and_projection_replacement(): void
    {
        $position = $this->proposition();
        $measurements = app(PropositionMeasurementService::class);
        $directions = app(\App\Services\MeasurementDirectionService::class);
        foreach ([1.0, -1.0] as $sign) {
            $measurements->project($position, $sign, $sign, $sign);
            foreach ([0.0, null] as $neutral) {
                $measurements->project($position, $neutral, $neutral, $neutral);
                foreach ([\App\Enums\MeasurementAxis::Correspondence, \App\Enums\MeasurementAxis::Chirality, \App\Enums\MeasurementAxis::Closure] as $axis) {
                    $this->assertSame(\App\Enums\ObservableDirection::after($sign), $directions->forProposition(
                        $position, $axis, fn ($node) => $node->fresh()->measurement?->{$axis->value},
                    ));
                }
            }
            PropositionMeasurement::query()->whereKey($position->id)->delete();
            $this->assertSame(\App\Enums\ObservableDirection::after($sign), $directions->forProposition(
                $position, \App\Enums\MeasurementAxis::Correspondence, fn () => null,
            ));
        }
    }

    public function test_unmeasured_roots_have_documented_bootstrap_directions(): void
    {
        $root = $this->proposition();
        $service = app(\App\Services\MeasurementDirectionService::class);
        foreach (\App\Enums\MeasurementAxis::cases() as $axis) {
            $this->assertSame($axis->initialDirection(), $service->forProposition($root, $axis, fn () => null));
        }
        $this->assertDatabaseCount('proposition_directions', 0);
    }

    public function test_unestablished_directions_inherit_primary_parent_but_own_history_takes_precedence(): void
    {
        $parent = $this->proposition();
        $child = $parent->replicate();
        $child->save();
        $edge = new \App\Models\Supersession;
        $edge->forceFill(['phase_id' => $parent->phase_id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $child->id, 'type' => 'variant'])->save();
        $measurements = app(PropositionMeasurementService::class);
        $directions = app(\App\Services\MeasurementDirectionService::class);
        $measurements->project($parent, -1, -1, 1);
        $measurements->project($child, 0, null, 0);
        foreach ([\App\Enums\MeasurementAxis::Correspondence, \App\Enums\MeasurementAxis::Chirality, \App\Enums\MeasurementAxis::Closure] as $axis) {
            $sample = fn ($node) => $node->fresh()->measurement?->{$axis->value};
            $expected = $axis === \App\Enums\MeasurementAxis::Closure ? 1 : -1;
            $this->assertSame(\App\Enums\ObservableDirection::after($expected), $directions->forProposition($child, $axis, $sample));
        }
        $measurements->project($child, 1, 1, -1);
        $measurements->project($child, 0, 0, 0);
        $this->assertSame(\App\Enums\ObservableDirection::Positive, $directions->forProposition(
            $child, \App\Enums\MeasurementAxis::Correspondence, fn ($node) => $node->fresh()->measurement?->correspondence,
        ));
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
