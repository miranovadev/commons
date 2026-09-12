<?php

namespace App\Services\Evidence;

use App\Domain\Evidence\VerifiedHyperlink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReinforcementSignatureService
{
    public const int MIN_VERSION_AGE_DAYS = 7;

    /** Caller holds the receipt-completion transaction. The source clock is global. */
    public function resolve(VerifiedHyperlink $evidence): array
    {
        $key = hash('sha256', $evidence->resource);
        $signature = hash('sha256', "hyperlink:v1\n".$evidence->resource."\n".$evidence->fingerprint);
        $now = CarbonImmutable::now();
        DB::table('reinforcement_sources')->insertOrIgnore([
            'resource_key' => $key, 'resource' => $evidence->resource,
            'fingerprint' => $evidence->fingerprint, 'signature' => $signature,
            'accepted_at' => $now,
        ]);
        $source = DB::table('reinforcement_sources')->where('resource_key', $key)->lockForUpdate()->first();
        if ($source->fingerprint === $evidence->fingerprint) {
            DB::table('reinforcement_sources')->where('resource_key', $key)->update([
                'pending_fingerprint' => null, 'pending_since' => null,
            ]);

            return ['signature' => $source->signature, 'status' => 'accepted-version'];
        }
        if ($source->pending_fingerprint !== $evidence->fingerprint) {
            DB::table('reinforcement_sources')->where('resource_key', $key)->update([
                'pending_fingerprint' => $evidence->fingerprint, 'pending_since' => $now,
            ]);

            return ['signature' => null, 'status' => 'version-awaiting-stability'];
        }
        $cutoff = $now->subDays(self::MIN_VERSION_AGE_DAYS);
        if (CarbonImmutable::parse($source->accepted_at)->gt($cutoff)
            || CarbonImmutable::parse($source->pending_since)->gt($cutoff)) {
            return ['signature' => null, 'status' => 'version-awaiting-stability'];
        }
        DB::table('reinforcement_sources')->where('resource_key', $key)->update([
            'fingerprint' => $evidence->fingerprint, 'signature' => $signature,
            'accepted_at' => $now, 'pending_fingerprint' => null, 'pending_since' => null,
        ]);

        return ['signature' => $signature, 'status' => 'accepted-version'];
    }
}
