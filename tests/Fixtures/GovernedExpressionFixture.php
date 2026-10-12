<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\PhaseProgressionService;

/** Real constitutional entry and authorised first expressive birth; no Commit double. */
final class GovernedExpressionFixture
{
    public Participant $actor;

    public Proposition $origin;

    public Phase $phase;

    public Proposition $expression;

    public function __construct()
    {
        $substrate = (new Substrate)->forceFill(['slug' => 'governed-'.str()->ulid(),
            'title' => 'Governed expression', 'visibility' => 'private', 'configuration' => []]);
        $substrate->save();
        $this->actor = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'author']);
        $this->actor->save();
        app(CycleEntryService::class)->enter($this->actor, ['statement' => 'Constitutional origin',
            'attestation' => ['direction' => 'support']]);
        $this->origin = $this->actor->fresh()->position;
        $cycle = $this->actor->cycles()->whereNull('completed_at')->sole();
        $this->phase = $cycle->phases()->where('kind', PhaseKind::Distinguish->value)->sole();
        $this->expression = app(ContributionDispatcher::class)->dispatch($this->actor, $this->phase,
            ['action' => 'proposition.variant', 'statement' => 'An independently authored first distinction.']);
    }

    /** Produce the real Persist Write while leaving its Phase open for evidence. */
    public function writePersist(): void
    {
        $this->phase = app(PhaseProgressionService::class)->advance($this->phase, $this->expression);
        $this->expression = app(ContributionDispatcher::class)->dispatch($this->actor, $this->phase,
            ['action' => 'proposition.variant', 'statement' => 'Develop the first distinction.']);
    }

    /** Real Persist birth and evidence-first grounding before Constrain. */
    public function advanceToConstrain(): void
    {
        $this->writePersist();
        $dispatcher = app(ContributionDispatcher::class);
        $dispatcher->dispatch($this->actor, $this->phase,
            ['action' => 'instrument.execute', 'proposition_id' => $this->expression->id,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/grounded-development']]);
        $dispatcher->dispatch($this->actor, $this->phase,
            ['action' => 'attestation.create', 'proposition_id' => $this->expression->id, 'direction' => 'support']);
        $this->phase = app(PhaseProgressionService::class)->advance($this->phase, $this->expression);
    }
}
