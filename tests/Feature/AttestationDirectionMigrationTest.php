<?php

namespace Tests\Feature;

use App\Models\Attestation;
use App\Models\Participant;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\AttestationEvidenceService;
use App\Services\CycleEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttestationDirectionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_and_rollback_preserve_rows_references_and_region_meaning(): void
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'migration', 'title' => 'Migration', 'visibility' => 'public', 'configuration' => []])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'Creator'])->save();
        app(CycleEntryService::class)->enter($participant, [
            'statement' => 'Origin', 'attestation' => ['direction' => 'negative', 'rationale' => 'Keep this history'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin'],
        ]);
        $origin = Proposition::findOrFail($participant->fresh()->current_proposition_id);
        $first = $origin->attestations()->firstOrFail();
        $second = new Attestation;
        $second->forceFill(['phase_id' => $first->phase_id, 'proposition_id' => $origin->id,
            'direction' => 'positive', 'supersedes_attestation_id' => $first->id])->save();
        $rows = DB::table('attestations')->orderBy('id')->get()->toArray();
        $frames = DB::table('commit_region_polarities')->get()->toArray();
        $ledger = DB::table('substrate_ledger_entries')->orderBy('id')->get()->toArray();
        $value = app(AttestationEvidenceService::class)->valueFor(collect([$origin]), $substrate);
        $migration = require database_path('migrations/2026_09_14_180000_normalize_attestation_directions.php');
        $migration->down();
        $this->assertSame('refute', DB::table('attestations')->where('id', $first->id)->value('direction'));
        $this->assertSame('support', DB::table('attestations')->where('id', $second->id)->value('direction'));
        $migration->up();
        $this->assertEquals($rows, DB::table('attestations')->orderBy('id')->get()->toArray());
        $this->assertEquals($frames, DB::table('commit_region_polarities')->get()->toArray());
        $this->assertEquals($ledger, DB::table('substrate_ledger_entries')->orderBy('id')->get()->toArray());
        $this->assertSame($value, app(AttestationEvidenceService::class)->valueFor(collect([$origin->fresh()]), $substrate));
    }
}
