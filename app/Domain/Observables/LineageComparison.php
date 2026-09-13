<?php

namespace App\Domain\Observables;

final readonly class LineageComparison
{
    public function __construct(
        public DomainLineage $current,
        public ?DomainLineage $previous,
    ) {}

    public function isBootstrap(): bool
    {
        return $this->current->horizon->parent_context_horizon_id === null
            && $this->previous === null;
    }
}
