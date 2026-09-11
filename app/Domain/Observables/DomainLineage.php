<?php

namespace App\Domain\Observables;

use App\Models\ContextHorizon;
use App\Models\Proposition;
use App\Models\Supersession;
use Illuminate\Support\Collection;

final readonly class DomainLineage
{
    /**
     * @param  Collection<int, Proposition>  $propositions
     * @param  Collection<int, Supersession>  $supersessions
     */
    public function __construct(
        public ContextHorizon $horizon,
        public Collection $propositions,
        public Collection $supersessions,
        public ?\App\Services\Participation\ParticipationContext $context = null,
    ) {}
}
