<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\LedgerEntryKind;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitmentSnapshotService;
use App\Services\ContextHorizonProjector;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextHorizonProjectorTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    private Phase $phase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->substrate = new Substrate;
        $this->substrate->forceFill([
            'slug' => 'horizon-tests',
            'title' => 'Horizon tests',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $this->substrate->save();

        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $this->substrate->getKey(),
            'callsign' => 'observer',
        ]);
        $participant->save();

        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'sequence' => 1,
        ]);
        $cycle->save();

        $this->phase = new Phase;
        $this->phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'kind' => PhaseKind::Distinguish,
            'write_register' => 'activity',
            'started_at' => now(),
        ]);
        $this->phase->save();
    }

    public function test_the_ledger_provides_idempotent_substrate_local_order(): void
    {
        $first = $this->proposition('First expression.');
        $second = $this->proposition('Second expression.');
        $attestation = new Attestation;
        $attestation->forceFill([
            'phase_id' => $this->phase->getKey(),
            'proposition_id' => $first->getKey(),
            'direction' => 'positive',
        ]);
        $attestation->save();

        $ledger = app(LedgerService::class);
        $firstEntry = $ledger->record($first);
        $secondEntry = $ledger->record($second);
        $attestationEntry = $ledger->record($attestation);

        $this->assertSame(1, $firstEntry->sequence);
        $this->assertSame(2, $secondEntry->sequence);
        $this->assertSame(3, $attestationEntry->sequence);
        $this->assertSame(
            LedgerEntryKind::Attestation,
            $attestationEntry->kind,
        );
        $this->assertTrue($firstEntry->is($ledger->record($first)));
        $this->assertCount(3, $this->substrate->ledgerEntries);

        $horizon = ContextHorizon::query()->sole();
        $this->assertSame(ContextDomain::Emergence, $horizon->domain);
        $this->assertNull($horizon->closed_at);
        $this->assertSame(
            2,
            ContextHorizonMembership::query()->count(),
        );

        $slice = $ledger->fetch($this->substrate, limit: 2);
        $this->assertSame([3, 2], array_column($slice['items'], 'sequence'));
    }

    public function test_completed_receipts_enter_the_ledger_atomically(): void
    {
        $proposition = $this->proposition('Evidence target.');
        $service = app(InstrumentExecutionService::class);
        $execution = $service->start(
            $this->phase,
            $proposition,
            'citation',
            'https://example.test/evidence',
        );

        $receipt = $service->complete($execution, $proposition);

        $this->assertDatabaseHas('substrate_ledger_entries', [
            'kind' => 'receipt',
            'ledgerable_type' => $receipt->getMorphClass(),
            'ledgerable_id' => $receipt->getKey(),
        ]);
    }

    public function test_an_incomplete_commitment_rolls_back_its_ledger_entry(): void
    {
        $proposition = $this->proposition(
            'An unmeasured commitment.',
            'committal',
        );

        try {
            app(CommitmentSnapshotService::class)->record(
                $proposition,
                prominence: 0.75,
                turbulence: 0.25,
                calculatorVersion: 'test-v1',
            );

            $this->fail('An incomplete commitment was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'A commitment requires all three Proposition measurements.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('substrate_ledger_entries', 0);
        $this->assertDatabaseCount('commitment_snapshots', 0);
        $this->assertDatabaseCount('context_horizons', 1); // The Cycle already owns the root.
    }

    public function test_cross_substrate_edges_cannot_enter_the_ledger(): void
    {
        $otherSubstrate = new Substrate;
        $otherSubstrate->forceFill([
            'slug' => 'other-substrate',
            'title' => 'Other substrate',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $otherSubstrate->save();

        $otherParticipant = new Participant;
        $otherParticipant->forceFill([
            'substrate_id' => $otherSubstrate->getKey(),
            'callsign' => 'other-observer',
        ]);
        $otherParticipant->save();

        $otherCycle = new Cycle;
        $otherCycle->forceFill([
            'participant_id' => $otherParticipant->getKey(),
            'sequence' => 1,
        ]);
        $otherCycle->save();

        $otherPhase = new Phase;
        $otherPhase->forceFill([
            'cycle_id' => $otherCycle->getKey(),
            'kind' => PhaseKind::Distinguish,
            'write_register' => 'activity',
            'started_at' => now(),
        ]);
        $otherPhase->save();

        $source = new Proposition;
        $source->forceFill([
            'phase_id' => $otherPhase->getKey(),
            'register' => 'activity',
            'tier' => 'expressive',
            'statement' => 'A proposition elsewhere.',
        ]);
        $source->save();

        $target = $this->proposition('A local proposition.');
        $supersession = new Supersession;
        $supersession->forceFill([
            'phase_id' => $this->phase->getKey(),
            'source_proposition_id' => $source->getKey(),
            'target_proposition_id' => $target->getKey(),
            'type' => 'variant',
        ]);
        $supersession->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'A contribution cannot cross substrate boundaries.'
        );

        app(LedgerService::class)->record($supersession);
    }

    public function test_commitments_create_horizons_only_on_direction_change(): void
    {
        [$origin, $originSnapshot] = $this->commit(
            'Origin.',
            prominence: 0,
            turbulence: 1,
        );

        $this->assertSame(
            ContextDomain::Emergence,
            $originSnapshot->direction_before,
        );
        $this->assertSame(
            ContextDomain::Settlement,
            $originSnapshot->direction_after,
        );
        $this->assertTrue($originSnapshot->isClosureBoundary());
        $this->assertSame(1, $this->horizonFor($origin)->sequence);
        $this->assertTrue(
            $origin->ledgerEntry->is(
                app(LedgerService::class)->record($origin),
            ),
        );

        [$zero, $zeroSnapshot] = $this->commit(
            'A neutral Settlement commitment.',
            prominence: 0.5,
            turbulence: 0.5,
            source: $origin,
        );

        $this->assertSame(
            ContextDomain::Settlement,
            $zeroSnapshot->direction_before,
        );
        $this->assertSame(
            ContextDomain::Settlement,
            $zeroSnapshot->direction_after,
        );
        $this->assertFalse($zeroSnapshot->isClosureBoundary());
        $this->assertSame(2, $this->horizonFor($zero)->sequence);

        [$crossing, $crossingSnapshot] = $this->commit(
            'A return to Emergence.',
            prominence: 0.75,
            turbulence: 0.25,
            source: $zero,
        );

        $this->assertSame(2, $this->horizonFor($crossing)->sequence);
        $this->assertSame(
            ContextDomain::Emergence,
            $crossingSnapshot->direction_after,
        );

        $horizons = ContextHorizon::query()
            ->oldest('sequence')
            ->get();

        $this->assertCount(3, $horizons);
        $this->assertSame(ContextDomain::Emergence, $horizons[0]->domain);
        $this->assertSame(
            $origin->getKey(),
            $horizons[0]->closed_by_proposition_id,
        );
        $this->assertSame(ContextDomain::Settlement, $horizons[1]->domain);
        $this->assertSame(
            $origin->getKey(),
            $horizons[1]->opened_by_proposition_id,
        );
        $this->assertSame(
            $crossing->getKey(),
            $horizons[1]->closed_by_proposition_id,
        );
        $this->assertSame(ContextDomain::Emergence, $horizons[2]->domain);
        $this->assertSame(
            $crossing->getKey(),
            $horizons[2]->opened_by_proposition_id,
        );
        $this->assertNull($horizons[2]->closed_at);
    }

    public function test_horizons_rebuild_without_rewriting_commitment_history(): void
    {
        [$origin, $snapshot] = $this->commit(
            'Origin.',
            prominence: 0,
            turbulence: 1,
        );
        [$next] = $this->commit(
            'Settlement continues.',
            prominence: 0.25,
            turbulence: 0.75,
            source: $origin,
        );

        app(PropositionMeasurementService::class)->project(
            $origin,
            correspondence: 1,
            chirality: 1,
            closure: 1,
        );

        ContextHorizonMembership::query()->delete();
        $horizonIds = ContextHorizon::query()->orderBy('sequence')->pluck('id')->all();

        $projector = app(ContextHorizonProjector::class);
        $current = $projector->currentFor($this->substrate);

        $this->assertSame(2, $current->sequence);
        $this->assertSame(2, ContextHorizon::query()->count());

        ContextHorizonMembership::query()->delete();
        $horizonIds = ContextHorizon::query()->orderBy('sequence')->pluck('id')->all();

        $count = $projector->rebuild($this->substrate);

        $this->assertSame(2, $count);
        $this->assertSame($horizonIds, ContextHorizon::query()->orderBy('sequence')->pluck('id')->all());
        $this->assertSame(1, $this->horizonFor($origin)->sequence);
        $this->assertSame(2, $this->horizonFor($next)->sequence);
        $this->assertSame(
            -0.5,
            CommitmentSnapshot::query()
                ->findOrFail($snapshot->proposition_id)
                ->correspondence,
        );
        $this->assertSame(
            ContextDomain::Settlement,
            ContextHorizon::query()
                ->whereNull('closed_at')
                ->sole()
                ->domain,
        );
    }

    private function proposition(
        string $statement,
        string $tier = 'expressive',
    ): Proposition {
        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $this->phase->getKey(),
            'register' => $this->phase->cycle->contextHorizon->domain->registerFor($tier === 'committal' ? PhaseKind::Order : PhaseKind::Distinguish),
            'tier' => $tier,
            'statement' => $statement,
        ]);
        $proposition->save();

        return $proposition;
    }

    /**
     * @return array{Proposition, CommitmentSnapshot}
     */
    private function commit(
        string $statement,
        float $prominence,
        float $turbulence,
        ?Proposition $source = null,
    ): array {
        // Each later commitment is a new Cycle; a Cycle never changes Domain.
        if ($source !== null) {
            $participant = $this->phase->cycle->participant;
            $cycle = new Cycle;
            $cycle->forceFill(['participant_id' => $participant->id,
                'sequence' => $participant->cycles()->max('sequence') + 1])->save();
            $this->phase = new Phase;
            $this->phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
                'write_register' => $cycle->contextHorizon->domain->registerFor(PhaseKind::Order), 'started_at' => now()])->save();
        }
        $proposition = $this->proposition($statement, 'committal');
        app(LedgerService::class)->record($proposition);
        $this->groundFixture($proposition);

        app(PropositionMeasurementService::class)->project(
            $proposition,
            correspondence: -0.5,
            chirality: 0.25,
            closure: -0.75,
        );

        $supersession = null;

        if ($source !== null) {
            $supersession = new Supersession;
            $supersession->forceFill([
                'phase_id' => $this->phase->getKey(),
                'source_proposition_id' => $source->getKey(),
                'target_proposition_id' => $proposition->getKey(),
                'type' => 'commit',
            ]);
            $supersession->save();
        }

        $snapshot = app(CommitmentSnapshotService::class)->record(
            $proposition,
            $prominence,
            $turbulence,
            'test-v1',
            $supersession,
        );

        return [$proposition, $snapshot];
    }

    private function horizonFor(Proposition $proposition): ContextHorizon
    {
        return ContextHorizonMembership::query()
            ->findOrFail($proposition->getKey())
            ->horizon;
    }
}
