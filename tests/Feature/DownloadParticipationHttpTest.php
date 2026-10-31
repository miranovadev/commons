<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\Receipt;
use Illuminate\Http\Client\Factory;
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
        $this->discoverGate($actor);
        $persist = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'A distinction to investigate.']);
        $distinction = $persist['current_proposition_id'];
        $this->contribute($actor, ['action' => 'participant.rest']);
        $persist = $this->judge($actor, $distinction);
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

    public function test_http_download_consumption_adaptive_reading_and_deferment_share_one_lifecycle(): void
    {
        Storage::fake('artifacts');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://evidence.example.test/*' => Http::response("name,value\nresearch,42\n", 200, ['Content-Type' => 'text/csv'])]);
        $this->startSubstrate();
        $actor = $this->joinParticipant('CSV reader');
        $this->enterParticipant($actor, true);
        $this->discoverGate($actor);
        $persist = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Investigate the dataset.']);
        $distinction = $persist['current_proposition_id'];
        $this->contribute($actor, ['action' => 'participant.rest']);
        $persist = $this->judge($actor, $distinction);
        $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $persist['plan']['gate_id'],
            'instrument' => ['kind' => 'load_file', 'method' => 'download', 'reference' => 'https://evidence.example.test/data.csv']]);
        $source = Receipt::where('type', 'load_file')->sole();
        $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Develop the observed dataset.']);
        $disperse = $this->driveUntil($actor, fn ($state) => $state['plan']['phase']['kind'] === 'disperse', 5, $source->id);
        $this->assertNotNull($source->fresh()->consumption);
        $option = collect($disperse['plan']['preparation_options'])->firstWhere('receipt_id', $source->id);
        $this->assertSame(['raw', 'text', 'tables'], array_column($option['capabilities'], 'read_type'));
        config(['preparation.text_budget' => 31]);
        $started = $this->contribute($actor, ['action' => 'preparation.start', 'proposition_id' => $disperse['plan']['gate_id'],
            'purpose' => 'Compare the original CSV with its table representation.', 'artifacts' => [
                ['receipt_id' => $source->id, 'artifact_id' => $option['artifact_id'], 'read_type' => 'raw', 'parameters' => ['offset' => 2, 'max_chars' => 10]],
                ['receipt_id' => $source->id, 'artifact_id' => $option['artifact_id'], 'read_type' => 'tables'],
            ]]);
        $this->assertSame($disperse['plan']['phase'], $started['plan']['phase']);
        $id = $started['plan']['preparation']['execution_id'];
        $url = '/api/substrates/'.$this->substrateSlug.'/participants/'.$actor.'/preparations/'.$id;
        $delivery = $this->getJson($url.'/reading')->assertOk()->json('data');
        $this->assertSame([10, 21], array_column(array_column($delivery['deliveries'], 'boundaries'), 'length'));
        $this->assertSame('me,value'."\nr", $delivery['deliveries'][0]['content']);
        $this->postJson($url.'/presented', ['presentation_token' => $delivery['presentation_token']])->assertOk();
        $resolved = $this->contribute($actor, ['action' => 'preparation.defer', 'execution_id' => $id, 'reason' => 'Inspect the remaining rows later.']);
        $this->assertNotSame($disperse['cycle']['id'], $resolved['cycle']['id']);
        $this->assertDatabaseHas('cycles', ['id' => $disperse['cycle']['id'], 'rested_at' => null]);
        $this->assertNotNull(Cycle::findOrFail($disperse['cycle']['id'])->deferred_at);
        $this->assertDatabaseCount('exploration_events', 0);
        $receipt = Receipt::where('type', 'preparation')->sole();
        $this->assertNull($receipt->consumption);
        $this->assertSame('deferred', $receipt->metadata['outcome']['type']);
        $this->assertSame(31, array_sum(array_column(array_column($receipt->metadata['deliveries'], 'boundaries'), 'length')));
        $this->assertStringNotContainsString('research,42', json_encode($receipt->metadata));
        $this->assertActionUnavailable($resolved, 'proposition.variant');
        $this->assertSame('not_required', $resolved['plan']['acquisition']['status']);
        $this->assertActionAvailable($resolved, 'proposition.traverse');
        Http::assertSentCount(1);
    }
}
