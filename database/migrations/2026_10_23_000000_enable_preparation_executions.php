<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A successful start reserves the allowance, including a later technical failure.
        DB::statement("CREATE UNIQUE INDEX evidence_preparation_cycle_unique ON evidence_executions (cycle_id) WHERE type = 'prepare'");
        $this->receiptTypes(['citation', 'artefact', 'hyperlink', 'web_search', 'tragedy', 'proposition_search', 'preparation']);
    }

    private function receiptTypes(array $values): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE receipts DROP CONSTRAINT IF EXISTS receipts_type_check');
            DB::statement("ALTER TABLE receipts ADD CONSTRAINT receipts_type_check CHECK (type IN ('".implode("','", $values)."'))");
        } else {
            Schema::table('receipts', fn (Blueprint $table) => $table->enum('type', $values)->change());
        }
    }

    public function down(): void
    {
        if (DB::table('evidence_executions')->where('type', 'prepare')->exists()) {
            throw new RuntimeException('Cannot discard Preparation execution history. Use a fresh database.');
        }
        DB::statement('DROP INDEX evidence_preparation_cycle_unique');
        $this->receiptTypes(['citation', 'artefact', 'hyperlink', 'web_search', 'tragedy', 'proposition_search']);
    }
};
