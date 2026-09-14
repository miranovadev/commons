<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->kinds(['citation', 'artefact', 'hyperlink', 'web_search']);
        Schema::table('receipts', function (Blueprint $table) {
            // Null preserves deliberate legacy capability resolution; [] means archival.
            $table->jsonb('capabilities')->nullable();
        });
        Schema::table('instrument_executions', function (Blueprint $table) {
            $table->foreignUlid('replayed_from_receipt_id')->nullable()->constrained('receipts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('instrument_executions')->where('kind', 'web_search')->orWhereNotNull('replayed_from_receipt_id')->exists()) {
            throw new RuntimeException('Cannot discard Web Search or Receipt replay history.');
        }
        Schema::table('instrument_executions', fn (Blueprint $table) => $table->dropConstrainedForeignId('replayed_from_receipt_id'));
        Schema::table('receipts', fn (Blueprint $table) => $table->dropColumn('capabilities'));
        $this->kinds(['citation', 'artefact', 'hyperlink']);
    }

    private function kinds(array $values): void
    {
        foreach (['instrument_executions' => 'kind', 'receipts' => 'type'] as $table => $column) {
            if (DB::getDriverName() === 'pgsql') {
                // Laravel implements enums as CHECK constraints on PostgreSQL.
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_{$column}_check");
                $allowed = "'".implode("','", $values)."'";
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$allowed}))");
            } else {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->enum($column, $values)->change());
            }
        }
    }
};
