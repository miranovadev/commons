<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\ChiralityGateService;
use App\Services\LedgerService;
use App\Services\Observables\ActiveCadenceLineageService;
use App\Services\Observables\AttestationBeatCursorService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class AttestationBeatContinuityTest extends TestCase
{
    use CreatesHomologousPositions, RefreshDatabase;
    use IsolatesClosureOutput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
    }

    private function start(): array
    {
        $this->createHomologyActor();
        $origin = $this->committedCoordinate(null, 'settlement', ['attestation_cadence_direction' => 'negative']);
        $parent = $this->child($origin, $origin);

        return [$origin, $parent];
    }

    private function child(Proposition $parent, Proposition $entry, bool $newCycle = true, string $type = 'variant'): Proposition
    {
        $phase = $newCycle ? $this->phase($entry, $parent, PhaseKind::Disperse) : $this->sameCyclePhase($parent);
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
            'tier' => 'expressive', 'statement' => 'Local continuation'])->save();
        app(LedgerService::class)->record($node);
        $this->link($parent, $node, $type);
        app(ProspectiveClosureService::class)->initialize($node);

        return $node;
    }

    private function sameCyclePhase(Proposition $node): Phase
    {
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $node->phase->cycle_id, 'kind' => PhaseKind::Disperse,
            'write_register' => $node->register, 'gate_proposition_id' => $node->id, 'started_at' => now()])->save();

        return $phase;
    }

    private function link(Proposition $source, Proposition $target, string $type): Supersession
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);

        return $edge;
    }

    private function act(Proposition $node, string $direction = 'support'): Attestation
    {
        return DB::transaction(function () use ($node, $direction) {
            $previous = Attestation::effective()->where('proposition_id', $node->id)->first();
            $act = new Attestation;
            $act->forceFill(['phase_id' => $this->sameCyclePhase($node)->id, 'proposition_id' => $node->id,
                'direction' => $direction, 'supersedes_attestation_id' => $previous?->id])->save();
            app(LedgerService::class)->record($act);

            return $act;
        });
    }

    private function intervals(Proposition $node): array
    {
        return json_decode(DB::table('local_chirality_states')->where('proposition_id', $node->id)->value('intervals'), true);
    }

    private function cursor(Proposition $node): ?int
    {
        $value = app(AttestationBeatCursorService::class)->for($node)?->beat_sequence;

        return $value === null ? null : (int) $value;
    }

    private function entries(Proposition $entry, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->phase($entry, $entry, PhaseKind::Distinguish);
        }
    }

    public function test_first_child_attestation_closes_interval_locally_without_inheriting_votes_or_samples(): void
    {
        $this->freezeTime(); // Ordering does not depend on timestamps.
        [$origin, $parent] = $this->start();
        $this->act($parent);
        $this->entries($origin, 2);
        $this->act($parent);
        $before = DB::table('local_chirality_states')->where('proposition_id', $parent->id)->first();
        $child = $this->child($parent, $origin); // First of five represented entries since the beat.
        $this->assertSame($this->cursor($parent), $this->cursor($child));
        $this->assertSame([], $this->intervals($child));
        $this->assertSame(0, $child->attestations()->count());
        $local = app(LocalMeasurementService::class)->chiralityComponents($child);
        $this->assertSame([0.0, 0.0, 0.0], [$local->orientation, $local->cadence, $local->average()]);
        $this->entries($origin, 4);
        $this->act($child);
        $this->assertSame([5], $this->intervals($child));
        $this->assertSame([2], $this->intervals($parent));
        $this->assertEquals($before, DB::table('local_chirality_states')->where('proposition_id', $parent->id)->first());
        $this->assertGreaterThan($this->cursor($parent), $this->cursor($child));
    }

    public static function hops(): array
    {
        return ['one unattested hop' => [1], 'multiple unattested hops' => [3]];
    }

    #[DataProvider('hops')]
    public function test_unattested_nodes_pass_the_same_cursor_without_extending_any_history(int $hops): void
    {
        [$origin, $parent] = $this->start();
        $this->act($parent);
        $beat = $this->cursor($parent);
        $chain = [$parent];
        for ($i = 0; $i < $hops; $i++) {
            $chain[] = $this->child($chain[array_key_last($chain)], $origin);
            $this->assertSame($beat, $this->cursor($chain[array_key_last($chain)]));
        }
        $last = $this->child($chain[array_key_last($chain)], $origin);
        $this->act($last);
        $this->assertSame([$hops + 1], $this->intervals($last));
        foreach ($chain as $node) {
            $this->assertSame([], $this->intervals($node));
            $this->assertSame($beat, $this->cursor($node));
        }
    }

    public function test_single_participant_same_cycle_zero_spacing_matures_positive_without_deduplication(): void
    {
        [$origin, $parent] = $this->start();
        $this->act($parent);
        $child = $this->child($parent, $origin, newCycle: false);
        $this->act($child);
        $this->assertSame([0], $this->intervals($child));
        $this->assertSame(0.0, app(LocalMeasurementService::class)->chiralityComponents($child)->cadence);
        $this->assertSame('negative', app(LocalMeasurementService::class)->chiralityComponents($child)->resolveDirections()['attestation_cadence_direction']);
        $this->act($child);
        $this->assertSame([0, 0], $this->intervals($child));
        $this->act($child);
        $this->assertSame([0, 0, 0], $this->intervals($child));
        $local = app(LocalMeasurementService::class)->chiralityComponents($child);
        $this->assertSame(1.0, $local->cadence);
        $this->assertSame('positive', $local->resolveDirections()['attestation_cadence_direction']);
        $this->assertDatabaseCount('participants', 1);
    }

    public function test_mixed_zero_intervals_keep_population_cv(): void
    {
        [$origin, $parent] = $this->start();
        $this->act($parent);
        $child = $this->child($parent, $origin, newCycle: false);
        $this->act($child);
        $this->entries($origin, 4);
        $this->act($child);
        $this->assertSame([0, 4], $this->intervals($child));
        $this->assertSame(0.0, app(LocalMeasurementService::class)->chiralityComponents($child)->cadence);
    }

    public static function reconcileOrders(): array
    {
        return ['acting edge first' => [false], 'other edge first' => [true]];
    }

    #[DataProvider('reconcileOrders')]
    public function test_reconcile_cannot_use_a_hard_superseded_gate_or_foreign_beat(bool $foreignFirst): void
    {
        [$origin, $local] = $this->start();
        $other = $this->child($origin, $origin);
        $this->act($local);
        $this->entries($origin, 2);
        $this->act($other, 'refute');
        $result = $this->reconcile($origin, $local, $other, $foreignFirst);
        $this->assertNull($this->cursor($result));
        $this->assertNotSame($this->cursor($other), $this->cursor($result));
        [$resolved] = app(LineageMeasurementService::class)->chirality($result);
        $this->assertSame(round((.25 / 4 - .25 / 4 - .5 / 9) / (1 + 1 / 4 + 1 / 4 + 1 / 9), 7), $resolved);
        $this->act($result);
        $this->assertSame([], $this->intervals($result));
        $this->assertSame([], $this->intervals($local));
        $this->assertSame([], $this->intervals($other));
        $this->assertEqualsCanonicalizing([$local->id, $other->id], $result->parents()->pluck('propositions.id')->all());
        $positions = app(LineageMeasurementService::class)->positions($result)->map(fn ($pair) => $pair[0]->id);
        $this->assertContains($other->id, $positions);
        $this->assertSame(1, app(LineageMeasurementService::class)->positions($result)
            ->first(fn ($pair) => $pair[0]->id === $other->id)[1]);
    }

    #[DataProvider('reconcileOrders')]
    public function test_reconcile_has_no_foreign_fallback_when_local_cursor_is_absent(bool $foreignFirst): void
    {
        [$origin, $local] = $this->start();
        $other = $this->child($origin, $origin);
        $this->act($other);
        $result = $this->reconcile($origin, $local, $other, $foreignFirst);
        $this->assertNull($this->cursor($result));
        $this->act($result);
        $this->assertSame([], $this->intervals($result));
        $this->assertSame(2, $result->parents()->count());
    }

    private function reconcile(Proposition $entry, Proposition $local, Proposition $other, bool $foreignFirst): Proposition
    {
        $phase = $this->phase($entry, $local, PhaseKind::Mediate);
        $result = new Proposition;
        $result->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
            'tier' => 'expressive', 'statement' => $local->statement])->save();
        app(LedgerService::class)->record($result);
        foreach ($foreignFirst ? [$other, $local] : [$local, $other] as $parent) {
            $this->link($parent, $result, 'reconcile');
        }
        app(ProspectiveClosureService::class)->initialize($result);

        return $result;
    }

    public function test_foreign_unary_source_uses_portable_beat_order_and_child_represented_entries_only(): void
    {
        [$origin, $ancestor] = $this->start();
        $this->act($ancestor);
        $source = $this->child($ancestor, $origin, newCycle: false);
        $this->act($source);
        $otherEntry = $this->committedCoordinate($origin, 'settlement');
        $this->entries($origin, 3); // These do not belong to the child's entry basin.
        $child = $this->child($source, $otherEntry, type: 'reclassify');
        $this->entries($otherEntry, 2);
        $this->act($child);
        $this->assertSame([3], $this->intervals($child));
        $this->assertSame([0], $this->intervals($source));
    }

    public function test_structural_progression_cannot_append_zero_intervals_or_rewrite_existing_cursors(): void
    {
        [$origin, $parent] = $this->start();
        $this->act($parent);
        $before = DB::table('local_chirality_states')->get()->toJson();
        $beat = $this->cursor($parent);
        $child = $this->child($parent, $origin, newCycle: false);
        $grandchild = $this->child($child, $origin, newCycle: false);
        $this->entries($origin, 3);
        foreach ([$parent, $child, $grandchild] as $node) {
            app(LocalMeasurementService::class)->chiralityComponents($node);
            $this->assertSame([], $this->intervals($node));
            $this->assertSame($beat, $this->cursor($node));
        }
        $this->assertSame($before, DB::table('local_chirality_states')->where('proposition_id', $parent->id)->get()->toJson());
        $this->act($parent, 'refute');
        $this->assertSame($beat, $this->cursor($child)); // Already-born branches do not search ancestry again.
        $this->assertSame($beat, $this->cursor($grandchild));
    }

    public function test_cursor_can_pass_through_a_committal_without_creating_local_measurement_state(): void
    {
        [$origin, $source] = $this->start();
        $this->act($source);
        $phase = $this->phase($origin, $source, PhaseKind::Order);
        $committal = new Proposition;
        $committal->forceFill(['phase_id' => $phase->id, 'tier' => 'committal',
            'register' => $phase->write_register, 'statement' => $source->statement])->save();
        app(LedgerService::class)->record($committal);
        $this->link($source, $committal, 'commit');
        $this->assertSame($this->cursor($source), $this->cursor($committal));
        $this->assertDatabaseMissing('local_chirality_states', ['proposition_id' => $committal->id]);
        $this->assertSame(0, $committal->attestations()->count());
    }

    private function state(Proposition $node): object
    {
        return DB::table('local_chirality_states')->where('proposition_id', $node->id)->first();
    }

    private function reference(Proposition $node): array
    {
        return json_decode($this->state($node)->cadence_reference, true, flags: JSON_THROW_ON_ERROR);
    }

    private function measuredIntervals(Proposition $node): array
    {
        return [...array_column($this->reference($node), 'interval'), ...$this->intervals($node)];
    }

    public static function hardReplacements(): array
    {
        return ['Revision' => ['revise'], 'Reclassification' => ['reclassify']];
    }

    #[DataProvider('hardReplacements')]
    public function test_normative_active_ancestry_keeps_event_ownership_and_historical_measurements(string $type): void
    {
        [$origin, $a] = $this->start();
        $this->act($a);
        foreach ([2, 3, 2] as $gap) {
            $this->entries($origin, $gap);
            $this->act($a);
        }
        $this->assertSame([2, 3, 2], $this->measuredIntervals($a));
        $aBefore = $this->state($a);
        $b = $this->child($a, $origin);
        $this->entries($origin, 2);
        $this->act($b);
        $this->assertSame([3], $this->intervals($b));
        $this->assertSame([3, 2, 3], $this->measuredIntervals($b));
        $bBefore = $this->state($b);
        $c = $this->child($b, $origin, type: $type); // Fourth entry since A's qualifying beat.
        $this->assertSame($this->cursor($a), $this->cursor($c));
        $this->act($c);
        $this->assertSame([4], $this->intervals($c));
        $this->assertSame([3, 2, 4], $this->measuredIntervals($c));
        $this->assertSame(0.5721225, (float) $this->state($c)->attestation_cadence);
        $reference = $this->reference($c);
        $this->entries($origin, 3);
        $this->act($c);
        $this->assertSame([4, 3], $this->intervals($c));
        $this->assertSame([3, 2, 4, 3], $this->measuredIntervals($c));
        $this->assertSame($reference, $this->reference($c));
        $this->assertSame([
            ['proposition_id' => $a->id, 'interval_index' => 1, 'interval' => 3],
            ['proposition_id' => $a->id, 'interval_index' => 2, 'interval' => 2],
        ], $reference);
        foreach ([[$b, [3, 2, 3]], [$c, [3, 2, 4, 3]]] as [$node, $samples]) {
            $mean = array_sum($samples) / count($samples);
            $cv = sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $samples)) / count($samples)) / $mean;
            $this->assertSame(round((1 - $cv) / (1 + $cv), 7), (float) $this->state($node)->attestation_cadence);
        }
        $this->assertEquals($aBefore, $this->state($a));
        $this->assertEquals($bBefore, $this->state($b));
        $this->assertSame([4, 1, 2], [$a->attestations()->count(), $b->attestations()->count(), $c->attestations()->count()]);
    }

    public function test_reference_crosses_short_active_positions_without_copying_their_references(): void
    {
        [$origin, $a] = $this->start();
        $this->act($a);
        $this->entries($origin, 4);
        $this->act($a);
        $b = $this->child($a, $origin);
        $this->act($b);
        $c = $this->child($b, $origin);
        $this->entries($origin, 1);
        $this->act($c);
        $d = $this->child($c, $origin);
        $this->act($d);
        $this->assertSame([1, 2, 1], $this->measuredIntervals($d));
        $this->assertSame([$b->id, $c->id], array_column($this->reference($d), 'proposition_id'));
        $this->assertSame([1], $this->intervals($d));
        $this->assertSame(1, $d->attestations()->count());
    }

    public function test_replacement_off_the_selected_route_is_excluded_without_following_its_new_branch(): void
    {
        [$origin, $a] = $this->start();
        $this->act($a);
        $this->entries($origin, 2);
        $this->act($a);
        $b = $this->child($a, $origin);
        $this->act($b);
        $c = $this->child($b, $origin);
        $replacement = $this->child($b, $origin, newCycle: false, type: 'revise');
        $this->act($replacement);
        $this->act($c);
        $this->assertSame([2, 2], $this->measuredIntervals($c));
        $this->assertSame([$a->id], array_column($this->reference($c), 'proposition_id'));
        $this->assertSame([2], $this->intervals($c), 'B and its replacement cannot supply the preceding beat');
    }

    #[DataProvider('reconcileOrders')]
    public function test_reconciliation_reference_follows_only_active_ancestry_of_the_acting_gate(bool $foreignFirst): void
    {
        [$origin, $a] = $this->start();
        $this->act($a);
        foreach ([2, 3] as $gap) {
            $this->entries($origin, $gap);
            $this->act($a);
        }
        $local = $this->child($a, $origin, newCycle: false);
        $this->act($local);
        $foreign = $this->child($origin, $origin, newCycle: false);
        $this->act($foreign);
        $this->act($foreign);
        $this->act($foreign);
        $result = $this->reconcile($origin, $local, $foreign, $foreignFirst);
        $this->assertSame($this->cursor($a), $this->cursor($result));
        $this->act($result);
        $this->assertSame([2, 3, 1], $this->measuredIntervals($result));
        $this->assertSame([$a->id, $a->id], array_column($this->reference($result), 'proposition_id'));
        $this->assertSame([0, 0], $this->intervals($foreign));
    }

    public function test_first_event_selects_current_active_context_then_retains_that_reference(): void
    {
        [$origin, $a] = $this->start();
        $this->act($a);
        $child = $this->child($a, $origin);
        $birthBeat = $this->cursor($child);
        $this->entries($origin, 2);
        $this->act($a);
        $this->assertSame($birthBeat, $this->cursor($child), 'Parent events cannot rewrite a born child');
        $this->act($child);
        $this->assertSame([3, 0], $this->measuredIntervals($child));
        $reference = $this->reference($child);
        $this->entries($origin, 2);
        $this->act($a);
        $this->act($child);
        $this->assertSame([3, 0, 2], $this->measuredIntervals($child));
        $this->assertSame($reference, $this->reference($child));
        $this->assertNotNull(app(AttestationBeatCursorService::class)->for($child)->source_supersession_id);
    }

    public function test_all_zero_historical_reference_is_real_and_matures_the_first_child_act(): void
    {
        [$origin, $a] = $this->start();
        $this->act($a);
        $this->act($a);
        $this->act($a);
        $child = $this->child($a, $origin, newCycle: false);
        $this->act($child, 'refute');
        $this->assertSame([0, 0, 0], $this->measuredIntervals($child));
        $this->assertSame([0], $this->intervals($child));
        $this->assertSame(1.0, (float) $this->state($child)->attestation_cadence);
        $this->assertSame('positive', $this->state($child)->attestation_cadence_direction);
        $this->assertSame(-0.5, (float) $this->state($child)->orientation, 'Orientation remains the child own effective lean');
        $measurement = app(PropositionMeasurementService::class)->recalculate($child);
        $this->assertSame(1.0, $measurement->attestation_cadence);
        $gate = app(ChiralityGateService::class)->evaluate($child);
        $this->assertFalse($gate['admissible'], 'Ordinary Bias still requires component agreement');
        $this->assertSame('negative', $gate['orientation_direction']);
        $this->assertSame('positive', $gate['attestation_cadence_direction']);
    }

    public function test_canonical_boundary_transports_only_the_existing_beat_not_pre_commit_intervals(): void
    {
        [$origin, $source] = $this->start();
        $this->act($source);
        foreach ([2, 3] as $gap) {
            $this->entries($origin, $gap);
            $this->act($source);
        }
        $phase = $this->phase($origin, $source, PhaseKind::Order);
        $commit = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => 'committal',
            'register' => $phase->write_register, 'statement' => 'Boundary']);
        $commit->save();
        app(LedgerService::class)->record($commit);
        $this->link($source, $commit, 'commit');
        // The canonical service/real Commit tests prove scalar preservation separately.
        $child = $this->child($commit, $origin);
        $this->act($child);
        $this->assertSame([], $this->reference($child));
        $this->assertSame([2], $this->intervals($child));
        $this->assertSame(0.0, (float) $this->state($child)->attestation_cadence);
        $this->assertDatabaseMissing('local_chirality_states', ['proposition_id' => $commit->id]);
        $this->assertSame(0, $commit->attestations()->count());
    }

    public function test_reference_selection_has_one_query_at_short_and_long_selected_lineages(): void
    {
        [$origin, $a] = $this->start();
        $this->act($a);
        $this->act($a);
        $this->act($a);
        $last = $a;
        $counts = [];
        $profile = [];
        $hops = 0;
        foreach ([2, 20] as $length) {
            $hops += $length;
            for ($i = 0; $i < $length; $i++) {
                $last = $this->child($last, $origin, newCycle: false);
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $selected = app(ActiveCadenceLineageService::class)->reference($last);
            $counts[] = count(DB::getQueryLog());
            $profile[] = ['unattested_hops' => $hops,
                'queries' => count(DB::getQueryLog()), 'sql_ms' => array_sum(array_column(DB::getQueryLog(), 'time'))];
            DB::disableQueryLog();
            $this->assertSame([0, 0], array_column($selected['reference'], 'interval'));
        }
        $this->assertSame([1, 1], $counts);
        // Once the nearest active predecessor owns two intervals, SQL must stop
        // there too; constant query count must not hide a full ancestry scan.
        $this->act($last);
        $this->act($last);
        $child = $this->child($last, $origin, newCycle: false);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $selected = app(ActiveCadenceLineageService::class)->reference($child);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertSame([$last->id, $last->id], array_column($selected['reference'], 'proposition_id'));
        $this->assertCount(2, DB::select($queries[0]['query'], $queries[0]['bindings']));
        $profile[] = ['nearest_predecessor_has_two_intervals' => true, 'queries' => 1,
            'returned_positions' => 2, 'sql_ms' => $queries[0]['time']];
        fwrite(STDERR, 'CADENCE_SELECTION_PERFORMANCE '.json_encode($profile, JSON_THROW_ON_ERROR).PHP_EOL);
    }

    public function test_cycle_completion_does_not_move_its_recorded_entry(): void
    {
        [, $parent] = $this->start();
        $cycle = $parent->phase->cycle;
        $position = $cycle->chirality_entry_after_sequence;
        $cycle->forceFill(['completed_at' => now()])->save();
        $this->assertSame($position, $cycle->fresh()->chirality_entry_after_sequence);
        $this->expectException(\DomainException::class);
        $cycle->forceFill(['chirality_entry_after_sequence' => $position + 1])->save();
    }
}
