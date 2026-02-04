<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;

class PropositionCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commons:proposition-create
        {participant_id : ULID of the participant creating the proposition}
        {statement : JSON string payload matching the proposition create schema}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a proposition from a participant ID and a statement';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $participantId = (string) $this->argument('participant_id');
        $json = (string) $this->argument('statement');

        // 1) Parse JSON
        $proposition = json_decode($json, true);
        if (!is_array($proposition)) {
            $this->error('Invalid JSON: could not decode into an object/associative array.');
            $this->line('Tip: wrap the JSON in single quotes in your shell.');
            return self::FAILURE;
        }

        // 2) Validate participant exists (placeholder allowed)
        // You said ignore participants table for now, but we can still sanity-check.
        $participantExists = DB::table('participants')->where('id', $participantId)->exists();
        if (!$participantExists) {

            $this->error("Participant not found: {$participantId}");
            return self::FAILURE;
        }

        // 3) Load proposition-create schema (as PHP array)
        // Put the schema array at config/schemas/proposition_create.php (or adjust path).
        $schemaArray = config('schemas.proposition.create');

        if (!is_array($schemaArray)) {
            $this->error('Schema not found. Expected config("schemas.proposition.create") to return an array.');
            return self::FAILURE;
        }

        // 4) Opis validation without extra encode/decode
        $validator = new Validator();

        $data = Helper::toJSON($proposition);
        $schema = Helper::toJSON($schemaArray);

        $result = $validator->validate($data, $schema);

        if (!$result->isValid()) {
            $this->error('Schema validation failed.');

            $formatter = new ErrorFormatter();
            $errors = $formatter->format($result->error(), true);

            // Pretty-print errors
            $this->line(json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::FAILURE;
        }

        // 5) Prepare insert
        // If client supplied id, use it; else generate ULID.
        $id = isset($proposition['id']) && is_string($proposition['id']) && $proposition['id'] !== ''
            ? $proposition['id']
            : (string) Str::ulid();

        $now = now();

        // 6) Insert
        try {
            DB::table('propositions')->insert([
                'id'                => $id,
                'participant_id'    => $participantId,
                'register'          => $proposition['register'],
                'tier'              => $proposition['tier'],
                'visibility'        => $proposition['visibility'],
                'statement'         => $proposition['statement'],
                'meta'              => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        } catch (\Throwable $e) {
            $this->error('Failed to insert proposition: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Proposition created: {$id}");
        return self::SUCCESS;
    }
}
