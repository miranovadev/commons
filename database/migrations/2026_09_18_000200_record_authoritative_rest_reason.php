<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', fn (Blueprint $table) => $table->string('rest_reason', 32)->nullable());
        // Unknown historical reasons stay unknown. They cannot prove a new Tragedy.
    }

    public function down(): void
    {
        Schema::table('cycles', fn (Blueprint $table) => $table->dropColumn('rest_reason'));
    }
};
