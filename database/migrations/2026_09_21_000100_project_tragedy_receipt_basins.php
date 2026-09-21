<?php

use App\Services\TragedyReceiptScopeBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tragedy_receipt_scopes', function (Blueprint $table): void {
            $table->foreignUlid('tragedy_id')->primary()->constrained('horizon_tragedies')->restrictOnDelete();
            $table->foreignUlid('current_committal_id')->nullable()->index()->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('recovery_phase_id')->nullable()->constrained('phases')->restrictOnDelete();
        });
        app(TragedyReceiptScopeBackfill::class)->run();
    }

    public function down(): void
    {
        // Only a rebuildable projection is removed; no ledger/proof row is touched.
        Schema::dropIfExists('tragedy_receipt_scopes');
    }
};
