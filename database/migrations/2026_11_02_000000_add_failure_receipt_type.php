<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->types(true);
    }

    public function down(): void
    {
        if (DB::table('receipts')->where('type', 'failure')->exists()) {
            throw new RuntimeException('Cannot discard Failure Receipt history.');
        }
        $this->types(false);
    }

    private function types(bool $includeFailure): void
    {
        $types = ['citation', 'artefact', 'hyperlink', 'web_search', 'tragedy', 'proposition_search', 'preparation', 'load_file'];
        if ($includeFailure) {
            $types[] = 'failure';
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE receipts DROP CONSTRAINT receipts_type_check');
            DB::statement("ALTER TABLE receipts ADD CONSTRAINT receipts_type_check CHECK (type IN ('".implode("','", $types)."'))");
        } else {
            Schema::table('receipts', fn (Blueprint $table) => $table->enum('type', $types)->change());
        }
    }
};
