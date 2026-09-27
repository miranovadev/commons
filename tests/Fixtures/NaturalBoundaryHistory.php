<?php

namespace Tests\Fixtures;

use App\Domain\Observables\ObservableValues;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitmentSnapshotService;
use App\Services\LedgerService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\ParticipationService;
use App\Services\ProjectionService;
use App\Services\PropositionMeasurementService;

/** Real post-bootstrap measurements over explicitly authored causal history. */
trait NaturalBoundaryHistory
{
    /** @return array{Substrate, Proposition, Proposition, Proposition} */
    private function naturalBoundaryHistory(): array
    {
        $substrate = (new Substrate)->forceFill(['slug' => 'natural-boundary', 'title' => 'Natural boundary',
            'visibility' => 'public', 'configuration' => []]);
        $substrate->save();
        $author = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'history']);
        $author->save();
        $state = app(ParticipationService::class)->enter($author, ['statement' => 'Origin',
            'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/natural-origin']]);
        $origin = Proposition::findOrFail($state['current_proposition_id']);
        $cycle = $author->cycles()->whereNull('completed_at')->sole();
        $cycle->phases()->update(['completed_at' => now()]);

        // The completed reference extends beyond the later seed. Its hard
        // expressive step occupies the same cardinal coordinate as that Commit.
        $parent = $origin;
        foreach ([
            [PhaseKind::Distinguish, 'variant'], [PhaseKind::Persist, 'variant'],
            [PhaseKind::Constrain, 'reclassify'], [PhaseKind::Constrain, 'revise'],
            [PhaseKind::Disperse, 'variant'], [PhaseKind::Persist, 'variant'],
            [PhaseKind::Bias, 'reclassify'], [PhaseKind::Order, 'commit'],
        ] as [$kind, $type]) {
            $parent = $this->naturalHistoryNode($cycle, $parent, $kind, $type);
        }
        $ancestor = $parent;
        $this->assertSame('emergence', $ancestor->commitmentSnapshot->direction_after->value);

        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $author->id, 'sequence' => 3,
            'entry_committal_id' => $ancestor->id])->save();
        foreach ([[PhaseKind::Distinguish, 'variant'], [PhaseKind::Persist, 'variant'],
            [PhaseKind::Bias, 'reclassify'], [PhaseKind::Order, 'commit']] as [$kind, $type]) {
            $parent = $this->naturalHistoryNode($cycle, $parent, $kind, $type);
        }
        $seed = $parent;
        $this->assertSame(1.0, $seed->commitmentSnapshot->depth);
        $this->assertSame(1.0, $seed->commitmentSnapshot->cadence);
        $this->assertSame(1.0, $seed->commitmentSnapshot->correspondence);
        $this->assertSame(1.0, $seed->commitmentSnapshot->chirality);
        $this->assertDatabaseCount('tragedies', 0);

        return [$substrate, $origin, $ancestor, $seed];
    }

    private function naturalHistoryNode(Cycle $cycle, Proposition $parent, PhaseKind $kind, string $type): Proposition
    {
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'gate_proposition_id' => $parent->id,
            'write_register' => $cycle->contextHorizon->domain->registerFor($kind), 'started_at' => now()])->save();
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
            'tier' => $type === 'commit' ? 'committal' : 'expressive', 'statement' => 'Developed '.$kind->name])->save();
        app(LedgerService::class)->record($node);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $node->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
        if ($type !== 'commit') {
            $vote = new Attestation;
            $vote->forceFill(['phase_id' => $phase->id, 'proposition_id' => $node->id, 'direction' => 'support'])->save();
            app(LedgerService::class)->record($vote);
            $this->receiptFixture($node);
        } else {
            $measurement = app(PropositionMeasurementService::class)->recalculate($node);
            $field = app(ProjectionService::class)->from(new ObservableValues(
                $measurement->correspondence, $measurement->chirality, $measurement->closure));
            app(CommitmentSnapshotService::class)->record($node, $field['prominence'], $field['turbulence'],
                LineageMeasurementService::VERSION, $edge);
            $cycle->forceFill(['completed_at' => now()])->save();
        }
        $phase->forceFill(['completed_at' => now()])->save();

        return $node;
    }
}
