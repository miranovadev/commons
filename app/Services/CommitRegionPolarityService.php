<?php

namespace App\Services;

use App\Services\Participation\ParticipationContext;
use App\Enums\ObservableDirection as Direction;
use App\Models\Proposition;
use Illuminate\Support\Facades\DB;

/** Immutable Attestation frame; independent of Tension and measurement coordinates. */
final class CommitRegionPolarityService
{
    public function __construct(private readonly CommitRegionService $regions) {}

    public function establishOrigin(Proposition $origin, string $positiveLabel): void
    {
        $this->store($origin, Direction::Positive,
            $positiveLabel === 'support' ? Direction::Positive : Direction::Negative);
    }

    public function establishCommit(Proposition $commit, Direction $sourceDirection): void
    {
        $this->store($commit, $sourceDirection, $sourceDirection);
    }

    /** @return array{governing: Direction, support: Direction} */
    public function forSeed(Proposition $seed, ?ParticipationContext $context = null): array
    {
        if ($context !== null) return $context->remember('polarity:'.$seed->id, fn () => $this->resolveSeed($seed, $context));
        return $this->resolveSeed($seed);
    }

    private function resolveSeed(Proposition $seed, ?ParticipationContext $context = null): array
    {
        $frame = $context !== null ? $context->frames->get($seed->id) : DB::table('commit_region_polarities')->where('proposition_id', $seed->id)->first();
        if ($frame !== null) {
            return ['governing' => Direction::from($frame->governing_direction),
                'support' => Direction::from($frame->support_direction)];
        }

        // Read-only reconstruction for pre-upgrade/legacy contributions. Commit-only
        // evidence is evaluated at the original Commit edge, never at today's state.
        $source = $context !== null ? $context->parentNodes($seed, true)->first() : $seed->commitSources()->first();
        if ($source !== null) {
            $edge = $context !== null ? $context->parents->get($seed->id)->where('type', 'commit')->first() : \App\Models\Supersession::query()->where('target_proposition_id', $seed->id)
                ->where('type', 'commit')->with('ledgerEntry')->firstOrFail();
            $direction = app(AttestationEvidenceService::class)->directionFor($source, $edge->ledgerEntry?->sequence, $context);
            return ['governing' => $direction, 'support' => $direction];
        }

        $first = $context !== null ? $context->attestations->where('proposition_id', $seed->id)->sortBy([['created_at', 'asc'], ['id', 'asc']])->first() : $seed->attestations()->orderBy('created_at')->orderBy('id')->first();
        return ['governing' => Direction::Positive,
            'support' => $first?->direction === 'refute' ? Direction::Negative : Direction::Positive];
    }

    public function seedFor(Proposition $position): ?Proposition
    {
        return $this->regions->seedFor($position);
    }

    public function backfill(Proposition $seed): void
    {
        $frame = $this->forSeed($seed);
        $this->store($seed, $frame['governing'], $frame['support']);
    }

    private function store(Proposition $seed, Direction $governing, Direction $support): void
    {
        if ($seed->tier !== 'committal') {
            throw new \DomainException('Only a Committal Proposition can establish region polarity.');
        }
        DB::table('commit_region_polarities')->insertOrIgnore([
            'proposition_id' => $seed->id,
            'governing_direction' => $governing->value,
            'support_direction' => $support->value,
        ]);
        $stored = $this->forSeed($seed);
        if ($stored['governing'] !== $governing || $stored['support'] !== $support) {
            throw new \DomainException('Commit Region polarity is immutable.');
        }
    }
}
