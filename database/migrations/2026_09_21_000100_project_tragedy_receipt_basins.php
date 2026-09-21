<?php

use App\Services\TragedyReceiptScopeBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tragedyTable = Schema::hasTable('horizon_tragedies') ? 'horizon_tragedies' : 'tragedies';
        Schema::create('tragedy_receipt_scopes', function (Blueprint $table) use ($tragedyTable): void {
            $table->foreignUlid('tragedy_id')->primary()->constrained($tragedyTable)->restrictOnDelete();
            $table->foreignUlid('current_committal_id')->nullable()->index()->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('recovery_phase_id')->nullable()->constrained('phases')->restrictOnDelete();
        });
        app(TragedyReceiptScopeBackfill::class)->run($tragedyTable);
    }

    public function down(): void
    {
        // Only a rebuildable projection is removed; no ledger/proof row is touched.
        Schema::dropIfExists('tragedy_receipt_scopes');
    }
};
