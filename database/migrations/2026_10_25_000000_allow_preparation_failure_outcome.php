<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->outcomes(['contributed', 'deferred', 'failed']);
    }

    public function down(): void
    {
        if (DB::table('evidence_executions')->where('outcome', 'failed')->exists()) {
            throw new RuntimeException('Cannot discard recorded Preparation failures. Use a fresh database.');
        }
        $this->outcomes(['contributed', 'deferred']);
    }

    private function outcomes(array $values): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE evidence_executions DROP CONSTRAINT IF EXISTS evidence_executions_outcome_check');
            DB::statement("ALTER TABLE evidence_executions ADD CONSTRAINT evidence_executions_outcome_check CHECK (outcome IN ('".implode("','", $values)."'))");
        } else {
            // SQLite table rebuilds otherwise lose partial allowance predicates.
            $partial = DB::getDriverName() === 'sqlite'
                ? DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'evidence_executions' AND sql LIKE '% WHERE %'") : [];
            foreach ($partial as $index) {
                DB::statement('DROP INDEX "'.str_replace('"', '""', $index->name).'"');
            }
            Schema::table('evidence_executions', fn (Blueprint $table) => $table->enum('outcome', $values)->nullable()->change());
            foreach ($partial as $index) {
                DB::statement($index->sql);
            }
        }
    }
};
