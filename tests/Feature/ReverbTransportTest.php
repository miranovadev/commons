<?php

namespace Tests\Feature;

use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReverbTransportTest extends TestCase
{
    public function test_configured_reverb_delivers_identifier_notifications_to_a_real_websocket_client(): void
    {
        if (! is_file(base_path('node_modules/pusher-js/package.json'))) {
            $this->markTestSkipped('Run npm ci to enable the real Reverb transport smoke test.');
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $address = stream_socket_get_name($socket, false);
        $port = (int) substr($address, strrpos($address, ':') + 1);
        fclose($socket);

        $server = new Process([PHP_BINARY, 'artisan', 'reverb:start', '--host=127.0.0.1', '--port='.$port], base_path(), [
            'REVERB_APP_ID' => 'commons-test', 'REVERB_APP_KEY' => 'commons-test-key',
            'REVERB_APP_SECRET' => 'commons-test-secret', 'REVERB_HOST' => '127.0.0.1',
            'REVERB_PORT' => (string) $port, 'REVERB_SCHEME' => 'http',
        ]);
        $client = new Process(['node', 'tests/reverb-client.mjs', (string) $port], base_path());
        $client->setTimeout(25);

        try {
            $server->start();
            $this->assertTrue($server->waitUntil(fn () => str_contains($server->getOutput(), 'Starting server')), $server->getErrorOutput());
            $client->start();
            $subscribed = $client->waitUntil(fn () => str_contains($client->getOutput(), 'SUBSCRIBED'));
            $this->assertTrue($subscribed, $server->getOutput().$server->getErrorOutput().$client->getErrorOutput());

            config(['broadcasting.connections.reverb' => [
                'driver' => 'reverb', 'app_id' => 'commons-test', 'key' => 'commons-test-key', 'secret' => 'commons-test-secret',
                'options' => ['host' => '127.0.0.1', 'port' => $port, 'scheme' => 'http', 'useTLS' => false],
                'client_options' => ['timeout' => 5],
            ]]);
            $payload = ['type' => 'proposition.created', 'proposition_id' => 'proposition-test', 'context_horizon_id' => 'horizon-test'];
            Broadcast::connection('reverb')->broadcast([new Channel('transport-test')], 'invalidation', $payload);
            $client->wait();
            $this->assertTrue($client->isSuccessful(), $client->getErrorOutput());
            $line = collect(explode("\n", $client->getOutput()))->first(fn ($line) => str_starts_with($line, 'RECEIVED '));
            $this->assertNotNull($line);
            $this->assertSame($payload, json_decode(substr($line, 9), true, flags: JSON_THROW_ON_ERROR));
        } finally {
            $client->stop();
            $server->stop();
        }
    }
}
