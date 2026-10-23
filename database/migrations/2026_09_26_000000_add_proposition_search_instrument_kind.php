<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->kind('evidence_executions', 'kind',
            ['citation', 'artefact', 'hyperlink', 'web_search', 'proposition_search']);
        $this->kind('receipts', 'type',
            ['citation', 'artefact', 'hyperlink', 'web_search', 'tragedy', 'proposition_search']);
    }

    public function down(): void
    {
        if (DB::table('evidence_executions')->where('kind', 'proposition_search')->exists()
            || DB::table('receipts')->where('type', 'proposition_search')->exists()) {
            throw new RuntimeException('Cannot discard Proposition Search Instrument or Receipt history.');
        }

        $this->kind('evidence_executions', 'kind',
            ['citation', 'artefact', 'hyperlink', 'web_search']);
        $this->kind('receipts', 'type',
            ['citation', 'artefact', 'hyperlink', 'web_search', 'tragedy']);
    }

    private function kind(string $table, string $column, array $values): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Laravel implements enums as CHECK constraints on PostgreSQL.
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_{$column}_check");
            $allowed = "'".implode("','", $values)."'";
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$allowed}))");

            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->enum($column, $values)->nullable($table === 'evidence_executions')->change());
    }
};
