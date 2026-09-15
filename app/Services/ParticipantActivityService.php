<?php

namespace App\Services;

use App\Models\Participant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Ephemeral runtime observation. Never changes Cycle, position, or eligibility. */
final readonly class ParticipantActivityService
{
    public function __construct(private RealtimePublisher $realtime) {}

    public function begin(Participant $participant, int $timeout): string
    {
        $token = (string) Str::uuid();
        $deadline = now()->timestamp + $timeout + 30;
        $this->locked($participant->id, function () use ($participant, $token, $deadline): void {
            $this->put($participant->id, ['token' => $token, 'started_at' => now()->toIso8601String(), 'deadline' => $deadline]);
        });
        $this->realtime->graphParticipant($participant->id, $participant->substrate_id);

        return $token;
    }

    public function heartbeat(Participant $participant, string $token): void
    {
        $changed = $this->locked($participant->id, function () use ($participant, $token): bool {
            $lease = Cache::get($this->key($participant->id));
            if (! is_array($lease) || $lease['token'] !== $token || $lease['deadline'] <= now()->timestamp) {
                return false;
            }
            $this->put($participant->id, $lease);

            return true;
        });
        if ($changed) {
            $this->realtime->graphParticipant($participant->id, $participant->substrate_id);
        }
    }

    public function end(Participant $participant, string $token): void
    {
        $changed = $this->locked($participant->id, function () use ($participant, $token): bool {
            $lease = Cache::get($this->key($participant->id));
            if (! is_array($lease) || $lease['token'] !== $token) {
                return false;
            }
            Cache::forget($this->key($participant->id));

            return true;
        });
        if ($changed) {
            $this->realtime->graphParticipant($participant->id, $participant->substrate_id);
        }
    }

    /** One shared-cache batch, with no inference or private connection metadata. */
    public function snapshot(Collection $participants): array
    {
        $leases = Cache::many($participants->map(fn ($p) => $this->key($p->id))->all());
        $at = now();

        return ['server_time' => $at->toIso8601String(), 'participants' => $participants->map(function ($participant) use ($leases, $at): array {
            $lease = $leases[$this->key($participant->id)] ?? null;
            $active = is_array($lease) && ($lease['expires_at'] ?? 0) > $at->timestamp;

            return ['id' => $participant->id, 'callsign' => $participant->callsign,
                'current_proposition_id' => $participant->current_proposition_id,
                'is_active' => $active, 'activity_expires_at' => $active ? $lease['expires_at'] * 1000 : null];
        })->values()->all()];
    }

    private function put(string $id, array $lease): void
    {
        // Cover all three configured inference HTTP attempts plus a margin.
        // A hard deadline prevents heartbeats extending a wake indefinitely.
        $seconds = max(60, 3 * (int) config('services.openai.timeout', 90) + 30);
        $lease['expires_at'] = min($lease['deadline'], now()->timestamp + $seconds);
        Cache::put($this->key($id), $lease, max(1, $lease['expires_at'] - now()->timestamp));
    }

    private function key(string $id): string
    {
        return 'commons:participant-activity:'.$id;
    }

    private function locked(string $id, callable $callback): mixed
    {
        // Compare-and-clear must not let a delayed old worker clear a newer wake.
        return Cache::lock($this->key($id).':lock', 5)->block(2, $callback);
    }
}
