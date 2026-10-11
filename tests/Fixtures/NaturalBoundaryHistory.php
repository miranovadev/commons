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
use App\Services\Observables\ChiralityCalculator;
use App\Services\Observables\CanonicalMeasurementService;
use App\Services\Observables\ClosureBirthService;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\ParticipationService;
use App\Services\ProjectionService;
use App\Services\PropositionMeasurementService;

/** Real post-bootstrap measurements over explicitly authored causal history. */
trait NaturalBoundaryHistory
{
    /** @return array{Substrate, Proposition, Proposition, Proposition} */
    private function naturalBoundaryHistory(int $futureParticipants = 1): array
    {
        // Unlike controlled routing fixtures, this fixture measures every observable.
        $this->app->forgetInstance(LocalMeasurementService::class);
        $this->app->forgetInstance(ClosureBirthService::class);
        $this->app->forgetInstance(ProspectiveClosureService::class);
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
        // This is constructed catastrophe geometry, not a bootstrap trajectory:
        // establish three Settlement Commits so a later third Emergence
        // observation can compare full committed depths of three against three.
        $firstSettlement = $parent;
        $this->assertSame('settlement', $firstSettlement->commitmentSnapshot->direction_after->value);
        // Full committed depth needs three S observations before the third E
        // observation can align. Build that later window with real Commits.
        for ($commitIndex = 0; $commitIndex < 2; $commitIndex++) {
            if ($commitIndex === 1) {
                for ($i = 0; $i < 4; $i++) {
                    $this->naturalIdleEntry($origin);
                }
            }
            $entry = $parent;
            $cycle = new Cycle;
            $cycle->forceFill(['participant_id' => $author->id, 'sequence' => (int) $author->cycles()->max('sequence') + 1,
                'entry_committal_id' => $entry->id])->save();
            $this->naturalIdleEntry($entry);
            $parent = $this->naturalHistoryNode($cycle, $parent, PhaseKind::Bias, 'reclassify');
            $parent = $this->naturalHistoryNode($cycle, $parent, PhaseKind::Order, 'commit');
            $this->assertSame($commitIndex === 0 ? 'settlement' : 'emergence', $parent->commitmentSnapshot->direction_after->value,
                json_encode($parent->commitmentSnapshot->only(['correspondence', 'chirality', 'closure', 'tension'])));
        }
        $ancestor = $parent;
        $this->assertSame('emergence', $ancestor->commitmentSnapshot->direction_after->value, json_encode([
            'snapshot' => $ancestor->commitmentSnapshot->only(['correspondence', 'chirality', 'orientation', 'attestation_cadence', 'closure', 'tension']),
            'components' => app(ChiralityCalculator::class)->components(app(ContextLineageComparisonService::class)->for($ancestor)),
        ]));

        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $author->id, 'sequence' => (int) $author->cycles()->max('sequence') + 1,
            'entry_committal_id' => $ancestor->id])->save();
        foreach ([[PhaseKind::Distinguish, 'variant'], [PhaseKind::Persist, 'variant'],
            [PhaseKind::Bias, 'reclassify'], [PhaseKind::Order, 'commit']] as [$kind, $type]) {
            $parent = $this->naturalHistoryNode($cycle, $parent, $kind, $type);
            if ($kind === PhaseKind::Distinguish) {
                $this->naturalIdleEntry($ancestor, $parent);
            }
        }
        $seed = $parent;
        // E and S will each observe [1,2,2] at the catastrophe birth. The
        // one-participant case needs one idle seed entry; the lifecycle supplies two.
        if ($futureParticipants === 1) {
            $this->naturalIdleEntry($seed);
        }
        $this->assertSame(.3333333, $seed->commitmentSnapshot->depth);
        $this->assertSame(.6970563, $seed->commitmentSnapshot->cadence);
        // The seed matches its reference locally; attenuation also includes the
        // preceding boundary's independently calculated first-Domain value.
        $raw = app(LocalMeasurementService::class);
        $this->assertSame(1.0, $raw->calculate($seed)->correspondence);
        $ancestorCorrespondence = $raw->calculate($ancestor)->correspondence;
        $this->assertLessThan(0, $ancestorCorrespondence);
        $mass = 1 + 1 / 4 + 1 / 9 + 1 / 16 + 1 / 25;
        $expected = round((1 + 1 / 4 + 1 / 9 + 1 / 16 + $ancestorCorrespondence / 25) / $mass, 7);
        $this->assertSame($expected, $seed->commitmentSnapshot->correspondence);
        $this->assertSame(1.0, $seed->commitmentSnapshot->orientation);
        $this->assertEqualsWithDelta(0, $seed->commitmentSnapshot->attestation_cadence, 1e-7);
        $this->assertDatabaseCount('tragedies', 0);

        return [$substrate, $origin, $ancestor, $seed];
    }

    private function naturalIdleEntry(Proposition $entry, ?Proposition $attest = null): void
    {
        $participant = (new Participant)->forceFill(['substrate_id' => $entry->phase->cycle->participant->substrate_id,
            'callsign' => 'idle-'.Participant::count()]);
        $participant->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1,
            'entry_committal_id' => $entry->id])->save();
        if ($attest !== null) {
            $phase = new Phase;
            $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish,
                'gate_proposition_id' => $entry->id, 'write_register' => $attest->register, 'started_at' => now()])->save();
            $vote = new Attestation;
            $vote->forceFill(['phase_id' => $phase->id, 'proposition_id' => $attest->id, 'direction' => 'support'])->save();
            app(LedgerService::class)->record($vote);
        }
    }

    private function naturalHistoryNode(Cycle $cycle, Proposition $parent, PhaseKind $kind, string $type): Proposition
    {
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'gate_proposition_id' => $parent->id,
            'write_register' => $cycle->contextHorizon->domain->registerFor($kind), 'started_at' => now()])->save();
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
            'tier' => $type === 'commit' ? 'committal' : 'expressive', 'statement' => 'Developed '.$kind->name])->save();
        app(LedgerService::class)->record($node, $parent);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $node->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
        if ($type !== 'commit') {
            app(ProspectiveClosureService::class)->initialize($node);
            $vote = new Attestation;
            $vote->forceFill(['phase_id' => $phase->id, 'proposition_id' => $node->id, 'direction' => 'support'])->save();
            app(LedgerService::class)->record($vote);
            $this->receiptFixture($node);
        } else {
            $birth = app(ProspectiveClosureService::class)->stored($parent);
            $this->assertTrue($birth['admissible'], 'Natural history Commit birth: '.json_encode($birth));
            $source = app(PropositionMeasurementService::class)->recalculate($parent);
            app(CanonicalMeasurementService::class)->preserve($node, $parent, $source,
                app(PropositionMeasurementService::class)->correspondenceDirectionFor($parent)->value);
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
