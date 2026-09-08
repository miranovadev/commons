<?php

namespace App\Services;

use App\Enums\LedgerEntryKind;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\SubstrateLedgerEntry;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ParticipationService
{
    public function __construct(
        private ContributionService $contributions,
        private ContributionDispatcher $dispatcher,
        private PhaseProgressionService $progression,
        private CycleEntryService $cycleEntry,
        private PotentialChargeService $potential,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function stateFor(Participant $participant): array
    {
        $cycle = Cycle::query()
            ->whereBelongsTo($participant)
            ->whereNull('completed_at')
            ->latest('sequence')
            ->first();

        if ($cycle === null) {
            return $this->waiting(
                'cycle_not_started',
                'The Participant is waiting for a Cycle to begin.',
                entry: $this->entryFor($participant),
            );
        }

        $phase = Phase::query()
            ->whereBelongsTo($cycle)
            ->whereNull('completed_at')
            ->latest('kind')
            ->first();

        if ($phase === null) {
            return $this->waiting(
                'cycle_has_no_active_phase',
                'The current Cycle has no active Phase.',
                $cycle,
            );
        }

        if ($phase->gate_proposition_id === null) {
            return $this->waiting(
                'potential_selection_pending',
                'The Phase is waiting for Potential to select its Gate.',
                $cycle,
            );
        }

        $plan = $this->contributions->planFor($participant);
        $exhausted = $plan['schema']['oneOf'] === [];
        $selection = $exhausted
            ? $this->potential->nextFrom($phase->gate()->firstOrFail())
            : null;

        return [
            'status' => $exhausted ? 'waiting' : 'ready',
            'cycle' => $this->cycle($cycle),
            'plan' => $plan,
            'perspective' => $this->perspective($participant),
            'reason' => $exhausted ? [
                'code' => 'no_admissible_write',
                'message' => 'The current position has no admissible Write action.',
            ] : null,
            'navigation' => $exhausted ? [
                'available' => $selection !== null,
                'phase_id' => $phase->getKey(),
                'next_proposition_id' => ($selection['proposition'] ?? null)?->getKey(),
                'potential' => $selection['potential'] ?? 0.0,
            ] : null,
            'entry' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $origin
     * @return array<string, mixed>
     */
    public function enter(Participant $participant, ?array $origin = null): array
    {
        $this->cycleEntry->enter($participant, $origin);

        return $this->stateFor($participant);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function contribute(
        Participant $participant,
        string $phaseId,
        array $input,
    ): array {
        return DB::transaction(function () use (
            $participant,
            $phaseId,
            $input,
        ): array {
            $lockedParticipant = Participant::query()
                ->lockForUpdate()
                ->findOrFail($participant->getKey());
            $cycle = Cycle::query()
                ->whereBelongsTo($lockedParticipant)
                ->whereNull('completed_at')
                ->latest('sequence')
                ->lockForUpdate()
                ->first();

            if ($cycle === null) {
                throw new ConflictHttpException(
                    'The Participant has no active Cycle.'
                );
            }

            $phase = Phase::query()
                ->whereBelongsTo($cycle)
                ->whereNull('completed_at')
                ->latest('kind')
                ->lockForUpdate()
                ->first();

            if ($phase === null || $phase->getKey() !== $phaseId) {
                throw new ConflictHttpException(
                    'The participation state changed. Refresh before contributing.'
                );
            }

            if (($input['action'] ?? null) === 'participant.traverse') {
                return $this->traverse($lockedParticipant, $phase);
            }

            $this->contributions->validate($lockedParticipant, $input);
            $gate = $this->dispatcher->dispatch(
                $lockedParticipant,
                $phase,
                $input,
            );
            $this->progression->advance($phase, $gate);

            return $this->stateFor($lockedParticipant);
        });
    }

    /**
     * Navigation is a server decision, not an expressive contribution. The
     * caller already holds the Participant and Phase locks; one request can
     * finish only one Cycle and take exactly one structural step.
     *
     * @return array<string, mixed>
     */
    private function traverse(Participant $participant, Phase $phase): array
    {
        $plan = $this->contributions->planFor($participant);
        if ($plan['schema']['oneOf'] !== []) {
            throw new \DomainException('Traversal requires an exhausted Write.');
        }
        $selection = $this->potential->nextFrom($phase->gate()->firstOrFail());
        if ($selection === null) {
            throw new \DomainException('No positive Potential is observable through a neighbour.');
        }

        $participant->current_proposition_id = $selection['proposition']->getKey();
        $participant->save();
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->completed_at = now();
        $phase->cycle->save();

        return $this->stateFor($participant);
    }

    /**
     * @return array<string, mixed>
     */
    private function waiting(
        string $code,
        string $message,
        ?Cycle $cycle = null,
        ?array $entry = null,
    ): array {
        return [
            'status' => 'waiting',
            'cycle' => $cycle === null ? null : $this->cycle($cycle),
            'plan' => null,
            'perspective' => null,
            'reason' => compact('code', 'message'),
            'entry' => $entry,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entryFor(Participant $participant): array
    {
        if ($participant->fresh()->current_proposition_id !== null) {
            return [
                // Preserve the existing client entry contract; selection is local.
                'mode' => 'potential',
                'selection' => 'position',
                'available' => true,
                'message' => 'Continue from the Participant’s current substrate position.',
            ];
        }

        $hasOrigin = SubstrateLedgerEntry::query()
            ->where('substrate_id', $participant->substrate_id)
            ->where('kind', LedgerEntryKind::Proposition->value)
            ->exists();

        if ($hasOrigin) {
            $available = CommitmentSnapshot::query()
                ->where('substrate_id', $participant->substrate_id)
                ->exists();

            return [
                'mode' => 'potential',
                'available' => $available,
                'message' => $available
                    ? 'Potential will select the Committal Gate with the greatest available reduction in Tension.'
                    : 'Potential is waiting for a measured Committal Proposition.',
            ];
        }

        return [
            'mode' => 'origin',
            'available' => true,
            'message' => 'Establish the first Committal Proposition to enter the substrate.',
            'requirements' => ['statement', 'attestation', 'receipt'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cycle(Cycle $cycle): array
    {
        return [
            'id' => $cycle->getKey(),
            'sequence' => $cycle->sequence,
            'created_at' => $cycle->created_at,
        ];
    }

    /**
     * Attenuation belongs at this boundary because it limits what the
     * Participant can perceive, without changing the substrate itself.
     *
     * @return array<string, mixed>
     */
    private function perspective(Participant $participant): array
    {
        return [
            'max_read_count' => $participant->max_read_count,
            'max_token_count' => $participant->max_token_count,
            'attenuation' => [
                'status' => 'active',
                'value' => 1.0,
                'distance' => 0,
                'formula' => '1 / (1 + structural_distance)',
            ],
        ];
    }
}
