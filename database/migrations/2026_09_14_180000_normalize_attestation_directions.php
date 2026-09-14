<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite must disable FK enforcement before beginning its table-rebuild transaction.
    public $withinTransaction = false;

    public function up(): void
    {
        $this->convert(['support' => 'positive', 'refute' => 'negative']);
    }

    public function down(): void
    {
        $this->convert(['positive' => 'support', 'negative' => 'refute']);
    }

    private function convert(array $mapping): void
    {
        $convert = function () use ($mapping): void {
            DB::transaction(function () use ($mapping): void {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('ALTER TABLE attestations DROP CONSTRAINT IF EXISTS attestations_direction_check');
                } else {
                    Schema::table('attestations', fn (Blueprint $table) => $table
                        ->enum('direction', array_merge(array_keys($mapping), array_values($mapping)))->change());
                }

                foreach ($mapping as $from => $to) {
                    DB::table('attestations')->where('direction', $from)->update(['direction' => $to]);
                }

                if (DB::getDriverName() === 'pgsql') {
                    $allowed = implode(', ', array_map(fn ($value) => "'{$value}'", array_values($mapping)));
                    DB::statement("ALTER TABLE attestations ADD CONSTRAINT attestations_direction_check CHECK (direction IN ({$allowed}))");
                } else {
                    Schema::table('attestations', fn (Blueprint $table) => $table
                        ->enum('direction', array_values($mapping))->change());
                }
            });
        };

        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints($convert);
        } else {
            $convert();
        }
    }
};
