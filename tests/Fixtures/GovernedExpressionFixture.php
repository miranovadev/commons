<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;

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
}
