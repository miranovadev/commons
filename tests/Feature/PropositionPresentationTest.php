<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection as Direction;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitRegionPolarityService;
use App\Services\CycleEntryService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PropositionPresentationTest extends TestCase
{
    use RefreshDatabase;

    private Participant $participant;

    private Phase $history;

    private Proposition $origin;

    private function bootstrap(string $label = 'support'): void
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'polarity', 'title' => 'Polarity', 'visibility' => 'private', 'configuration' => []])->save();
        $this->participant = new Participant;
        $this->participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer', 'max_read_count' => 25])->save();
        app(CycleEntryService::class)->enter($this->participant, [
            'statement' => 'Origin', 'attestation' => ['direction' => $label],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin'],
        ]);
        $this->origin = Proposition::findOrFail($this->participant->fresh()->current_proposition_id);
        $this->history = $this->origin->phase;
    }

    public static function labels(): array
    {
        return [['support', Direction::Positive], ['refute', Direction::Negative]];
    }

    #[DataProvider('labels')]
    public function test_participation_exposes_the_origin_frame_independently_of_numeric_measurements(string $label, Direction $support): void
    {
        $this->bootstrap($label);
        $plan = app(InputPlanService::class)->forParticipant($this->participant);
        $this->assertSame(['support_direction' => $support->value, 'positive' => 1, 'negative' => 0], $plan['gate']['attestation_summary']);
        $this->assertSame(1, $plan['gate']['attestation_counts'][$label]);
        $this->assertEquals(-1, $plan['gate']['observables']['chirality']);
    }

    public function test_display_counts_keep_inherited_evidence_in_its_original_frame_and_refresh_after_replacement(): void
    {
        $this->bootstrap();
        $source = $this->node('concord', $this->origin);
        $original = $this->attest($source, 'refute'); // Negative in the original positive-support region.
        $commit = $this->node('concord', $source, 'committal', 'commit');
        app(CommitRegionPolarityService::class)->establishCommit($commit, Direction::Negative);
        $this->attest($commit, 'refute'); // Positive in the new negative-support region.
        $phase = $this->phase(PhaseKind::Distinguish, 'signal', $commit);
        $presentation = app(PropositionPresentationService::class);
        $context = new ParticipationContext($phase);
        $expected = ['support_direction' => 'negative', 'positive' => 1, 'negative' => 1];
        $this->assertSame($expected, $presentation->attestations($commit));
        $this->assertSame($expected, $presentation->attestations($commit, $context));
        $this->attest($source, 'support', $original);
        $fresh = new ParticipationContext($phase);
        $this->assertSame(['support_direction' => 'negative', 'positive' => 2, 'negative' => 0], $presentation->attestations($commit, $fresh));
        $this->assertSame($expected, $presentation->attestations($commit, $context));
    }

    private function node(string $register, Proposition $parent, string $tier = 'expressive', string $type = 'variant'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->history->id, 'register' => $register, 'tier' => $tier, 'statement' => 'Node '.Proposition::count()])->save();
        app(LedgerService::class)->record($node);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $this->history->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $node->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);

        return $node;
    }

    private function phase(PhaseKind $kind, string $register, ?Proposition $gate = null, ?Participant $participant = null): Phase
    {
        $participant ??= $this->participant;
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register,
            'gate_proposition_id' => $gate?->id, 'started_at' => now()])->save();

        return $phase;
    }

    private function attest(Proposition $position, string $label, ?Attestation $supersedes = null): Attestation
    {
        $witness = $supersedes?->phase->cycle->participant;
        if ($witness === null) {
            $witness = new Participant;
            $witness->forceFill(['substrate_id' => $this->participant->substrate_id, 'callsign' => 'witness-'.Attestation::count()])->save();
        }
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $this->phase(PhaseKind::Persist, $position->register, participant: $witness)->id,
            'proposition_id' => $position->id, 'direction' => $label,
            'supersedes_attestation_id' => $supersedes?->id])->save();
        app(LedgerService::class)->record($attestation);

        return $attestation;
    }
}
