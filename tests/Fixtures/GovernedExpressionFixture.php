<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PhaseService;

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
        app(ContributionDispatcher::class)->dispatch($this->actor, $this->phase,
            ['action' => 'instrument.execute', 'proposition_id' => $this->origin->id,
                'instrument' => ['kind' => 'citation', 'reference' => $this->origin->id]]);
        $this->expression = app(ContributionDispatcher::class)->dispatch($this->actor, $this->phase,
            ['action' => 'proposition.variant', 'statement' => 'An independently authored first distinction.']);
    }

    /** Resolve the first attempt, Rest, and personally enter Persist in a new Cycle. */
    public function enterPersist(): void
    {
        $this->phase = app(PhaseProgressionService::class)->advance($this->phase, $this->expression);
        app(ParticipationService::class)->contribute($this->actor, $this->phase->id, ['action' => 'participant.rest']);
        $entry = app(PhaseService::class)->currentFor($this->actor->fresh());
        $target = app(ContributionDispatcher::class)->dispatch($this->actor, $entry,
            ['action' => 'attestation.create', 'proposition_id' => $this->expression->id, 'direction' => 'support']);
        $this->phase = app(PhaseProgressionService::class)->advance($entry, $target);
    }

    /** Acquire shared evidence, then produce the real Persist Write. */
    public function writePersist(): void
    {
        $this->enterPersist();
        app(ContributionDispatcher::class)->dispatch($this->actor, $this->phase,
            ['action' => 'instrument.execute', 'proposition_id' => $this->phase->gate_proposition_id,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/grounded-development']]);
        $this->expression = app(ContributionDispatcher::class)->dispatch($this->actor, $this->phase,
            ['action' => 'proposition.variant', 'statement' => 'Develop the first distinction.']);
    }

    /** Real Persist birth and evidence-first grounding before Constrain. */
    public function advanceToConstrain(): void
    {
        $this->writePersist();
        $dispatcher = app(ContributionDispatcher::class);
        $dispatcher->dispatch($this->actor, $this->phase,
            ['action' => 'attestation.create', 'proposition_id' => $this->expression->id, 'direction' => 'support']);
        $this->phase = app(PhaseProgressionService::class)->advance($this->phase, $this->expression);
    }
}
