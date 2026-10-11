<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\LedgerService;
use App\Services\Observables\AttestationBeatCursorService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

class AttestationBeatContinuityTest extends TestCase
{
    use CreatesHomologousPositions, RefreshDatabase;

    private function start(): array
    {
        $this->createHomologyActor();
        $origin = $this->commit(null, 'settlement', ['attestation_cadence_direction' => 'negative']);
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
        app(\App\Services\Observables\ProspectiveClosureService::class)->initialize($node);

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
    public function test_reconcile_selects_local_cursor_even_when_other_beat_is_newer(bool $foreignFirst): void
    {
        [$origin, $local] = $this->start();
        $other = $this->child($origin, $origin);
        $this->act($local);
        $this->entries($origin, 2);
        $this->act($other, 'refute');
        $result = $this->reconcile($origin, $local, $other, $foreignFirst);
        $this->assertSame($this->cursor($local), $this->cursor($result));
        $this->assertNotSame($this->cursor($other), $this->cursor($result));
        [$resolved] = app(LineageMeasurementService::class)->chirality($result);
        $this->assertSame(round((.25 / 4 - .25 / 4 - .5 / 9) / (1 + 1 / 4 + 1 / 4 + 1 / 9), 7), $resolved);
        $this->act($result);
        $this->assertSame([3], $this->intervals($result));
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
        app(\App\Services\Observables\ProspectiveClosureService::class)->initialize($result);

        return $result;
    }

    public function test_foreign_unary_source_uses_portable_beat_order_and_child_represented_entries_only(): void
    {
        [$origin, $source] = $this->start();
        $this->act($source);
        $otherEntry = $this->commit($origin, 'settlement');
        $this->entries($origin, 3); // These do not belong to the child's entry basin.
        $child = $this->child($source, $otherEntry, type: 'reclassify');
        $this->entries($otherEntry, 2);
        $this->act($child);
        $this->assertSame([3], $this->intervals($child));
        $this->assertSame([], $this->intervals($source));
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
