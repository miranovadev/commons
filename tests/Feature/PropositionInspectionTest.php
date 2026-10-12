<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\LedgerService;
use App\Services\PropositionInspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[\PHPUnit\Framework\Attributes\Group('component-contract')]
class PropositionInspectionTest extends TestCase
{
    use \Tests\Fixtures\IsolatesClosureOutput;

    use CreatesHomologousPositions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
        $this->createHomologyActor();
    }

    public function test_only_committals_opening_verified_child_horizons_have_transition_markers(): void
    {
        $origin = $this->commit(null, 'emergence');
        $down = $this->commit($origin, 'settlement');
        $same = $this->commit($down, 'settlement');
        $up = $this->commit($same, 'emergence');
        $expressive = $this->position($down);
        $expressive->setAttribute('field', ['tension' => 1]);
        $nodes = collect([$origin, $down, $same, $up, $expressive]);
        app(PropositionInspectionService::class)->loadMarkers($this->actor->substrate, $nodes);
        $this->assertNull($origin->domain_transition);
        $this->assertSame('emergence-settlement', $down->domain_transition);
        $this->assertNull($same->domain_transition);
        $this->assertSame('settlement-emergence', $up->domain_transition);
        $this->assertNull($expressive->domain_transition);
        // No marker from a snapshot direction alone without matching committed topology.
        DB::table('commitment_snapshots')->where('proposition_id', $same->id)->update(['direction_after' => 'emergence']);
        app(PropositionInspectionService::class)->loadMarkers($this->actor->substrate, collect([$same]));
        $this->assertNull($same->domain_transition);
    }

    public function test_recorded_foreign_mediate_classification_is_confluence_but_same_region_is_not(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $this->commit($origin, 'settlement');
        $b = $this->commit($origin, 'settlement');
        $foreign = $this->position($a, PhaseKind::Mediate);
        $local = $this->position($b, PhaseKind::Mediate);
        $incoming = $this->classification($b, $foreign, $local);
        $ordinary = $this->classification($b, $local, $local);
        $nodes = collect([$foreign, $local, $incoming, $ordinary]);
        app(PropositionInspectionService::class)->loadMarkers($this->actor->substrate, $nodes);
        $this->assertTrue($incoming->confluence);
        $this->assertFalse($ordinary->confluence);
        $this->assertFalse($foreign->confluence, 'Mediate itself is not a Bias event');
        DB::table('reclassification_results')->where('target_proposition_id', $incoming->id)->delete();
        app(PropositionInspectionService::class)->loadMarkers($this->actor->substrate, collect([$incoming]));
        $this->assertFalse($incoming->confluence, 'An edge without recorded classification is insufficient');
    }

    public function test_local_bias_retains_exact_foreign_reconcile_operand_provenance(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $this->commit($origin, 'settlement');
        $b = $this->commit($origin, 'settlement');
        $gate = $this->position($a, PhaseKind::Disperse);
        $other = $this->position($b, PhaseKind::Disperse);
        $phase = $this->phase($a, $gate, PhaseKind::Mediate);
        $mediated = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $phase->write_register, 'statement' => 'Recorded incoming operand']);
        $mediated->save();
        app(LedgerService::class)->record($mediated);
        $this->edge($gate, $mediated, 'reconcile');
        $incoming = $this->edge($other, $mediated, 'reconcile');
        $bias = $this->classification($a, $mediated, $mediated);
        app(PropositionInspectionService::class)->loadMarkers($this->actor->substrate, collect([$bias, $mediated]));
        $this->assertTrue($bias->confluence);
        $this->assertFalse($mediated->confluence);
        DB::table('supersessions')->where('id', $incoming->id)->update(['type' => 'variant']);
        app(PropositionInspectionService::class)->loadMarkers($this->actor->substrate, collect([$bias]));
        $this->assertFalse($bias->confluence, 'Two arbitrary parents are not recorded mediation');
    }

    public function test_same_region_reconcile_and_bias_do_not_receive_confluence(): void
    {
        $origin = $this->commit(null, 'emergence');
        $gate = $this->position($origin, PhaseKind::Disperse);
        $other = $this->position($origin, PhaseKind::Disperse);
        $phase = $this->phase($origin, $gate, PhaseKind::Mediate);
        $mediated = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $phase->write_register, 'statement' => 'Local operands']);
        $mediated->save();
        app(LedgerService::class)->record($mediated);
        $this->edge($gate, $mediated, 'reconcile');
        $this->edge($other, $mediated, 'reconcile');
        $bias = $this->classification($origin, $mediated, $mediated);
        app(PropositionInspectionService::class)->loadMarkers($this->actor->substrate, collect([$bias]));
        $this->assertFalse($bias->confluence);
    }

    public function test_closure_history_follows_entries_not_timestamp_or_siblings_and_preserves_zero_direction(): void
    {
        $origin = $this->commit(null, 'emergence', ['depth' => -1, 'cadence' => -1,
            'depth_direction' => 'negative', 'cadence_direction' => 'negative']);
        $a = $this->commit($origin, 'settlement', ['depth' => 0, 'cadence' => 0,
            'depth_direction' => 'negative', 'cadence_direction' => 'negative']);
        $sibling = $this->commit($origin, 'settlement', ['depth' => 1, 'cadence' => 1,
            'depth_direction' => 'positive', 'cadence_direction' => 'positive']);
        $b = $this->commit($a, 'settlement', ['depth' => .4, 'cadence' => .2,
            'depth_direction' => 'positive', 'cadence_direction' => 'positive']);
        CommitmentSnapshot::query()->update(['evaluated_at' => now()]);
        $before = CommitmentSnapshot::all()->map->getAttributes()->all();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $history = app(PropositionInspectionService::class)->history($b);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $this->assertSame([$origin->id, $a->id, $b->id], array_column($history['points'], 'proposition_id'));
        $this->assertNotContains($sibling->id, array_column($history['points'], 'proposition_id'));
        $this->assertSame(0.0, $history['points'][1]['depth']);
        $this->assertSame('negative', $history['points'][1]['depth_direction']);
        $this->assertSame('negative', $history['points'][1]['cadence_direction']);
        $this->assertSame($b->id, $history['current']['proposition_id']);
        $this->assertSame($before, CommitmentSnapshot::all()->map->getAttributes()->all());
        $this->assertFalse($queries->contains(fn ($query) => preg_match('/^\s*(insert|update|delete)/i', $query) === 1));
    }

    public function test_missing_historical_values_remain_missing(): void
    {
        $origin = $this->commit(null, 'emergence', ['depth' => null, 'cadence' => null,
            'depth_direction' => null, 'cadence_direction' => null]);
        $history = app(PropositionInspectionService::class)->history($origin);
        $this->assertNull($history['current']['depth']);
        $this->assertNull($history['current']['depth_direction']);
    }

    private function classification(Proposition $entry, Proposition $source, Proposition $gate): Proposition
    {
        $phase = $this->phase($entry, $gate, PhaseKind::Bias);
        $target = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $phase->write_register, 'statement' => 'Recorded classification']);
        $target->save();
        app(LedgerService::class)->record($target);
        $this->edge($source, $target, 'reclassify');
        DB::table('reclassification_results')->insert(['source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'register' => $target->register]);

        return $target;
    }

    private function edge(Proposition $source, Proposition $target, string $type): Supersession
    {
        $edge = (new Supersession)->forceFill(['phase_id' => $target->phase_id,
            'source_proposition_id' => $source->id, 'target_proposition_id' => $target->id, 'type' => $type]);
        $edge->save();
        app(LedgerService::class)->record($edge);

        return $edge;
    }
}
