<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttestationCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commons:attestation-create
        {participant_id : ULID of the participant creating the attestation}
        {proposition_id : ULID of the proposition being attested}
        {stance : support|refute|cite}
        {rationale? : Optional attestation rationale}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an attestation (support, refute, or cite) for a proposition.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $participantId = (string) $this->argument('participant_id');
        $propositionId = (string) $this->argument('proposition_id');
        $stance = strtolower((string) $this->argument('stance'));
        $rationale = $this->argument('rationale');

        if (!$this->isUlid($participantId)) {
            $this->error("Invalid participant_id ULID: {$participantId}");
            return self::FAILURE;
        }

        if (!$this->isUlid($propositionId)) {
            $this->error("Invalid proposition_id ULID: {$propositionId}");
            return self::FAILURE;
        }

        if (!in_array($stance, ['support', 'refute', 'cite'], true)) {
            $this->error("Invalid stance: {$stance}. Must be one of: support, refute, cite.");
            return self::FAILURE;
        }

        // FK safety: ensure participant exists
        $participantExists = DB::table('participants')->where('id', $participantId)->exists();
        if (!$participantExists) {
            $this->error("Participant not found: {$participantId}");
            return self::FAILURE;
        }

        // FK safety: ensure proposition exists
        $propositionExists = DB::table('propositions')->where('id', $propositionId)->exists();
        if (!$propositionExists) {
            $this->error("Proposition not found: {$propositionId}");
            return self::FAILURE;
        }

        $id = (string) Str::ulid();
        $now = now();

        try {
            DB::table('attestations')->insert([
                'id' => $id,
                'participant_id' => $participantId,
                'proposition_id' => $propositionId,
                'stance' => $stance,
                'rationale' => is_string($rationale) && trim($rationale) !== '' ? $rationale : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            $this->error('Failed to create attestation: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Attestation created: {$id}");
        $this->line("stance: {$stance}");
        $this->line("proposition_id: {$propositionId}");
        $this->line("participant_id: {$participantId}");

        return self::SUCCESS;
    }

    private function isUlid(string $value): bool
    {
        return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value);
    }
}
