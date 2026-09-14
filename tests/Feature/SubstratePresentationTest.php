<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Substrate;
use App\Models\User;
use App\Services\CycleEntryService;
use App\Services\InputPlanService;
use App\Support\SubstratePresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubstratePresentationTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $this->postJson('/api/bootstrap', [
            'name' => 'System', 'email' => 'system@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ])->assertCreated();

        return User::query()->firstOrFail();
    }

    public function test_creation_defaults_and_partial_updates_preserve_other_configuration(): void
    {
        $this->administrator();
        $this->postJson('/api/substrates', [
            'slug' => 'custom', 'title' => 'Custom', 'visibility' => 'public',
            'configuration' => ['maximum_participants' => 7],
        ])->assertCreated()->assertJsonPath('data.configuration.presentation', SubstratePresentation::DEFAULTS);

        $this->patchJson('/api/substrates/custom', ['configuration' => ['presentation' => [
            'primary_color' => 'orange', 'positive' => ['label' => 'Agree', 'color' => 'green'],
            'negative' => ['label' => 'Challenge', 'color' => 'red'],
        ]]])->assertOk()->assertJsonPath('data.configuration.maximum_participants', 7)
            ->assertJsonPath('data.configuration.presentation.negative.label', 'Challenge');
        $this->patchJson('/api/substrates/custom', ['configuration' => ['presentation' => [
            'positive' => ['label' => 'Concur'],
        ]]])->assertOk()->assertJsonPath('data.configuration.presentation.positive.color', 'green')
            ->assertJsonPath('data.configuration.presentation.primary_color', 'orange');
        $this->getJson('/api/substrates')->assertOk()
            ->assertJsonPath('data.0.configuration.presentation.positive.label', 'Concur');
    }

    public function test_creation_accepts_palette_settings_and_rejects_invalid_values(): void
    {
        $this->administrator();
        $input = ['slug' => 'custom', 'title' => 'Custom', 'visibility' => 'public',
            'configuration' => ['presentation' => ['primary_color' => 'grape', 'negative' => ['label' => 'Disagree', 'color' => 'cyan']]]];
        $this->postJson('/api/substrates', $input)->assertCreated()
            ->assertJsonPath('data.configuration.presentation.positive.label', 'Support')
            ->assertJsonPath('data.configuration.presentation.negative.color', 'cyan');

        foreach ([['primary_color' => '#ff0000'], ['positive' => ['label' => '   ']],
            ['negative' => ['color' => 'not-a-palette']], ['negative' => ['label' => str_repeat('x', 41)]],
            ['positive' => ['unexpected' => true]], ['unexpected' => true]] as $presentation) {
            $this->patchJson('/api/substrates/custom', ['configuration' => ['presentation' => $presentation]])->assertUnprocessable();
            $this->postJson('/api/substrates', array_replace($input, ['slug' => 'invalid', 'configuration' => ['presentation' => $presentation]]))->assertUnprocessable();
        }
        $this->patchJson('/api/substrates/custom', ['configuration' => ['maximum_participants' => 1]])->assertUnprocessable();
    }

    public function test_legacy_substrates_have_defaults_and_ordinary_members_cannot_change_them(): void
    {
        $system = $this->administrator();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'legacy', 'title' => 'Legacy', 'visibility' => 'public', 'configuration' => []])->save();
        $this->actingAs(User::factory()->create())->getJson('/api/substrates/legacy')
            ->assertOk()->assertJsonPath('data.configuration.presentation', SubstratePresentation::DEFAULTS)
            ->assertJsonPath('data.can_update', false);
        $this->patchJson('/api/substrates/legacy', ['configuration' => ['presentation' => ['primary_color' => 'red']]])->assertForbidden();
        $this->actingAs($system)->getJson('/api/substrates/legacy')->assertJsonPath('data.can_update', true);
        $this->assertSame([], $substrate->fresh()->configuration);
    }

    public function test_specialized_schemas_expose_labels_without_changing_directions_or_leaking_between_substrates(): void
    {
        config(['schemas.cache.store' => 'array']);
        foreach (['first' => 'Concur', 'second' => 'Accept'] as $slug => $label) {
            $substrate = new Substrate;
            $substrate->forceFill(['slug' => $slug, 'title' => $slug, 'visibility' => 'public',
                'configuration' => ['presentation' => ['positive' => ['label' => $label]]]])->save();
            $participant = new Participant;
            $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => $slug])->save();
            app(CycleEntryService::class)->enter($participant, [
                'statement' => 'Origin', 'attestation' => ['direction' => 'positive'],
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin'],
            ]);
            $schema = app(InputPlanService::class)->forParticipant($participant)['schema'];
            $this->assertSame(['positive' => $label, 'negative' => 'Refute'], $schema['x-commons']['attestation_labels']);
            $this->assertSame(['positive', 'negative'], config('schemas.actions')['attestation.create']['properties']['direction']['enum']);
        }
    }
}
