<?php

namespace App\Models;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\CorrespondenceValues;
use App\Enums\ObservableDirection;
use Illuminate\Database\Eloquent\Model;

/** Immutable resolved baseline, not a Committal-local expressive observation. */
class CanonicalMeasurement extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'proposition_id';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return array_fill_keys(['correspondence', 'chirality', 'orientation', 'attestation_cadence', 'shape_topology', 'phase_profile'], 'float');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \DomainException('Canonical measurements are immutable.'));
    }

    public function chiralityComponents(): ChiralityValues
    {
        return new ChiralityValues($this->orientation, $this->attestation_cadence,
            retainedOrientation: ObservableDirection::from($this->orientation_direction),
            retainedCadence: ObservableDirection::from($this->attestation_cadence_direction));
    }

    public function correspondenceComponents(): CorrespondenceValues
    {
        return new CorrespondenceValues($this->shape_topology, $this->phase_profile,
            ObservableDirection::from($this->shape_topology_direction), ObservableDirection::from($this->phase_profile_direction));
    }

    public function metadata(): array
    {
        return $this->only(['chirality_direction', 'orientation', 'attestation_cadence', 'orientation_direction',
            'attestation_cadence_direction', 'shape_topology', 'phase_profile', 'shape_topology_direction', 'phase_profile_direction']);
    }
}
