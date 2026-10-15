<?php

namespace App\Console\Commands;

use App\Exceptions\PotentialProjectionUnavailable;
use App\Models\Participant;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\ReferencePotentialChargeService;
use Illuminate\Console\Command;

final class InspectPotential extends Command
{
    protected $signature = 'commons:potential-inspect {participant} {--verify : Explicitly run the slow full-graph reference calculation}';

    protected $description = 'Inspect projected Potential, provenance hops and version integrity without repairing state';

    public function handle(PotentialChargeService $potential, PotentialProjectionService $projection): int
    {
        $participant = Participant::findOrFail($this->argument('participant'));
        $position = $participant->position()->firstOrFail();
        $cycle = $participant->cycles()->latest('sequence')->first();
        try {
            $result = $potential->inspectRecoveryFrom($position, $cycle?->contextHorizon, $participant);
        } catch (PotentialProjectionUnavailable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $selection = $result['selection'];
        unset($result['selection']['proposition']);
        $region = $projection->region($result['starting_context_horizon_id']);
        $result['current_proposition_id'] = $position->id;
        $result['parent_context_horizon_id'] = $region['local']['parent'];
        $result['region_potential'] = $region['potential'];
        $result['substrate_field_version'] = (int) $participant->substrate->fresh()->field_version;
        $result['would_tragedy'] = false; // Potential and inspection never publish terminal encounters.
        if ($this->option('verify')) {
            $reference = app(ReferencePotentialChargeService::class)->inspectRecoveryFrom($position, $cycle?->contextHorizon, $participant)['selection'];
            $result['reference_matches'] = ($selection['proposition_id'] ?? null) === ($reference['proposition']->id ?? null)
                && ($selection['raw_potential_charge'] ?? null) === ($reference['raw_potential_charge'] ?? null)
                && ($selection['distance'] ?? null) === ($reference['distance'] ?? null)
                && abs(($selection['potential_charge'] ?? 0) - ($reference['potential_charge'] ?? 0)) < 1e-12;
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return ($result['reference_matches'] ?? true) ? self::SUCCESS : self::FAILURE;
    }
}
