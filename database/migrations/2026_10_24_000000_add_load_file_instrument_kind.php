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
            ['citation', 'artefact', 'hyperlink', 'web_search', 'proposition_search', 'load_file']);
        $this->kind('receipts', 'type',
            ['citation', 'artefact', 'hyperlink', 'web_search', 'tragedy', 'proposition_search', 'preparation', 'load_file']);
    }

    public function down(): void
    {
        if (DB::table('evidence_executions')->where('kind', 'load_file')->exists()
            || DB::table('receipts')->where('type', 'load_file')->exists()) {
            throw new RuntimeException('Cannot discard Load File Instrument or Receipt history.');
        }

        $this->kind('evidence_executions', 'kind',
            ['citation', 'artefact', 'hyperlink', 'web_search', 'proposition_search']);
        $this->kind('receipts', 'type',
            ['citation', 'artefact', 'hyperlink', 'web_search', 'tragedy', 'proposition_search', 'preparation']);
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

        // SQLite's schema builder omits partial-index predicates during a table
        // rebuild. Keep both execution allowance indexes exact, never global.
        $partial = DB::getDriverName() === 'sqlite'
            ? DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql LIKE '% WHERE %'", [$table]) : [];
        foreach ($partial as $index) {
            DB::statement('DROP INDEX "'.str_replace('"', '""', $index->name).'"');
        }
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->enum($column, $values)->nullable($table === 'evidence_executions')->change());
        foreach ($partial as $index) {
            DB::statement($index->sql);
        }
    }
};
