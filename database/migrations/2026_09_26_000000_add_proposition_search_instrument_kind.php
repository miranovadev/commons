<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->kinds(['citation', 'artefact', 'hyperlink', 'web_search', 'proposition_search']);
    }

    public function down(): void
    {
        if (DB::table('instrument_executions')->where('kind', 'proposition_search')->exists()
            || DB::table('receipts')->where('type', 'proposition_search')->exists()) {
            throw new RuntimeException('Cannot discard Proposition Search Instrument or Receipt history.');
        }

        $this->kinds(['citation', 'artefact', 'hyperlink', 'web_search']);
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
