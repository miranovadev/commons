<?php

namespace Tests\Feature;

use App\Models\Receipt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ParticipationHttpTestCase;

#[Group('integration')]
class DownloadParticipationHttpTest extends ParticipationHttpTestCase
{
    public function test_human_http_journey_acquires_without_progression_then_generates_normally(): void
    {
        Storage::fake('artifacts');
        $this->startSubstrate();
        $actor = $this->joinParticipant('File investigator');
        $this->enterParticipant($actor, true);
        $persist = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'A distinction to investigate.']);
        $instrument = ['kind' => 'load_file', 'method' => 'download', 'reference' => 'https://evidence.example.test/file.txt?edition=2'];
        $action = ['action' => 'instrument.execute', 'proposition_id' => $persist['plan']['gate_id'], 'instrument' => $instrument];
        $this->postContribution($actor, $persist['plan']['phase']['id'], [...$action,
            'instrument' => [...$instrument, 'method' => 'upload']])->assertUnprocessable();
        $observed = $this->contribute($actor, $action);
        $this->assertSame($persist['plan']['phase'], $observed['plan']['phase']);
        $this->assertSame($persist['cycle'], $observed['cycle']);
        $receipt = Receipt::where('type', 'load_file')->sole();
        $item = collect($observed['plan']['receipts'])->firstWhere('id', $receipt->id);
        $this->assertFalse($item['capabilities']['readable']);
        $this->assertFalse($item['loaded']);
        $this->assertSame('download', $item['acquisition']['method']);
        $body = 'Deterministic external observation for '.$instrument['reference'];
        $this->assertSame($body, Storage::disk('artifacts')->get($receipt->metadata['artifacts'][0]['path']));
        $this->assertStringNotContainsString($body, json_encode($observed));
        $this->assertNull($receipt->consumption);
        $this->assertActionAvailable($observed, 'proposition.variant');
        $this->assertActionUnavailable($observed, 'instrument.execute');
        $constrain = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Develop the acquired observation.']);
        $this->assertSame('constrain', $constrain['plan']['phase']['kind']);
        $this->assertSame($persist['cycle']['id'], $constrain['cycle']['id']);
        Http::assertSentCount(1);
    }
}
