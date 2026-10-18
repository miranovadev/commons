<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Models\ParticipantReceiptContext;
use App\Services\InstrumentExecutionService;
use App\Services\MediationReadService;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\Support\RefreshIntegrationDatabase as RefreshDatabase;
use Tests\TestCase;

#[Group('integration')]
class ReceiptPhaseBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public static function desiredStates(): array
    {
        return [['load', 'gate'], ['unload', 'gate'], ['load', 'write'], ['unload', 'write']];
    }

    #[DataProvider('desiredStates')]
    public function test_receipt_changes_accompany_one_normal_contribution_and_affect_the_next_context(string $desired, string $role): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $anchor = app(MediationReadService::class)->anchorFor($f->phase);
        $f->phase->forceFill(['kind' => PhaseKind::Disperse, 'gate_proposition_id' => $anchor->id, 'write_register' => 'activity'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $anchor->id])->save();
        $executions = app(InstrumentExecutionService::class);
        $subject = $role === 'gate' ? $anchor : $f->gate;
        $r = $executions->complete($executions->start($f->phase, $subject, 'hyperlink', 'https://example.test/boundary'), $subject,
            verification: new VerifiedHyperlink('example.test/boundary', hash('sha256', 'boundary'), CarbonImmutable::now(), contentExcerpt: 'BOUNDARY-BODY'));
        // The existing Distinguish/Disperse prerequisite requires this Write's own observation.
        $executions->complete($executions->start($f->phase, $f->gate, 'hyperlink', 'https://example.test/write'), $f->gate,
            verification: new VerifiedHyperlink('example.test/write', hash('sha256', 'write'), CarbonImmutable::now()));
        if ($desired === 'load') {
            app(ReceiptContextService::class)->clear($f->viewer, $f->phase, $r->id);
        }
        $this->publishPotentialFixture($f->viewer);
        $service = app(ParticipationService::class);
        $before = $service->stateFor($f->viewer);
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $before);
        $this->assertSame($desired === 'unload' ? 1 : 0, substr_count($prompt['user'], 'BOUNDARY-BODY')); // Loaded Gate and Write evidence are visible before unloading.
        $phaseCount = DB::table('phases')->count();
        $eventCount = DB::table('receipt_context_events')->count();
        $after = $service->contribute($f->viewer, $f->phase->id, ['action' => 'attestation.create',
            'proposition_id' => $f->gate->id, 'direction' => 'support', 'receipt_options' => [$desired => [$r->id]]]);
        $this->assertSame('mediate', $after['plan']['phase']['kind']);
        $this->assertSame($phaseCount + 1, DB::table('phases')->count());
        $this->assertSame($eventCount + 1, DB::table('receipt_context_events')->count());
        $this->assertSame([$anchor->id], array_column($after['plan']['read_options'], 'id'));
        $this->assertSame($desired === 'load' ? 1 : 0, ParticipantReceiptContext::active()->where('receipt_id', $r->id)->count());
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $after);
        $this->assertSame($desired === 'load' ? 1 : 0, substr_count($prompt['user'].$prompt['evidence'], 'BOUNDARY-BODY'));
        $this->assertNotNull($f->phase->fresh()->completed_at);
    }
}
