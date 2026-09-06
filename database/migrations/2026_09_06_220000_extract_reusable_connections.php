<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('provider', ['openai', 'anthropic']);
            $table->string('name');
            $table->jsonb('configuration')->default('{}');
            $table->text('credentials');
            $table->timestamps();
            $table->unique(['user_id', 'provider', 'name']);
            $table->index(['user_id', 'provider']);
        });

        Schema::table('participant_connections', function (Blueprint $table) {
            $table->foreignUlid('connection_id')->nullable()->constrained('connections')->cascadeOnDelete();
        });

        $legacyConnections = DB::table('participant_connections as participant_connections')
            ->leftJoin('participants', 'participants.id', '=', 'participant_connections.participant_id')
            ->select([
                'participant_connections.id', 'participant_connections.participant_id',
                'participant_connections.provider', 'participant_connections.name',
                'participant_connections.configuration', 'participant_connections.credentials',
                'participant_connections.created_at', 'participant_connections.updated_at',
                'participants.user_id', 'participants.callsign',
            ])->orderBy('participant_connections.created_at')->get();

        foreach ($legacyConnections as $legacy) {
            $configuration = $this->decodeConfiguration($legacy->configuration);
            $connectionId = (string) Str::ulid();
            $name = $this->uniqueConnectionName($legacy->user_id, $legacy->provider, $legacy->name, $legacy->callsign);

            DB::table('connections')->insert([
                'id' => $connectionId,
                'user_id' => $legacy->user_id,
                'provider' => $legacy->provider,
                'name' => $name,
                'configuration' => json_encode(Arr::only($configuration, ['model', 'instructions']), JSON_THROW_ON_ERROR),
                // Copy the encrypted payload without decrypting it in a migration.
                'credentials' => $legacy->credentials,
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->updated_at,
            ]);

            DB::table('participant_connections')->where('id', $legacy->id)->update([
                'connection_id' => $connectionId,
                'configuration' => json_encode([
                    'trigger' => data_get($configuration, 'trigger', ['mode' => 'manual']),
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        Schema::table('participant_connections', function (Blueprint $table) {
            $table->dropUnique(['participant_id', 'provider', 'name']);
            $table->dropIndex(['provider', 'is_enabled']);
            $table->dropColumn(['provider', 'name', 'credentials']);
            $table->unique(['participant_id', 'connection_id']);
        });
    }

    public function down(): void
    {
        Schema::table('participant_connections', function (Blueprint $table) {
            $table->enum('provider', ['openai', 'anthropic'])->nullable();
            $table->string('name')->nullable();
            $table->text('credentials')->nullable();
        });

        $links = DB::table('participant_connections as participant_connections')
            ->join('connections', 'connections.id', '=', 'participant_connections.connection_id')
            ->select([
                'participant_connections.id',
                'participant_connections.configuration as wake_configuration',
                'connections.provider', 'connections.name',
                'connections.configuration as connection_configuration',
                'connections.credentials',
            ])->get();

        foreach ($links as $link) {
            $connectionConfiguration = $this->decodeConfiguration($link->connection_configuration);
            $wakeConfiguration = $this->decodeConfiguration($link->wake_configuration);
            DB::table('participant_connections')->where('id', $link->id)->update([
                'provider' => $link->provider,
                'name' => $link->name,
                'credentials' => $link->credentials,
                'configuration' => json_encode(array_replace_recursive($connectionConfiguration, $wakeConfiguration), JSON_THROW_ON_ERROR),
            ]);
        }

        Schema::table('participant_connections', function (Blueprint $table) {
            $table->dropUnique(['participant_id', 'connection_id']);
            $table->dropConstrainedForeignId('connection_id');
            $table->unique(['participant_id', 'provider', 'name']);
            $table->index(['provider', 'is_enabled']);
        });

        Schema::dropIfExists('connections');
    }

    /** @return array<string, mixed> */
    private function decodeConfiguration(mixed $configuration): array
    {
        if (is_array($configuration)) return $configuration;
        if (! is_string($configuration) || $configuration === '') return [];
        return json_decode($configuration, true, flags: JSON_THROW_ON_ERROR);
    }

    private function uniqueConnectionName(?int $userId, string $provider, string $name, ?string $callsign): string
    {
        $candidate = $name;
        $suffix = $callsign === null ? 'legacy' : $callsign;
        $attempt = 1;
        while (DB::table('connections')->where('user_id', $userId)->where('provider', $provider)->where('name', $candidate)->exists()) {
            $attempt++;
            $candidate = "{$name} ({$suffix}".($attempt > 2 ? " {$attempt}" : '').')';
        }
        return $candidate;
    }
};
