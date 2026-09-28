<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\LedgerService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommitmentSnapshotFixtureTest extends TestCase
{
    use RefreshDatabase;

    private Cycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'snapshot-fixture', 'title' => 'Snapshot fixture',
            'visibility' => 'private', 'configuration' => []])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'author'])->save();
        $this->cycle = new Cycle;
        $this->cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1])->save();
    }

    public function test_origin_is_the_unique_parentless_snapshot_exception(): void
    {
        $origin = $this->committal();
        $this->freezeCommittedRegionFixture($origin);
        $this->assertNull($origin->fresh()->commitmentSnapshot->supersession_id);

        $ordinary = $this->committal($origin);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('exactly one genuine Commit parent');
        $this->freezeCommittedRegionFixture($ordinary);
    }

    public function test_snapshot_links_the_unique_commit_and_repeated_freezing_preserves_it(): void
    {
        $origin = $this->committal();
        $this->freezeCommittedRegionFixture($origin);
        $ordinary = $this->committal($origin);
        $commit = $this->commitAncestryFixture($ordinary, $origin);
        $this->freezeCommittedRegionFixture($ordinary);
        $snapshot = $ordinary->fresh()->commitmentSnapshot;
        $this->assertSame($commit->id, $snapshot->supersession_id);
        $this->assertSame($ordinary->id, $snapshot->supersession->target_proposition_id);
        $this->assertSame($origin->id, $snapshot->supersession->source_proposition_id);
        $before = $snapshot->getAttributes();
        $this->freezeCommittedRegionFixture($ordinary, .9);
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
    }

    public static function invalidParents(): array
    {
        return ['missing' => [[]], 'wrong operation' => [['variant']],
            'ambiguous commits' => [['commit', 'commit']]];
    }

    #[DataProvider('invalidParents')]
    public function test_incomplete_or_ambiguous_ancestry_cannot_publish_a_snapshot(array $types): void
    {
        $origin = $this->committal();
        $other = $this->committal($origin);
        $this->commitAncestryFixture($other, $origin);
        $ordinary = $this->committal($origin);
        foreach ($types as $index => $type) {
            $edge = new Supersession;
            $edge->forceFill(['phase_id' => $ordinary->phase_id,
                'source_proposition_id' => $index === 0 ? $origin->id : $other->id,
                'target_proposition_id' => $ordinary->id, 'type' => $type])->save();
            app(LedgerService::class)->record($edge);
        }
        try {
            $this->freezeCommittedRegionFixture($ordinary);
            $this->fail('Invalid ancestry must not be frozen.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('exactly one genuine Commit parent', $error->getMessage());
        }
        $this->assertNull($ordinary->fresh()->commitmentSnapshot);
    }

    public function test_a_commit_from_a_different_selected_gate_is_rejected(): void
    {
        $origin = $this->committal();
        $other = $this->committal($origin);
        $this->commitAncestryFixture($other, $origin);
        $ordinary = $this->committal($origin);
        $this->commitAncestryFixture($ordinary, $origin);
        $ordinary->phase->forceFill(['gate_proposition_id' => $other->id])->save();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('producing Phase and selected source');
        $this->freezeCommittedRegionFixture($ordinary);
    }

    public function test_an_existing_unlinked_snapshot_is_rejected_without_rewriting_history(): void
    {
        $origin = $this->committal();
        $ordinary = $this->committal($origin);
        $this->commitAncestryFixture($ordinary, $origin);
        // Explicitly simulate the historical defect under review.
        $this->freezeRegionFixture($ordinary);
        $snapshot = $ordinary->fresh()->commitmentSnapshot;
        $before = $snapshot->getAttributes();
        try {
            $this->freezeCommittedRegionFixture($ordinary);
            $this->fail('An immutable snapshot must not be silently repaired.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('does not identify its genuine Commit', $error->getMessage());
        }
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
    }

    private function committal(?Proposition $gate = null): Proposition
    {
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $this->cycle->id, 'kind' => PhaseKind::Order,
            'gate_proposition_id' => $gate?->id, 'write_register' => 'canon', 'started_at' => now()])->save();
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'committal',
            'register' => 'canon', 'statement' => 'Fixture Committal'])->save();
        app(LedgerService::class)->record($node);

        return $node;
    }
}
