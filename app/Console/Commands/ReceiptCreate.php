<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReceiptCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commons:receipt-create
        {participant_id : ULID of the participant creating the receipt}
        {receiptable_type : proposition|attestation}
        {receiptable_id : ULID of the proposition or attestation being receipted}
        {kind : dataset|storage|url}
        {reference : Receipt reference (URL, storage pointer, dataset)}
        {--meta= : Optional JSON string metadata payload}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a receipt attached to a proposition or attestation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $participantId      = (string) $this->argument('participant_id');
        $receiptableType    = strtolower((string) $this->argument('receiptable_type'));
        $receiptableId      = (string) $this->argument('receiptable_id');
        $kind               = strtolower((string) $this->argument('kind'));
        $reference            = (string) $this->argument('reference');
        $metaOption         = $this->option('meta');

        // -------------------------
        // Basic validation
        // -------------------------

        if (!$this->isUlid($participantId)) {
            $this->error("Invalid participant_id ULID: {$participantId}");
            return self::FAILURE;
        }

        if (!in_array($receiptableType, ['proposition', 'attestation'], true)) {
            $this->error("Invalid attach_type: {$receiptableType}. Must be proposition or attestation.");
            return self::FAILURE;
        }

        if (!$this->isUlid($receiptableId)) {
            $this->error("Invalid attach_id ULID: {$receiptableId}");
            return self::FAILURE;
        }

        if (!in_array($kind, ['url', 'storage', 'dataset'], true)) {
            $this->error("Invalid type: {$kind}. Must be url, storage, or dataset.");
            return self::FAILURE;
        }

        if (empty($reference)) {
            $this->error("Receipt content cannot be empty.");
            return self::FAILURE;
        }

        // -------------------------
        // FK safety: participant exists
        // -------------------------

        if (!DB::table('participants')->where('id', $participantId)->exists()) {
            $this->error("Participant not found: {$participantId}");
            return self::FAILURE;
        }

        // -------------------------
        // Resolve polymorphic target
        // -------------------------

        $targetTable = match ($receiptableType) {
            'proposition' => 'propositions',
            'attestation' => 'attestations',
        };

        $modelType = match ($receiptableType) {
            'proposition' => 'App\\Models\\Proposition',
            'attestation' => 'App\\Models\\Attestation',
        };

        if (!DB::table($targetTable)->where('id', $receiptableId)->exists()) {
            $this->error("Target {$modelType} not found: {$receiptableId}");
            return self::FAILURE;
        }

        // -------------------------
        // Optional meta JSON decode
        // -------------------------

        $meta = null;

        if (is_string($metaOption) && trim($metaOption) !== '') {
            $decoded = json_decode($metaOption, true);

            if (!is_array($decoded)) {
                $this->error("Invalid --meta JSON string.");
                return self::FAILURE;
            }

            $meta = $decoded;
        }

        // -------------------------
        // Insert receipt
        // -------------------------

        $id  = (string) Str::ulid();
        $now = now();

        try {
            DB::table('receipts')->insert([
                'id'               => $id,
                'participant_id'   => $participantId,

                // Polymorphic binding
                'receiptable_type' => $receiptableType,
                'receiptable_id'   => $receiptableId,

                // Receipt classification
                'kind'             => $kind,
                'reference'          => $reference,
                'meta'             => $meta ? json_encode($meta) : null,

                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        } catch (\Throwable $e) {
            $this->error("Failed to create receipt: " . $e->getMessage());
            return self::FAILURE;
        }

        // -------------------------
        // Success output
        // -------------------------

        $this->info("Receipt created: {$id}");
        $this->line("kind: {$kind}");
        $this->line("attached_to: {$receiptableType} {$receiptableId}");
        $this->line("participant_id: {$participantId}");

        return self::SUCCESS;
    }

    private function isUlid(string $value): bool
    {
        return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value);
    }
}
